<?php

namespace Eppo;

use Eppo\Config\SDKData;
use Eppo\DTO\Allocation;
use Eppo\DTO\ExperimentConfiguration;
use Eppo\DTO\Variation;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Exception\InvalidApiKeyException;
use GuzzleHttp\Exception\GuzzleException;
use Sarahman\SimpleCache\FileSystemCache;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

class EppoClient
{
    /** @var EppoClient */
    private static $instance;

    /** @var ExperimentConfigurationRequester */
    private $configurationRequester;

    /**
     * The Singleton's constructor should always be private to prevent direct
     * construction calls with the `new` operator.
     */
    protected function __construct(ExperimentConfigurationRequester $configurationRequester)
    {
        $this->configurationRequester = $configurationRequester;
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone()
    {
    }

    /**
     * @param string $apiKey
     * @param string $baseUrl
     * @return EppoClient
     */
    public static function init(string $apiKey, string $baseUrl = ''): EppoClient
    {
        if (self::$instance === null) {
            $sdkData = new SDKData();
            $cache = new FileSystemCache();
            $httpClient = new HttpClient($baseUrl, $apiKey, $sdkData);
            $configStore = new ConfigurationStore($cache);
            $configRequester = new ExperimentConfigurationRequester($httpClient, $configStore);

            self::$instance = new self($configRequester);
        }

        return self::$instance;
    }

    public static function getInstance(): EppoClient
    {
        return self::$instance;
    }

    /**
     * @param $subjectKey
     * @param $experimentKey
     * @param array $subjectAttributes
     * @return string|null
     * @throws Exception\HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws GuzzleException
     * @throws SimpleCacheInvalidArgumentException
     */
    public function getAssignment($subjectKey, $experimentKey, array $subjectAttributes = []): ?string
    {
        Validator::validateNotBlank($subjectKey, 'Invalid argument: subjectKey cannot be blank');
        Validator::validateNotBlank($experimentKey, 'Invalid argument: experimentKey cannot be blank');

        $experimentConfig = $this->configurationRequester->getConfiguration($experimentKey);
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

        try {
            // $assignmentLogger->logAssignment();
        } catch (\Exception $exception) {
            error_log('[Eppo SDK] Error logging assignment event: ' . $exception->getMessage());
        }

        return $assignedVariation;
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

    private function getSubjectVariationOverride(string $subjectKey, ExperimentConfiguration $experimentConfig): ?string
    {
        $subjectHash = md5($subjectKey);
        $overrides = $experimentConfig->getOverrides();
        if (count($overrides) > 0) {
            return $experimentConfig->getOverrides()[$subjectHash];
        }

        return null;
    }
}