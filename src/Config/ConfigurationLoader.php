<?php

namespace Eppo\Config;

use Eppo\API\APIRequestWrapper;
use Eppo\Bandits\BanditReferenceIndexer;
use Eppo\Bandits\IBanditReferenceIndexer;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\BanditReference;
use Eppo\DTO\Flag;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidConfigurationException;
use Eppo\UFCParser;

class ConfigurationLoader
{
    private UFCParser $parser;

    public function __construct(
        private readonly APIRequestWrapper $apiRequestWrapper,
        public readonly ConfigurationStore $configurationStore,
        private readonly int $cacheAgeLimitMillis = 30 * 1000,
        private readonly bool $optimizedBanditLoading = false
    ) {
        $this->parser = new UFCParser();
    }

    /**
     * @throws InvalidApiKeyException
     * @throws HttpRequestException
     * @throws InvalidConfigurationException
     */
    public function getFlag(string $key): ?Flag
    {
        $this->reloadConfigurationIfExpired();
        return $this->configurationStore->getFlag($key);
    }

    /**
     * @param string $flagKey
     * @param string $variation
     * @return string|null
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidConfigurationException
     */
    public function getBanditByVariation(string $flagKey, string $variation): ?string
    {
        $this->reloadConfigurationIfExpired();
        return $this->configurationStore->getBanditReferenceIndexer()->getBanditByVariation($flagKey, $variation);
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidConfigurationException
     */
    public function reloadConfigurationIfExpired(): void
    {
        $flagCacheAge = $this->getCacheAgeInMillis();
        if ($flagCacheAge < 0 || $flagCacheAge >= ($this->cacheAgeLimitMillis)) {
            $this->reloadConfiguration();
        }
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidConfigurationException
     */
    public function fetchAndStoreConfigurations(?string $flagETag): void
    {
        $response = $this->apiRequestWrapper->getUFC($flagETag);
        if ($response->isModified) {
            $responseData = json_decode($response->body, true);
            if (!$responseData) {
                syslog(LOG_WARNING, "[Eppo SDK] Empty or invalid response from the configuration server.");
                return;
            }

            $flags = array_map(fn($object) => $this->parser->parseFlag($object), $responseData['flags']);
            $indexer = $this->createBanditReferenceIndexer($responseData);
            $bandits = $this->fetchBanditsIfNeeded($indexer);

            $configuration = new Configuration(
                flags: $flags,
                bandits: $bandits,
                banditReferenceIndexer: $indexer,
                eTag: $response->ETag,
                fetchedAt: $this->millitime()
            );

            $this->configurationStore->setConfiguration($configuration);
        }
    }

    public function getCacheAgeInMillis(): int
    {
        $config = $this->configurationStore->getConfiguration();
        if ($config === null) {
            return -1;
        }
        return $this->millitime() - $config->fetchedAt;
    }

    public function getBandit(string $banditBanditKey): ?Bandit
    {
        return $this->configurationStore->getBandit($banditBanditKey);
    }


    /**
     * @return void
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidConfigurationException
     */
    public function reloadConfiguration(): void
    {
        $flagETag = $this->configurationStore->getConfiguration()->eTag;
        $this->fetchAndStoreConfigurations($flagETag);
    }

    private function millitime(): int
    {
        return intval(microtime(true) * 1000);
    }

    private function createBanditReferenceIndexer(array $responseData): IBanditReferenceIndexer
    {
        if (isset($responseData['banditReferences'])) {
            $banditReferences = array_map(
                function ($json) {
                    return BanditReference::fromJson($json);
                },
                $responseData['banditReferences']
            );
            return BanditReferenceIndexer::from($banditReferences);
        } else {
            syslog(LOG_WARNING, "[EPPO SDK] No bandit-flag variations found in UFC response.");
            return BanditReferenceIndexer::empty();
        }
    }

    private function fetchBanditsIfNeeded(IBanditReferenceIndexer $indexer): array
    {
        if (!$indexer->hasBandits()) {
            return [];
        }

        $shouldFetchBandts = ~$this->optimizedBanditLoading;


        if (!$shouldFetchBandts) {
            $requiredModelKeys = $indexer->getBanditModelKeys();

            // Get currently loaded models from the existing configuration
            $currentConfig = $this->configurationStore->getConfiguration();
            $currentlyLoadedBanditModels = [];
            if ($currentConfig && !empty($currentConfig->bandits)) {
                $currentlyLoadedBanditModels = array_map(
                    fn($bandit) => $bandit->modelVersion,
                    $currentConfig->bandits
                );
            }

            // Check if we need to fetch new bandits
            if (array_diff($requiredModelKeys, $currentlyLoadedBanditModels)) {
                $shouldFetchBandts = true;
            }
        }
        if ($shouldFetchBandts) {
            $banditModelResponse = json_decode($this->apiRequestWrapper->getBandits()->body, true);
            if (!$banditModelResponse || !isset($banditModelResponse['bandits'])) {
                syslog(LOG_WARNING, "[Eppo SDK] Empty or invalid response from the configuration server.");
                return [];
            }

            return array_map(fn($json) => Bandit::fromJson($json), $banditModelResponse['bandits']);
        }

        // If we didn't fetch new bandits, return the current bandits
        return $currentConfig?->bandits ?? [];
    }
}
