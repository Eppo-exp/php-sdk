<?php

namespace Eppo\Config;

use Eppo\API\APIRequestWrapper;
use Eppo\Bandits\BanditReferenceIndexer;
use Eppo\Bandits\IBanditReferenceIndexer;
use Eppo\Bandits\IBandits;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\BanditReference;
use Eppo\DTO\Flag;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidConfigurationException;
use Eppo\Flags\IFlags;
use Eppo\UFCParser;

class ConfigurationLoader implements IFlags, IBandits
{
    private const KEY_BANDIT_TIMESTAMP = "banditTimestamp";
    private const KEY_LOADED_BANDIT_VERSIONS = 'banditModelVersions';
    private UFCParser $parser;

    private const KEY_FLAG_TIMESTAMP = "flagTimestamp";
    private const KEY_FLAG_ETAG = "flagETag";

    public function __construct(
        private readonly APIRequestWrapper $apiRequestWrapper,
        private readonly IConfigurationStore $configurationStore,
        private readonly int $cacheAgeLimit = 30,
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
        $flagCacheAge = $this->getCacheAgeSeconds();
        if ($flagCacheAge === -1 || $flagCacheAge >= $this->cacheAgeLimit) {
            $flagETag = $this->configurationStore->getMetadata(self::KEY_FLAG_ETAG);
            $this->fetchAndStoreConfigurations($flagETag);
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
            // Decode and set the data.
            $responseData = json_decode($response->body, true);
            if (!$responseData) {
                syslog(LOG_WARNING, "[Eppo SDK] Empty or invalid response from the configuration server.");
                return;
            }

            $inflated = array_map(fn($object) => $this->parser->parseFlag($object), $responseData['flags']);

            // Create a handy helper class from the `banditReferences` to help connect flags to bandits.
            if (isset($responseData['banditReferences'])) {
                $banditReferences = array_map(
                    function ($json) {
                        return BanditReference::fromJson($json);
                    },
                    $responseData['banditReferences']
                );
                $indexer = BanditReferenceIndexer::from($banditReferences);
            } else {
                syslog(LOG_WARNING, "[EPPO SDK] No bandit-flag variations found in UFC response.");
                $indexer = BanditReferenceIndexer::empty();
            }

            $this->configurationStore->setUnifiedFlagConfiguration($inflated, $indexer);

            // Only load bandits if there are any referenced by the flags.
            if ($indexer->hasBandits()) {
                // TODO: Use the indexer to see what bandit models are needed and whether they've already been loaded
                // to determine whether to make a fetch call here.
                $this->fetchBanditsAsRequired($indexer);
            }
        }

        // Store metadata for next time.
        $this->configurationStore->setMetadata(self::KEY_FLAG_TIMESTAMP, time());
        $this->configurationStore->setMetadata(self::KEY_FLAG_ETAG, $response->ETag);
    }

    private function getCacheAgeSeconds(): int
    {
        $timestamp = $this->configurationStore->getMetadata(self::KEY_FLAG_TIMESTAMP);
        if ($timestamp != null) {
            return time() - $timestamp;
        }
        return -1;
    }

    public function getBanditReferenceIndexer(): IBanditReferenceIndexer
    {
        return $this->configurationStore->getBanditReferenceIndexer();
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidConfigurationException
     */
    private function fetchAndStoreBandits(): void
    {
        $banditModelResponse = json_decode($this->apiRequestWrapper->getBandits()->body, true);
        if (!$banditModelResponse || !isset($banditModelResponse['bandits'])) {
            syslog(LOG_WARNING, "[Eppo SDK] Empty or invalid response from the configuration server.");
            $bandits = [];
        } else {
            $bandits = array_map(fn($json) => Bandit::fromJson($json), $banditModelResponse['bandits']);
        }
        $banditModelVersions = array_map(fn($bandit)=> $bandit->modelVersion, $bandits);

        $this->configurationStore->setBandits($bandits);
        $this->configurationStore->setMetadata(self::KEY_LOADED_BANDIT_VERSIONS, $banditModelVersions);
        $this->configurationStore->setMetadata(self::KEY_BANDIT_TIMESTAMP, time());
    }

    public function getBandit(string $banditKey): ?Bandit
    {
        return $this->configurationStore->getBandit($banditKey);
    }

    /**
     * Loads bandits unless `optimizedBanditLoading` is `true` in which case, currently loaded bandit models are
     * compared to those required by flags to determine whether to (re)load bandit models.
     *
     * @param IBanditReferenceIndexer $indexer
     * @return void
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidConfigurationException
     */
    public function fetchBanditsAsRequired(IBanditReferenceIndexer $indexer): void
    {
        $needToFetch = false;
        if ($this->optimizedBanditLoading) {
            // Get the currently loaded bandits to determine if they satisfy what's required by the flags
            $currentlyLoadedBanditModels = $this->configurationStore->getMetadata(
                self::KEY_LOADED_BANDIT_VERSIONS
            );
            $references = $indexer->getBanditModelVersionReferences();

            $needToFetch = !self::satisfiesRequiredModels($currentlyLoadedBanditModels, $references);
        } else {
            $needToFetch = true;
        }

        if ($needToFetch) {
            $this->fetchAndStoreBandits();
        }
    }

    /**
     * @param array<string, string> $loaded
     * @param array<string, string> $required
     * @return bool
     */
    private static function satisfiesRequiredModels(array $loaded, array $required): bool
    {
        foreach ($required as $banditKey => $modelVersion) {
            if (!isset($loaded[$banditKey]) || $modelVersion != $loaded[$banditKey]) {
                return false;
            }
        }
        return true;
    }
}
