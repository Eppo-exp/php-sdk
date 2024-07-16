<?php

namespace Eppo\Config;

use Eppo\API\APIRequestWrapper;
use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Bandits\IBandits;
use Eppo\Bandits\IBanditVariationIndexer;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Bandit\BanditVariation;
use Eppo\DTO\Flag;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidConfigurationException;
use Eppo\Flags\IFlags;
use Eppo\UFCParser;

class ConfigurationLoader implements IFlags, IBandits
{
    private const BANDIT_TIMESTAMP = "banditTimestamp";
    private UFCParser $parser;

    private const FLAG_TIMESTAMP = "flagTimestamp";
    private const FLAG_ETAG = "flagETag";

    public function __construct(
        private readonly APIRequestWrapper $apiRequestWrapper,
        private readonly IConfigurationStore $configurationStore,
        private readonly int $cacheAgeLimit = 30
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
        return $this->configurationStore->getBanditVariations()->getBanditByVariation($flagKey, $variation);
    }

    /**
     * @param string $flagKey
     * @return bool
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws HttpRequestException
     * @throws InvalidConfigurationException
     */
    public function isBanditFlag(string $flagKey): bool
    {
        $this->reloadConfigurationIfExpired();
        return $this->configurationStore->getBanditVariations()->isBanditFlag($flagKey);
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
            $flagETag = $this->configurationStore->getMetadata(self::FLAG_ETAG);
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
            $variations = [];
            if (isset($responseData['bandits'])) {
                $variations = array_map(
                    fn($listOfVariations) => array_map(
                        fn($json) => BanditVariation::fromJson($json),
                        $listOfVariations
                    ),
                    $responseData['bandits']
                );
            } else {
                syslog(LOG_WARNING, "[EPPO SDK] No bandit-flag variations found in UFC response.");
            }

            $indexer = BanditVariationIndexer::from($variations);
            $this->configurationStore->setUnifiedFlagConfiguration($inflated, $indexer);

            // Only load bandits if there are any referenced by the flags.
            if ($indexer->hasBandits()) {
                $this->fetchAndStoreBandits();
            }
        }

        // Store metadata for next time.
        $this->configurationStore->setMetadata(self::FLAG_TIMESTAMP, time());
        $this->configurationStore->setMetadata(self::FLAG_ETAG, $response->ETag);
    }

    private function getCacheAgeSeconds(): int
    {
        $timestamp = $this->configurationStore->getMetadata(self::FLAG_TIMESTAMP);
        if ($timestamp != null) {
            return time() - $timestamp;
        }
        return -1;
    }

    public function getBanditVariations(): IBanditVariationIndexer
    {
        return $this->configurationStore->getBanditVariations();
    }

    private function fetchAndStoreBandits(): void
    {
        $banditModelResponse = json_decode($this->apiRequestWrapper->getBandits()->body, true);
        if (!$banditModelResponse || !isset($banditModelResponse['bandits'])) {
            syslog(LOG_WARNING, "[Eppo SDK] Empty or invalid response from the configuration server.");
            $bandits = [];
        } else {
            $bandits = array_map(fn($json) => Bandit::fromJson($json), $banditModelResponse['bandits']);
        }

        $this->configurationStore->setBandits($bandits);
        $this->configurationStore->setMetadata(self::BANDIT_TIMESTAMP, time());
    }

    public function getBandit(string $banditKey): ?Bandit
    {
        return $this->configurationStore->getBandit($banditKey);
    }
}
