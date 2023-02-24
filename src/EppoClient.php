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

    /** @var EppoClient */
    private static $instance;

    /** @var ExperimentConfigurationRequester */
    private $configurationRequester;

    /** @var LoggerInterface */
    private $assignmentLogger;

    /** @var IPoller */
    private $poller;

    /**
     * @param ExperimentConfigurationRequester $configurationRequester
     * @param IPoller $poller
     * @param LoggerInterface|null $assignmentLogger optional assignment logger. Please check Eppo/LoggerLoggerInterface
     */
    protected function __construct(
        ExperimentConfigurationRequester $configurationRequester,
        IPoller $poller,
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
     * @param string $subjectKey
     * @param string $experimentKey
     * @param array $subjectAttributes
     *
     * @return string|null
     *
     * @throws HttpRequestException
     * @throws GuzzleException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getAssignment(string $subjectKey, string $experimentKey, array $subjectAttributes = []): ?string
    {
        Validator::validateNotBlank($subjectKey, 'Invalid argument: subjectKey cannot be blank');
        Validator::validateNotBlank($experimentKey, 'Invalid argument: experimentKey cannot be blank');

        $experimentConfig = $this->configurationRequester->getConfiguration($experimentKey);
        if (!$experimentConfig) {
            return null;
        }

        $allowListOverride = $this->getSubjectVariationOverride($subjectKey, $experimentConfig);
        if ($allowListOverride) {
            return $allowListOverride;
        }

        // Check for disabled flag.
        if (!$experimentConfig->isEnabled()) {
            return null;
        }

        // Attempt to match a rule from the list.
        $matchedRule = RuleEvaluator::findMatchingRule($subjectAttributes, $experimentConfig->getRules());
        if (!$matchedRule) {
            return null;
        }

        /** @var Allocation $allocation */
        $allocation = $experimentConfig->getAllocations()[$matchedRule->allocationKey];

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
                $assignedVariation = $variation->value;
                break;
            }
        }

        if ($this->assignmentLogger) {
            try {
                $this->assignmentLogger->logAssignment(
                    $experimentKey,
                    $assignedVariation,
                    $subjectKey,
                    time(),
                    $subjectAttributes
                );
            } catch (Exception $exception) {
                error_log('[Eppo SDK] Error logging assignment event: ' . $exception->getMessage());
            }
        }

        return $assignedVariation;
    }

    /**
     * Only used for unit-tests.
     * For production use please use only singleton instance.
     *
     * @param ExperimentConfigurationRequester $experimentConfigurationRequester
     * @param IPoller $poller
     * @param LoggerInterface|null $logger
     *
     * @return EppoClient
     */
    public static function createTestClient(
        ExperimentConfigurationRequester $experimentConfigurationRequester,
        IPoller $poller,
        ?LoggerInterface $logger = null
    ): EppoClient {
        return new EppoClient($experimentConfigurationRequester, $poller, $logger);
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
     * @param string $subjectKey
     * @param ExperimentConfiguration $experimentConfig
     *
     * @return string|null
     */
    private function getSubjectVariationOverride(string $subjectKey, ExperimentConfiguration $experimentConfig): ?string
    {
        $subjectHash = hash('md5', $subjectKey);
        $overrides = $experimentConfig->getOverrides();
        if (count($overrides) > 0) {
            return $experimentConfig->getOverrides()[$subjectHash];
        }

        return null;
    }
}