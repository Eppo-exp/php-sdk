<?php

namespace Eppo;

use Eppo\Config\SDKData;
use Eppo\DTO\Allocation;
use Eppo\DTO\ExperimentConfiguration;
use Eppo\DTO\Rule;
use Eppo\DTO\Variation;
use Eppo\DTO\VariationType;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Logger\LoggerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;
use Sarahman\SimpleCache\FileSystemCache;

class EppoClient
{
    const SECOND_MILLIS = 1000;
    const MINUTE_MILLIS = 60 * self::SECOND_MILLIS;
    const POLL_INTERVAL_MILLIS = 5 * self::MINUTE_MILLIS;
    const JITTER_MILLIS = 30 * self::SECOND_MILLIS;

    private static EppoClient $instance;
    private RuleEvaluator $evaluator;


    /**
     * @param FlagConfigurationLoader $configurationRequester
     * @param PollerInterface $poller
     * @param LoggerInterface|null $assignmentLogger optional assignment logger. Please check Eppo/LoggerLoggerInterface
     * @param bool|null $isGracefulMode
     */
    protected function __construct(
        private readonly FlagConfigurationLoader $configurationRequester,
        private readonly PollerInterface $poller,
        private readonly ?LoggerInterface $assignmentLogger = null,
        private readonly ?bool $isGracefulMode = true
    )
    {
        $this->evaluator = new RuleEvaluator();
    }

    /**
     * Initializes EppoClient singleton instance.
     *
     * @param string $apiKey
     * @param string $baseUrl
     * @param LoggerInterface|null $assignmentLogger optional assignment logger. Please check Eppo/LoggerLoggerInterface.
     * @param CacheInterface|null $cache optional cache instance. Compatible with psr-16 simple cache. By default, (if nothing passed) EppoClient will use FileSystem cache.
     * @param ClientInterface|null $httpClient optional PSR-18 ClientInterface. If nothing is passed, EppoClient will use Discovery to locate a suitable implementation in the project.
     * @param RequestFactoryInterface|null $requestFactory optional PSR-17 Request Factory implementation. If none is provided, EppoClient will use Discovery
     * @param bool|null $isGracefulMode
     * @return EppoClient
     * @throws Exception
     */
    public static function init(
        string $apiKey,
        string $baseUrl = '',
        LoggerInterface $assignmentLogger = null,
        CacheInterface $cache = null,
        ClientInterface $httpClient = null,
        RequestFactoryInterface $requestFactory = null,
        ?bool $isGracefulMode = true
    ): EppoClient
    {
        if (self::$instance === null) {
            // Get SDK metadata to pass as params in the http client.
            $sdkData = new SDKData();
            $sdkParams = ['sdkVersion' => $sdkData->getSdkVersion(),
                'sdkName' => $sdkData->getSdkName()];

            if (!$cache) {
                $cache = new FileSystemCache(__DIR__ . '/../cache');
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

            $configLoader = new FlagConfigurationLoader($apiWrapper, $configStore);
            $poller = new Poller(
                self::POLL_INTERVAL_MILLIS,
                self::JITTER_MILLIS,
                function () use ($configLoader) {
                    $configLoader->fetchAndStoreConfigurations();
                }
            );

            self::$instance = new self($configLoader, $poller, $assignmentLogger, $isGracefulMode);
        }

        return self::$instance;
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


    private function getTypedAssignment(VariationType $valueType, string $subjectKey, string $flagKey, array $subjectAttributes = []): mixed
    {
        try {
            $assignmentVariation = $this->getAssignmentDetail($subjectKey, $flagKey, $subjectAttributes, $valueType);
            if ($assignmentVariation === null) {
                return null;
            }
            switch ($valueType) {
                case VariationType::STRING:
                    return $assignmentVariation->value;
                case VariationType::NUMERIC:
                    return doubleval($assignmentVariation->value);
                case VariationType::BOOLEAN:
                    return boolval($assignmentVariation->value);
                case VariationType::JSON:
                    return $assignmentVariation->value;
            }
            return null;
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    /**
     * Gets the assigned string variation for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not a string, null wil be returned.
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getStringAssignment(string $subjectKey, string $flagKey, array $subjectAttributes = []): ?string
    {
        return $this->getTypedAssignment(VariationType::STRING, $subjectKey, $flagKey, $subjectAttributes);
    }

    /**
     * Gets the assigned boolean variation for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not a boolean, null wil be returned.
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getBooleanAssignment(string $subjectKey, string $flagKey, array $subjectAttributes = []): ?bool
    {
        return $this->getTypedAssignment(VariationType::BOOLEAN, $subjectKey, $flagKey, $subjectAttributes);
    }

    /**
     * Gets the assigned numeric variation as a float for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not an integer or float (double), null wil be returned.
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getNumericAssignment(string $subjectKey, string $flagKey, array $subjectAttributes = []): ?float
    {
        return $this->getTypedAssignment(VariationType::NUMERIC, $subjectKey, $flagKey, $subjectAttributes);
    }

    /**
     * Gets the assigned JSON variation, as parsed by PHP's json_decode, for the given subject and experiment.
     * If there is an issue retrieving the variation or the retrieved variation is not valid JSON, null wil be returned.
     *
     * @return mixed the parsed variation JSON
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getParsedJSONAssignment(string $subjectKey, string $flagKey, array $subjectAttributes = []): mixed
    {
        return $this->getTypedAssignment(VariationType::JSON, $subjectKey, $flagKey, $subjectAttributes);
    }

    /**
     * Get's the assigned JSON variation, represented as JSON string, for the given subject and experiment.
     * If there is an issue retrieving the variation or the retrieved variation is not valid JSON, null wil be returned.
     *
     * @return string|null the parsed variation JSON as a string
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getJSONStringAssignment(string $subjectKey, string $flagKey, array $subjectAttributes = []): ?string
    {
        try {
            $parsedJsonValue = $this->getParsedJSONAssignment($subjectKey, $flagKey, $subjectAttributes);
            return isset($parsedJsonValue) ? json_encode($parsedJsonValue) : null;
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }

    /**
     * Get's the legacy, string-only assignment for the given subject and experiment.
     * If there is an issue retrieving the variation, null wil be returned.
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     * @deprecated in favor of the typed get<type>Assignment methods
     *
     */
    public function getAssignment(string $subjectKey, string $flagKey, array $subjectAttributes = []): ?string
    {
        try {
            $assignmentVariation = $this->getAssignmentDetail($subjectKey, $flagKey, $subjectAttributes);
            return $assignmentVariation ? $assignmentVariation->value : null;
        } catch (Exception $exception) {
            return $this->handleException($exception);
        }
    }


    /**
     * Maps a subject to a Variation for the given flag.
     *
     * If there is an expected type for the variation value, a type check is performed as well.
     *
     * Returns null if the subject has no allocation for the flag.
     *
     * @param string $subjectKey an identifier for the experiment. Ex: a user ID
     * @param string $flagKey a feature flag identifier
     * @param array $subjectAttributes optional attributes to use in the evaluation of experiment targeting rules. These attributes are also included in the loggin callback.
     * @param string|null $expectedVariationType
     * @return Variation|null the Variation DTO assigned to the subject, or null if there is no assignment,
     * an error was encountered, or an expected type was provided that didn't match the variation's typed
     * value.
     * @throws GuzzleException
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     * @throws ClientExceptionInterface
     */
    private function getAssignmentDetail(string $subjectKey, string $flagKey, array $subjectAttributes = [], VariationType $expectedVariationType = null): ?Variation
    {
        Validator::validateNotBlank($subjectKey, 'Invalid argument: subjectKey cannot be blank');
        Validator::validateNotBlank($flagKey, 'Invalid argument: flagKey cannot be blank');

        $flag = $this->configurationRequester->getConfiguration($flagKey);
        if (!$flag) {
            syslog(LOG_WARNING, "[EPPO SDK] No assigned variation; flag not found ${flagKey}");
            return null;
        }

        $evaluationResult = $this->evaluator->evaluateFlag($flag, $subjectKey, $subjectAttributes);
        $computedVariation = $evaluationResult?->variation ?? null;


        // If there is an assignment and the expected type has been expressed, do a type check and log an error if they don't match.
        if ($computedVariation && $expectedVariationType && !$this->checkExpectedType($expectedVariationType, $computedVariation->value)) {
            $actualType = gettype($computedVariation->value);
            syslog(LOG_ERR, "[EPPO SDK] Variation does not have the expected type, ${$expectedVariationType}; found ${$actualType}");
            return null;
        }

        if (!$flag->enabled) {
            syslog(LOG_INFO, "[EPPO SDK] No assigned variation; flag is disabled.");
            return null;
        }

        // If an assignment was made, log it using the user-provided logger callback.
        if ($computedVariation && $this->assignmentLogger && $evaluationResult->doLog) {
            try {
                $allocationKey = $evaluationResult->allocationKey;
                $variationValueToLog = $this->getLogFriendlyValue($computedVariation, $expectedVariationType);
                $experimentKey = "$flagKey-$allocationKey";
                $this->assignmentLogger->logAssignment(
                    $experimentKey,
                    $variationValueToLog,
                    $subjectKey,
                    time(),
                    $subjectAttributes,
                    $allocationKey,
                    $flagKey
                );
            } catch (Exception $exception) {
                error_log('[Eppo SDK] Error logging assignment event: ' . $exception->getMessage());
            }
        }

        return $computedVariation;
    }


    private function checkExpectedType(VariationType $expectedVariationType, $typedValue) : bool
    {
        return (
            ($expectedVariationType == VariationType::STRING && gettype($typedValue) === "string") ||
            ($expectedVariationType == VariationType::NUMERIC && in_array(gettype($typedValue), ["integer", "double"])) ||
            ($expectedVariationType == VariationType::BOOLEAN && gettype($typedValue) === "boolean") ||
            ($expectedVariationType == VariationType::JSON)); // JSON type check un-necessary here.
    }

    /**
     * Renders the flag's computed value in a format friendly to loggers.
     * If no valueType is provided, the variation's unparsed string value is returned
     */
    private function getLogFriendlyValue(Variation $variation, VariationType $valueType = null): string
    {
        if ($valueType === null || $valueType === VariationType::STRING) {
            return $variation->value;
        } elseif ($valueType === VariationType::NUMERIC) {
            return strval($variation->value);
        } elseif ($valueType === VariationType::BOOLEAN || $valueType === VariationType::JSON) {
            // json_encode renders booleans in human readable "true" and "false".
            return json_encode($variation->value);
        } else {
            $typeString = $valueType->value;
            syslog(LOG_WARNING,
                "[EPPO SDK] Unexpected value type $typeString; returning unparsed (raw string) value");
            return $variation->value;
        }

    }

    public function startPolling()
    {
        $this->poller->start();
    }

    public function stopPolling()
    {
        $this->poller->stop();
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone()
    {
    }

    private function handleException(Exception $exception): mixed
    {
        if ($this->isGracefulMode) {
            error_log('[Eppo SDK] Error getting assignment: ' . $exception->getMessage());
            return null;
        }
        throw $exception;
    }

    /**
     * Only used for unit-tests.
     * For production use please use only singleton instance.
     *
     * @param FlagConfigurationLoader $configurationLoader
     * @param PollerInterface $poller
     * @param LoggerInterface|null $logger
     * @param bool|null $isGracefulMode
     * @return EppoClient
     */
    public static function createTestClient(
        FlagConfigurationLoader $configurationLoader,
        PollerInterface $poller,
        ?LoggerInterface $logger = null,
        ?bool $isGracefulMode = true
    ): EppoClient
    {
        return new EppoClient($configurationLoader, $poller, $logger, $isGracefulMode);
    }
}
