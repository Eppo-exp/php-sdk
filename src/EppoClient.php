<?php

namespace Eppo;

use Eppo\Config\SDKData;
use Eppo\DTO\Allocation;
use Eppo\DTO\ExperimentConfiguration;
use Eppo\DTO\Variation;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Logger\LoggerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\SimpleCache\CacheInterface;
use Sarahman\SimpleCache\FileSystemCache;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

class EppoClient
{
    const SECOND_MILLIS = 1000;
    const MINUTE_MILLIS = 60 * self::SECOND_MILLIS;
    const POLL_INTERVAL_MILLIS = 5 * self::MINUTE_MILLIS;
    const JITTER_MILLIS = 30 * self::SECOND_MILLIS;

    // Internal variance data types
    const VARIANT_TYPE_STRING = 'string';
    const VARIANT_TYPE_NUMERIC = 'numeric';
    const VARIANT_TYPE_BOOLEAN = 'boolean';
    const VARIANT_TYPE_JSON = 'json';

    /** @var EppoClient */
    private static $instance;

    /** @var ExperimentConfigurationRequester */
    private $configurationRequester;

    /** @var LoggerInterface */
    private $assignmentLogger;

    /** @var PollerInterface */
    private $poller;

    /**
     * @param ExperimentConfigurationRequester $configurationRequester
     * @param PollerInterface $poller
     * @param LoggerInterface|null $assignmentLogger optional assignment logger. Please check Eppo/LoggerLoggerInterface
     */
    protected function __construct(
        ExperimentConfigurationRequester $configurationRequester,
        PollerInterface $poller,
        ?LoggerInterface $assignmentLogger = null
    ) {
        $this->configurationRequester = $configurationRequester;
        $this->assignmentLogger = $assignmentLogger;
        $this->poller = $poller;
    }

    /**
     * Initializes EppoClient singleton instance.
     *
     * @param string $apiKey
     * @param string $baseUrl
     * @param LoggerInterface|null $assignmentLogger optional assignment logger. Please check Eppo/LoggerLoggerInterface.
     * @param CacheInterface|null $cache optional cache instance. Compatible with psr-16 simple cache. By default, (if nothing passed) EppoClient will use FileSystem cache.
     *
     * @return EppoClient
     * @throws Exception
     */
    public static function init(
        string $apiKey,
        string $baseUrl = '',
        LoggerInterface $assignmentLogger = null,
        CacheInterface $cache = null
    ): EppoClient {
        if (self::$instance === null) {
            $sdkData = new SDKData();
            if (!$cache) {
                $cache = new FileSystemCache(__DIR__ . '/../cache');
            }
            $httpClient = new HttpClient($baseUrl, $apiKey, $sdkData);
            $configStore = new ConfigurationStore($cache);
            $configRequester = new ExperimentConfigurationRequester($httpClient, $configStore);
            $poller = new Poller(
                self::POLL_INTERVAL_MILLIS,
                self::JITTER_MILLIS,
                function () use ($configRequester) {
                    $configRequester->fetchAndStoreConfigurations();
                }
            );

            self::$instance = new self($configRequester, $poller, $assignmentLogger);
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

    /**
     * Get's the assigned string variation for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not a string, null wil be returned.
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getStringAssignment(string $subjectKey, string $experimentKey, array $subjectAttributes = []): ?string {
        $assignmentVariation = $this->getAssignmentVariation($subjectKey, $experimentKey, $subjectAttributes, self::VARIANT_TYPE_STRING);
        return  $assignmentVariation ? strval($assignmentVariation->typedValue) : null;
    }

    /**
     * Get's the assigned boolean variation for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not a boolean, null wil be returned.
     * 
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getBooleanAssignment(string $subjectKey, string $experimentKey, array $subjectAttributes = []): ?bool {
        $assignmentVariation = $this->getAssignmentVariation($subjectKey, $experimentKey, $subjectAttributes, self::VARIANT_TYPE_BOOLEAN);
        return $assignmentVariation ? boolval($assignmentVariation->typedValue) : null;
    }

    /**
     * Get's the assigned numeric variation as a float for the given subject and experiment
     * If there is an issue retrieving the variation or the retrieved variation is not an integer or float (double), null wil be returned.
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getNumericAssignment(string $subjectKey, string $experimentKey, array $subjectAttributes = []): ?float {
        $assignmentVariation = $this->getAssignmentVariation($subjectKey, $experimentKey, $subjectAttributes, self::VARIANT_TYPE_NUMERIC);
        return $assignmentVariation ? doubleval($assignmentVariation->typedValue) : null;
    }

     /**
     * Get's the assigned JSON variation, as parsed by PHP's json_decode, for the given subject and experiment. 
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
    public function getParsedJSONAssignment(string $subjectKey, string $experimentKey, array $subjectAttributes = []): mixed {
        $assignmentVariation = $this->getAssignmentVariation($subjectKey, $experimentKey, $subjectAttributes, self::VARIANT_TYPE_JSON);
        return $assignmentVariation ? $assignmentVariation->typedValue : null;
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
    public function getJSONStringAssignment(string $subjectKey, string $experimentKey, array $subjectAttributes = []): string {
        $parsedJsonValue = $this->getParsedJSONAssignment($subjectKey, $experimentKey, $subjectAttributes);
        return isset($parsedJsonValue) ? json_encode($parsedJsonValue) : null;
    }

    /**
     * Get's the legacy, string-only assignment for the given subject and experiment.
     * If there is an issue retrieving the variation, null wil be returned.
     * 
     * @deprecated in favor of the typed get<type>Assignment methods
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getAssignment(string $subjectKey, string $experimentKey, array $subjectAttributes = []): ?string
    {        
        $assignmentVariation = $this->getAssignmentVariation($subjectKey, $experimentKey, $subjectAttributes);
        return $assignmentVariation ? $assignmentVariation->value : null;
    }

    /**
     * Helper function that gets the Variation DTO for the given subject and experiment. 
     * It will first check to see if the subject has an override. If not, it will compute it's assignment 
     * based on the experiment configuration.
     * 
     * If there is an expected type for the variation value, a type check is performed as well.
     * 
     * @return Variation|null the Variation DTO assigned to the subject, or null if there is no assignment,
     * an error was encountered, or an expected type was provided that didn't match the variation's typed
     *  value.
     */
    private function getAssignmentVariation(string $subjectKey, string $experimentKey, array $subjectAttributes, string $expectedVariationType = null): ?Variation
    {
        Validator::validateNotBlank($subjectKey, 'Invalid argument: subjectKey cannot be blank');
        Validator::validateNotBlank($experimentKey, 'Invalid argument: experimentKey cannot be blank');

        $experimentConfig = $this->configurationRequester->getConfiguration($experimentKey);
        if (!$experimentConfig) {
            return null;
        }

        $overrideVariation = $this->getSubjectOverrideVariation($subjectKey, $experimentConfig);
        
        /** @var Variation $assignedVariation */
        $assignedVariation = null;
        if (!$overrideVariation) {
            $assignedVariation = $this->getSubjectAssignedVariation($subjectKey, $experimentKey, $experimentConfig, $subjectAttributes);
        }

        $resultVariation = $overrideVariation ?: $assignedVariation;

        // Default to logging the untyped string variation value
        // If a typed request is made, we'll adjust to log an appropriate string version of the typed value
        $variationValueToLog = $resultVariation ? $resultVariation->value : null;
                
        // If we have an expected type, then we will perform a type check
        // If the type check does not pass, we'll consider it an invalid assignment and return null
        // We'll also come up with the string value to log for the various types
        $typeMatchesExpected = false;
        if ($expectedVariationType && $resultVariation) {
            // Type check
            if ($expectedVariationType === self::VARIANT_TYPE_STRING) {
                $typeMatchesExpected = gettype($resultVariation->typedValue) === "string";
                $variationValueToLog = $resultVariation->typedValue;
            } else if ($expectedVariationType === self::VARIANT_TYPE_NUMERIC) {
                $typeMatchesExpected = in_array(gettype($resultVariation->typedValue), ["integer", "double"]);
                $variationValueToLog = strval($resultVariation->typedValue);
            } else if ($expectedVariationType === self::VARIANT_TYPE_BOOLEAN) {
                $typeMatchesExpected = gettype($resultVariation->typedValue) === "boolean";
                $variationValueToLog = $resultVariation->typedValue ? "true" : "false";
            } else if ($expectedVariationType === self::VARIANT_TYPE_JSON) {
                $typeMatchesExpected = true; // If the variation was constructed, then the JSON parsed successfully
                $variationValueToLog = json_encode($resultVariation->typedValue);
            }
        }

        if ($expectedVariationType && !$typeMatchesExpected) {
            // Typed value is unexpected type
            return null;
        }

        if ($assignedVariation && $this->assignmentLogger) {
            // If an assignment was made, log it. (Note: we do not log overrides)
            try {
                $this->assignmentLogger->logAssignment(
                    $experimentKey,
                    $variationValueToLog,
                    $subjectKey,
                    time(),
                    $subjectAttributes
                );
            } catch (Exception $exception) {
                error_log('[Eppo SDK] Error logging assignment event: ' . $exception->getMessage());
            }
        }

        return $resultVariation;
    }

        /**
     * Private helper function that creates a Variation DTO to represent an override assignment for
     * the given subject and experiment, if any. If there is no override, null will be returned.
     */
    private function getSubjectOverrideVariation(string $subjectKey, ExperimentConfiguration $experimentConfig): ?Variation
    {
        $subjectHash = hash('md5', $subjectKey);
        $overrides = $experimentConfig->getOverrides();
        $typedOverrides = $experimentConfig->getTypedOverrides();

        $overrideVariation = null;

        if (isset($overrides[$subjectHash]) || isset($typedOverrides[$subjectHash])) {
            // We have an override for this subject
            $overrideVariation = new Variation();
            $overrideVariation->value = $overrides[$subjectHash] ?? null;
            $overrideVariation->typedValue = $typedOverrides[$subjectHash] ?? null;
        }
      
        return $overrideVariation;
    }

    /**
     * Private helper function that retrieves the Variation DTO for assigning the given subject a variation
     * for the given experiment. If the experiment is not enabled, there is no appropriate assignment, or
     * an error is encountered, null will be returned.
     */
    private function getSubjectAssignedVariation(string $subjectKey, string $experimentKey, ExperimentConfiguration $experimentConfig, array $subjectAttributes): ?Variation {
        
        // Check for disabled flag.
        // TODO: should we do this check before even checking overrides?
        if (!$experimentConfig->isEnabled()) {
            return null;
        }

        // Attempt to match a rule from the list.
        $matchedRule = RuleEvaluator::findMatchingRule($subjectAttributes, $experimentConfig->getRules());
        if (!$matchedRule) {
            return null;
        }

        /** @var Allocation $allocation */
        $allocation = $experimentConfig->getAllocations()[$matchedRule->allocationKey] ?? null;

        if (!$this->isInExperimentSample($subjectKey, $experimentKey, $experimentConfig, $allocation)) {
            return null;
        }

        // Compute variation for subject.
        $subjectShards = $experimentConfig->getSubjectShards();
        $variations = $allocation->variations;

        $shard = Shard::getShard('assignment-' . $subjectKey . '-' . $experimentKey, $subjectShards);

        $assignedVariation = null;

        /** @var Variation $variation */
        foreach ($variations as $variation) {
            if (Shard::isShardInRange($shard, $variation->shardRange)) {
                $assignedVariation = $variation;
                break;
            }
        }

        return $assignedVariation;
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

    /**
     * This checks whether the subject is included in the experiment sample.
     * It is used to determine whether the subject should be assigned to a variant.
     * Given a hash function output (bucket), check whether the bucket is between 0 and exposure_percent * total_buckets.
     *
     * @param string $subjectKey
     * @param string $experimentKey
     * @param ExperimentConfiguration $experimentConfiguration
     * @param Allocation $allocation
     *
     * @return bool
     */
    private function isInExperimentSample(
        string $subjectKey,
        string $experimentKey,
        ExperimentConfiguration $experimentConfiguration,
        Allocation $allocation
    ): bool {
        $subjectShards = $experimentConfiguration->getSubjectShards();
        $percentExposure = $allocation->percentExposure;
        $shard = Shard::getShard('exposure-' . $subjectKey . '-' . $experimentKey, $subjectShards);

        return $shard <= $percentExposure * $subjectShards;
    }

    /**
     * Only used for unit-tests.
     * For production use please use only singleton instance.
     *
     * @param ExperimentConfigurationRequester $experimentConfigurationRequester
     * @param PollerInterface $poller
     * @param LoggerInterface|null $logger
     *
     * @return EppoClient
     */
    public static function createTestClient(
        ExperimentConfigurationRequester $experimentConfigurationRequester,
        PollerInterface $poller,
        ?LoggerInterface $logger = null
    ): EppoClient {
        return new EppoClient($experimentConfigurationRequester, $poller, $logger);
    }
}
