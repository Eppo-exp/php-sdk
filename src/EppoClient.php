<?php

namespace Eppo;

use Eppo\API\APIRequestWrapper;
use Eppo\Bandits\BanditEvaluator;
use Eppo\Bandits\IBanditEvaluator;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigurationLoader;
use Eppo\Config\ConfigurationStore;
use Eppo\Config\SDKData;
use Eppo\DTO\Bandit\AttributeSet;
use Eppo\DTO\Bandit\BanditResult;
use Eppo\DTO\Variation;
use Eppo\DTO\VariationType;
use Eppo\Exception\BanditEvaluationException;
use Eppo\Exception\EppoClientException;
use Eppo\Exception\EppoClientInitializationException;
use Eppo\Exception\EppoException;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Exception\InvalidConfigurationException;
use Eppo\Logger\AssignmentEvent;
use Eppo\Logger\BanditActionEvent;
use Eppo\Logger\IBanditLogger;
use Eppo\Logger\LoggerInterface;
use Exception;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

class EppoClient
{
    public const SECOND_MILLIS = 1000;
    public const MINUTE_MILLIS = 60 * self::SECOND_MILLIS;
    public const POLL_INTERVAL_MILLIS = 5 * self::MINUTE_MILLIS;
    public const JITTER_MILLIS = 30 * self::SECOND_MILLIS;

    private static ?EppoClient $instance = null;
    private RuleEvaluator $evaluator;
    private IBanditEvaluator $banditEvaluator;


    /**
     * @param ConfigurationLoader $configurationLoader
     * @param PollerInterface $poller
     * @param LoggerInterface|null $eventLogger optional logger. Please @see LoggerInterface
     * @param bool|null $isGracefulMode
     * @param IBanditEvaluator|null $banditEvaluator
     */
    protected function __construct(
        private readonly ConfigurationLoader $configurationLoader,
        private readonly PollerInterface $poller,
        private readonly ?LoggerInterface $eventLogger = null,
        private readonly ?bool $isGracefulMode = true,
        IBanditEvaluator $banditEvaluator = null,
    ) {
        $this->evaluator = new RuleEvaluator();
        $this->banditEvaluator = $banditEvaluator ?? new BanditEvaluator();
    }

    /**
     * Initializes EppoClient singleton instance.
     *
     * @param LoggerInterface|null $assignmentLogger optional assignment logger. Please @see LoggerInterface.
     * @param CacheInterface|null $cache optional Compatible with psr-16 simple cache. By default, (if nothing passed)
     * EppoClient will use FileSystem cache.
     * @param ClientInterface|null $httpClient optional PSR-18 ClientInterface. If nothing is passed, EppoClient will
     * use Discovery to locate a suitable implementation in the project.
     * @param RequestFactoryInterface|null $requestFactory optional PSR-17 Request Factory implementation. If none is
     * provided, EppoClient will use Discovery
     * @throws EppoClientInitializationException
     * @throws EppoClientException
     */
    public static function init(
        string $apiKey,
        ?string $baseUrl = null,
        LoggerInterface $assignmentLogger = null,
        CacheInterface $cache = null,
        ClientInterface $httpClient = null,
        RequestFactoryInterface $requestFactory = null,
        ?bool $isGracefulMode = true
    ): EppoClient {
        // Get SDK metadata to pass as params in the http client.
        $sdkData = new SDKData();
        $sdkParams = [
            'sdkVersion' => $sdkData->getSdkVersion(),
            'sdkName' => $sdkData->getSdkName()
        ];

        if (!$cache) {
            try {
                $cache = (new DefaultCacheFactory())->create();
            } catch (Exception $e) {
                throw EppoClientInitializationException::from($e);
            }
        }

        $configStore = new ConfigurationStore($cache);

        if (!$httpClient) {
            $httpClient = Psr18ClientDiscovery::find();
        }
        $requestFactory = $requestFactory ?: new Psr17Factory();

        $apiWrapper = new APIRequestWrapper(
            $apiKey,
            $sdkParams,
            $httpClient,
            $requestFactory,
            $baseUrl
        );

        $configLoader = new ConfigurationLoader($apiWrapper, $configStore);
        $poller = new Poller(
            self::POLL_INTERVAL_MILLIS,
            self::JITTER_MILLIS,
            function () use ($configLoader) {
                $configLoader->fetchAndStoreConfigurations();
            }
        );

        self::$instance = self::createAndInitClient($configLoader, $poller, $assignmentLogger, $isGracefulMode);


        return self::$instance;
    }

    /**
     * @throws EppoClientInitializationException
     */
    private static function createAndInitClient(
        ConfigurationLoader $configLoader,
        PollerInterface $poller,
        ?LoggerInterface $assignmentLogger,
        ?bool $isGracefulMode,
        ?IBanditEvaluator $banditEvaluator = null
    ): EppoClient {
        try {
            $configLoader->reloadConfigurationIfExpired();
        } catch (HttpRequestException | InvalidApiKeyException $e) {
            throw new EppoClientInitializationException(
                'Unable to initialize Eppo Client: ' . $e->getMessage()
            );
        }
        return new self($configLoader, $poller, $assignmentLogger, $isGracefulMode, $banditEvaluator);
    }

    /**
     * Gets singleton instance of the EppoClient.
     * Run EppoClient->init before using this.
     *
     * @return EppoClient
     */
    public static function getInstance(): EppoClient
    {
        return self::$instance;
    }

    /**
     * @throws EppoClientException
     */
    private function getTypedAssignment(
        VariationType $valueType,
        string $flagKey,
        string $subjectKey,
        array $subjectAttributes,
        array|bool|float|int|string $defaultValue
    ): array|bool|float|int|string {
        try {
            $assignmentVariation = $this->getAssignmentDetail($flagKey, $subjectKey, $subjectAttributes, $valueType);
            if ($assignmentVariation === null) {
                return $defaultValue;
            }
            return match ($valueType) {
                VariationType::JSON, VariationType::STRING => $assignmentVariation->value,
                VariationType::NUMERIC => doubleval($assignmentVariation->value),
                VariationType::INTEGER => intval($assignmentVariation->value),
                VariationType::BOOLEAN => boolval($assignmentVariation->value)
            };
        } catch (Exception $exception) {
            return $this->handleException($exception, $defaultValue);
        }
    }

    /**
     * Gets the assigned string variation for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not a string, null wil be returned.
     *
     * @throws EppoClientException
     */
    public function getStringAssignment(
        string $flagKey,
        string $subjectKey,
        array $subjectAttributes,
        string $defaultValue
    ): string {
        return $this->getTypedAssignment(
            VariationType::STRING,
            $flagKey,
            $subjectKey,
            $subjectAttributes,
            $defaultValue
        );
    }

    /**
     * Gets the assigned boolean variation for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not a boolean, null wil be returned.
     *
     * @throws EppoClientException
     */
    public function getBooleanAssignment(
        string $flagKey,
        string $subjectKey,
        array $subjectAttributes,
        bool $defaultValue
    ): bool {
        return $this->getTypedAssignment(
            VariationType::BOOLEAN,
            $flagKey,
            $subjectKey,
            $subjectAttributes,
            $defaultValue
        );
    }

    /**
     * Gets the assigned numeric variation as a float for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not an integer or float (double),
     * null wil be returned.
     *
     * @throws EppoClientException
     */
    public function getNumericAssignment(
        string $flagKey,
        string $subjectKey,
        array $subjectAttributes,
        float $defaultValue
    ): float {
        return $this->getTypedAssignment(
            VariationType::NUMERIC,
            $flagKey,
            $subjectKey,
            $subjectAttributes,
            $defaultValue
        );
    }

    /**
     * Gets the assigned variation as an integer for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not an integer, null wil be returned.
     *
     * @throws EppoClientException
     */
    public function getIntegerAssignment(
        string $flagKey,
        string $subjectKey,
        array $subjectAttributes,
        int $defaultValue
    ): int {
        return $this->getTypedAssignment(
            VariationType::INTEGER,
            $flagKey,
            $subjectKey,
            $subjectAttributes,
            $defaultValue
        );
    }

    /**
     * Gets the assigned JSON variation, as parsed by PHP's json_decode, for the given subject and experiment.
     * If there is an issue retrieving the variation or the retrieved variation is not valid JSON, null wil be returned.
     *
     * @param string $flagKey
     * @param string $subjectKey
     * @param array $subjectAttributes
     * @param array $defaultValue
     * @return array the parsed variation JSON
     *
     * @throws EppoClientException
     */
    public function getJSONAssignment(
        string $flagKey,
        string $subjectKey,
        array $subjectAttributes,
        array $defaultValue
    ): array {
        return $this->getTypedAssignment(VariationType::JSON, $flagKey, $subjectKey, $subjectAttributes, $defaultValue);
    }

    /**
     * Maps a subject to a Variation for the given flag.
     *
     * If there is an expected type for the variation value, a type check is performed as well.
     *
     * Returns null if the subject has no allocation for the flag.
     *
     * @param string $flagKey a feature flag identifier
     * @param string $subjectKey an identifier for the experiment. Ex: a user ID
     * @param array $subjectAttributes optional attributes to use in the evaluation of experiment targeting rules.
     * These attributes are also included in the logging callback.
     * @param VariationType|null $expectedVariationType
     * @return Variation|null the Variation DTO assigned to the subject, or null if there is no assignment,
     * an error was encountered, or an expected type was provided that didn't match the variation's typed
     * value.
     * @throws InvalidArgumentException
     * @throws InvalidApiKeyException
     * @throws HttpRequestException
     * @throws InvalidConfigurationException
     */
    private function getAssignmentDetail(
        string $flagKey,
        string $subjectKey,
        array $subjectAttributes = [],
        VariationType $expectedVariationType = null
    ): ?Variation {
        Validator::validateNotBlank($subjectKey, 'Invalid argument: subjectKey cannot be blank');
        Validator::validateNotBlank($flagKey, 'Invalid argument: flagKey cannot be blank');

        $flag = $this->configurationLoader->getFlag($flagKey);

        if (!$flag) {
            syslog(LOG_WARNING, "[EPPO SDK] No assigned variation; flag not found ${flagKey}");
            return null;
        }

        $evaluationResult = $this->evaluator->evaluateFlag($flag, $subjectKey, $subjectAttributes);
        $computedVariation = $evaluationResult?->variation ?? null;

        // If there is an assignment and the expected type has been expressed, do a type check and log an error if
        //they don't match.
        if (
            $computedVariation && $expectedVariationType && !$this->checkExpectedType(
                $expectedVariationType,
                $computedVariation->value
            )
        ) {
            $actualType = gettype($computedVariation->value);
            $eVarType = $expectedVariationType->value;
            syslog(LOG_ERR, "[EPPO SDK] Variation does not have the expected type, ${eVarType}; found ${actualType}");
            return null;
        }

        if (!$flag->enabled) {
            syslog(LOG_INFO, '[EPPO SDK] No assigned variation; flag is disabled.');
            return null;
        }

        // If an assignment was made, log it using the user-provided logger callback.
        if ($computedVariation && $this->eventLogger && $evaluationResult->doLog) {
            try {
                $allocationKey = $evaluationResult->allocationKey;
                $sdkData = (new SDKData())->asArray();
                $experimentKey = "$flagKey-$allocationKey";
                $this->eventLogger->logAssignment(
                    new AssignmentEvent(
                        $experimentKey,
                        $evaluationResult->variation->key,
                        $allocationKey,
                        $flagKey,
                        $subjectKey,
                        microtime(true),
                        $subjectAttributes,
                        $sdkData,
                        $evaluationResult->extraLogging ?? []
                    )
                );
            } catch (Exception $exception) {
                error_log('[Eppo SDK] Error logging assignment event: ' . $exception->getMessage());
            }
        }

        return $computedVariation;
    }

    /**
     * Selects a Bandit action, if applicable, based on the flag key, subject, and actions provided.
     *
     * Actions are selected based on the subject and action contexts. Contexts are passed as associative arrays
     * (key=>value) of attributes which, by default, are sorted into numeric and non-numeric. Non-numeric attributes
     * are bucketed as "Categorical Attributes" while numeric are treated as "Numeric Attributes". Demonstrated in the
     * **Example 2** below is a means of explicitly classifying attributes as either Numeric or Categorical.
     *
     * Logging selected bandits to your data warehouse is critical. Instead of just implementing `LoggerInterface`,
     * implement `IBanditLogger`. This interface logging methods for both flag assignments and bandits.
     *
     * Example 1:
     *
     * $flagKey = 'my-bandit-flag';
     * $subject = 'user-123';
     * $subjectContext = ['accountAge' => 0.5, 'country' => 'US'];
     *
     * // A simple list of actions with no context attributes
     * $actions = ['nike', 'adidas', 'reebok'];
     *
     * $result = $client->getBanditAction($flagKey, $subject, $subjectContext, $actions, 'control');
     *
     * Example 1.2: Actions with un-grouped attributes
     * $actions = [
     *   'nike': [
     *     'brandLoyalty' => 0.0,
     *     'size' => 5,
     *     'colour' => 'red'
     *   ], ...
     * ];
     *
     * $result = $client->getBanditAction($flagKey, $subject, $subjectContext, $actions, 'control');
     *
     *
     * Example 2:
     *
     * $subjectContext = new AttributeSet(
     *      numericAttributes: ['accountAge' => 0.5],
     *      categoricalAttributes: ['zip' => 90210, 'country' => 'US']
     * );
     *
     * $actions = [
     *   'nike': new AttributeSet(
     *     numericAttributes: ['brandLoyalty' => 0.0],
     *     categoricalAttributes: ['size' => 5, 'colour' => 'red']
     *   ), ...
     * ];
     *
     * $result = $client->getBanditAction($flagKey, $subject, $subjectContext, $actions, 'control');
     *
     * @param string $flagKey
     * @param string $subjectKey
     * @param array<string, ?object>|AttributeSet $subjectContext
     * @param array<string>|array<string, array<string, ?object>>|array<string, AttributeSet> $actions
     * @param string $defaultVariation
     * @return BanditResult
     *
     * @throws EppoClientException
     */
    public function getBanditAction(
        string $flagKey,
        string $subjectKey,
        array|AttributeSet $subjectContext,
        array $actions,
        string $defaultVariation
    ): BanditResult {
        try {
            // Normalize the subject and action into AttributeSets. These functions detect the structure of the
            // user data allowing for either automatic or manual sorting into numeric and categorical attributes.
            $subject = AttributeSet::fromFlexibleInput($subjectContext);
            $actionContexts = AttributeSet::arrayFromFlexibleInput($actions);

            return $this->getBanditDetail($flagKey, $subjectKey, $subject, $actionContexts, $defaultVariation);
        } catch (EppoException $e) {
            if ($this->isGracefulMode) {
                error_log('[Eppo SDK] Error selecting bandit action: ' . $e->getMessage());
                return new BanditResult($defaultVariation);
            } else {
                throw EppoClientException::from($e);
            }
        }
    }

    /**
     * @param string $flagKey
     * @param string $subjectKey ,
     * @param AttributeSet $subject
     * @param array<string, AttributeSet> $actionsWithContext
     * @param string $defaultVariation
     * @return BanditResult
     *
     * @throws InvalidConfigurationException
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws EppoClientException
     * @throws BanditEvaluationException
     */
    private function getBanditDetail(
        string $flagKey,
        string $subjectKey,
        AttributeSet $subject,
        array $actionsWithContext,
        string $defaultVariation
    ): BanditResult {
        Validator::validateNotBlank($flagKey, 'Invalid argument: flagKey cannot be blank');

        $isBanditFlag = $this->configurationLoader->isBanditFlag($flagKey);

        if (empty($actionsWithContext) && $isBanditFlag) {
            // This exception is caught in graceful mode and the default is returned (@see getBanditAction)
            throw new BanditEvaluationException("No actions provided for bandit flag {$flagKey}");
        }

        $variation = $this->getStringAssignment(
            $flagKey,
            $subjectKey,
            $subject->toArray(),
            $defaultVariation
        );

        if (!$isBanditFlag) {
            // It's likely that the developer made a mistake passing a non-bandit flag so let's warn them.
            syslog(LOG_WARNING, "[Eppo SDK]: Flag \"{$flagKey}\" does not contain a Bandit");

            // Return the computed variation without doing any more Bandit work.
            return new BanditResult($variation);
        }

        $banditKey = $this->configurationLoader->getBanditByVariation($flagKey, $variation);
        if ($banditKey == null) {
            // The assigned variation is not a bandit.
            return new BanditResult($variation);
        }

        $bandit = $this->configurationLoader->getBandit($banditKey);
        if ($bandit == null) {
            throw new BanditEvaluationException(
                "Assigned bandit not found for ($flagKey, $variation)",
                EppoException::BANDIT_EVALUATION_FAILED_BANDIT_MODEL_NOT_PRESENT
            );
        }

        $result = $this->banditEvaluator->evaluateBandit(
            $flagKey,
            $subjectKey,
            $subject,
            $actionsWithContext,
            $bandit->modelData
        );

        $banditActionLog = BanditActionEvent::fromEvaluation(
            $variation,
            $result,
            $bandit,
            (new SDKData())->asArray()
        );

        if ($this->eventLogger instanceof IBanditLogger) {
            $this->eventLogger->logBanditAction($banditActionLog);
        }
        return new BanditResult($variation, $result->selectedAction);
    }


    private function checkExpectedType(
        VariationType $expectedVariationType,
        $typedValue
    ): bool {
        return (
            ($expectedVariationType == VariationType::STRING && gettype($typedValue) === 'string') ||
            ($expectedVariationType == VariationType::INTEGER && gettype($typedValue) === 'integer') ||
            ($expectedVariationType == VariationType::NUMERIC && in_array(
                gettype($typedValue),
                ['integer', 'double']
            )) ||
            ($expectedVariationType == VariationType::BOOLEAN && gettype($typedValue) === 'boolean') ||
            ($expectedVariationType == VariationType::JSON)); // JSON type check un-necessary here.
    }


    public function startPolling(): void
    {
        $this->poller->start();
    }

    public function stopPolling(): void
    {
        $this->poller->stop();
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone()
    {
    }

    /**
     * @throws EppoClientException
     */
    private function handleException(
        Exception $exception,
        array|bool|float|int|string|null $defaultValue
    ): array|bool|float|int|string|null {
        if ($this->isGracefulMode) {
            error_log('[Eppo SDK] Error getting assignment: ' . $exception->getMessage());
            return $defaultValue;
        }
        throw EppoClientException::from($exception);
    }

    /**
     * Only used for unit-tests.
     * Do not use for production.
     *
     * @param ConfigurationLoader $configurationLoader
     * @param PollerInterface $poller
     * @param LoggerInterface|null $logger
     * @param bool|null $isGracefulMode
     * @param IBanditEvaluator|null $banditEvaluator
     * @return EppoClient
     * @throws EppoClientInitializationException
     */
    public static function createTestClient(
        ConfigurationLoader $configurationLoader,
        PollerInterface $poller,
        ?LoggerInterface $logger = null,
        ?bool $isGracefulMode = false,
        ?IBanditEvaluator $banditEvaluator = null
    ): EppoClient {
        return self::createAndInitClient($configurationLoader, $poller, $logger, $isGracefulMode, $banditEvaluator);
    }
}
