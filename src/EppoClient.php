<?php

namespace Eppo;

use Eppo\API\APIRequestWrapper;
use Eppo\Bandits\BanditEvaluator;
use Eppo\Bandits\IBanditEvaluator;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigStore;
use Eppo\Config\Configuration;
use Eppo\Config\ConfigurationLoader;
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
    public const DEFAULT_POLL_INTERVAL_MILLIS = 5 * self::MINUTE_MILLIS; // 5 minutes.
    public const DEFAULT_JITTER_MILLIS = 30 * self::SECOND_MILLIS;
    public const DEFAULT_CACHE_AGE_LIMIT = 30 * self::SECOND_MILLIS; // 30 seconds.

    private static ?EppoClient $instance = null;
    private RuleEvaluator $evaluator;
    private IBanditEvaluator $banditEvaluator;

    /**
     * @param ConfigStore $configurationStore
     * @param ConfigurationLoader $configurationLoader
     * @param PollerInterface $poller
     * @param LoggerInterface|null $eventLogger optional logger. Please @see LoggerInterface
     * @param bool|null $isGracefulMode
     * @param IBanditEvaluator|null $banditEvaluator
     */
    protected function __construct(
        private readonly ConfigStore $configurationStore,
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
        ?bool $isGracefulMode = true,
        ?PollingOptions $pollingOptions = null,
        ?bool $throwOnFailedInit = false,
    ): EppoClient {
        // Get SDK metadata to pass as params in the http client.
        $sdkData = new SDKData();
        $sdkParams = [
            'sdkVersion' => $sdkData->getSdkVersion(),
            'sdkName' => $sdkData->getSdkName()
        ];

        if (!$cache) {
            $cache = (new DefaultCacheFactory())->create();
        }

        $configStore = new ConfigStore($cache);

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

        // Polling option defaults
        $cacheAgeLimit = self::DEFAULT_CACHE_AGE_LIMIT;
        $interval = self::DEFAULT_POLL_INTERVAL_MILLIS;
        $jitter = self::DEFAULT_JITTER_MILLIS;

        // If polling options were passed, use them.
        if ($pollingOptions !== null) {
            if ($pollingOptions->cacheAgeLimitMillis !== null) {
                $cacheAgeLimit = $pollingOptions->cacheAgeLimitMillis;
            }
            if ($pollingOptions->pollingIntervalMillis !== null) {
                $interval = $pollingOptions->pollingIntervalMillis;
            }
            if ($pollingOptions->pollingJitterMillis !== null) {
                $jitter = $pollingOptions->pollingJitterMillis;
            }
        }

        $configLoader = new ConfigurationLoader($apiWrapper, $configStore, $cacheAgeLimit);

        $poller = new Poller(
            $interval,
            $jitter,
            function () use ($configLoader) {
                $configLoader->reloadConfiguration();
            }
        );

        self::$instance = self::createAndInitClient(
            $configStore,
            $configLoader,
            $poller,
            $assignmentLogger,
            $isGracefulMode,
            throwOnFailedInit: $throwOnFailedInit
        );

        return self::$instance;
    }

    /**
     * @throws EppoClientInitializationException
     */
    private static function createAndInitClient(
        ConfigStore $configStore,
        ConfigurationLoader $configLoader,
        PollerInterface $poller,
        ?LoggerInterface $assignmentLogger,
        ?bool $isGracefulMode,
        ?IBanditEvaluator $banditEvaluator = null,
        ?bool $throwOnFailedInit = false,
    ): EppoClient {
        try {
            $configLoader->reloadConfigurationIfExpired();
        } catch (Exception | HttpRequestException | InvalidApiKeyException $e) {
            $message = 'Unable to initialize Eppo Client: ' . $e->getMessage();
            if ($throwOnFailedInit) {
                throw new EppoClientInitializationException(
                    $message
                );
            } else {
                syslog(LOG_INFO, "[Eppo SDK] " . $message);
            }
        }
        return new self($configStore, $configLoader, $poller, $assignmentLogger, $isGracefulMode, $banditEvaluator);
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
     */
    private function getAssignmentDetail(
        string $flagKey,
        string $subjectKey,
        array $subjectAttributes = [],
        VariationType $expectedVariationType = null,
        ?Configuration $config = null,
    ): ?Variation {
        Validator::validateNotBlank($subjectKey, 'Invalid argument: subjectKey cannot be blank');
        Validator::validateNotBlank($flagKey, 'Invalid argument: flagKey cannot be blank');

        if ($config === null) {
            $config = $this->configurationStore->getConfiguration();
        }

        $flag = $config->getFlag($flagKey);

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
     * @param string $defaultValue
     * @return BanditResult
     *
     * @throws EppoClientException
     */
    public function getBanditAction(
        string $flagKey,
        string $subjectKey,
        array|AttributeSet $subjectContext,
        array $actions,
        string $defaultValue
    ): BanditResult {
        try {
            // Normalize the subject and action into AttributeSets. These functions detect the structure of the
            // user data allowing for either automatic or manual sorting into numeric and categorical attributes.
            $subject = AttributeSet::fromFlexibleInput($subjectContext);
            $actionContexts = AttributeSet::arrayFromFlexibleInput($actions);

            return $this->getBanditDetail($flagKey, $subjectKey, $subject, $actionContexts, $defaultValue);
        } catch (EppoException $e) {
            if ($this->isGracefulMode) {
                error_log('[Eppo SDK] Error selecting bandit action: ' . $e->getMessage());
                return new BanditResult($defaultValue);
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
     * @param string $defaultValue
     * @return BanditResult
     *
     * @throws InvalidArgumentException
     * @throws BanditEvaluationException
     * @throws EppoClientException
     */
    private function getBanditDetail(
        string $flagKey,
        string $subjectKey,
        AttributeSet $subject,
        array $actionsWithContext,
        string $defaultValue
    ): BanditResult {
        Validator::validateNotBlank($flagKey, 'Invalid argument: flagKey cannot be blank');

        $config = $this->configurationStore->getConfiguration();

        try {
            $variation = $this->getAssignmentDetail(
                $flagKey,
                $subjectKey,
                $subject->toArray(),
                VariationType::STRING,
                $config
            )?->key ?? $defaultValue;
            // TODO return a BanditResult with the default value here instead of going with the default.
        } catch (EppoException $e) {
            syslog(LOG_WARNING, "[Eppo SDK] Error computing experiment assignment: " . $e->getMessage());
            $variation = $defaultValue;
            // TODO return a BanditResult with the default value here instead of going with the default.
        }

        $banditKey = $config->getBanditByVariation($flagKey, $variation);
        if ($banditKey !== null && !empty($actionsWithContext)) {
            // Evaluate the bandit, log and return.

            $bandit = $config->getBandit($banditKey);
            if ($bandit == null) {
                if (!$this->isGracefulMode) {
                    throw new EppoClientException(
                        "Assigned bandit not found for ($flagKey, $variation)",
                        EppoException::BANDIT_EVALUATION_FAILED_BANDIT_MODEL_NOT_PRESENT
                    );
                }
            } else {
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
                    try {
                        $this->eventLogger->logBanditAction($banditActionLog);
                    } catch (Exception $exception) {
                        syslog(LOG_WARNING, "[Eppo SDK] Error in logging bandit action: " . $exception->getMessage());
                    }
                }
                return new BanditResult($variationKey, $result->selectedAction);
            }
        }
        return new BanditResult($variation);
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

    /**
     * @throws EppoClientException
     */
    public function fetchAndActivateConfiguration(): void
    {
        try {
            $this->configurationLoader->fetchAndStoreConfiguration(null);
        } catch (HttpRequestException | InvalidApiKeyException | InvalidConfigurationException $e) {
            if ($this->isGracefulMode) {
                error_log('[Eppo SDK] Error fetching configuration ' . $e->getMessage());
            } else {
                throw EppoClientException::from($e);
            }
        }
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
     * @param ConfigStore $configStore
     * @param ConfigurationLoader $configurationLoader
     * @param PollerInterface $poller
     * @param LoggerInterface|null $logger
     * @param bool|null $isGracefulMode
     * @param IBanditEvaluator|null $banditEvaluator
     * @param bool|null $throwOnFailedInit
     * @return EppoClient
     * @throws EppoClientInitializationException
     */
    public static function createTestClient(
        ConfigStore $configStore,
        ConfigurationLoader $configurationLoader,
        PollerInterface $poller,
        ?LoggerInterface $logger = null,
        ?bool $isGracefulMode = false,
        ?IBanditEvaluator $banditEvaluator = null,
        ?bool $throwOnFailedInit = true,
    ): EppoClient {
        return self::createAndInitClient(
            $configStore,
            $configurationLoader,
            $poller,
            $logger,
            $isGracefulMode,
            $banditEvaluator,
            throwOnFailedInit: $throwOnFailedInit
        );
    }
}
