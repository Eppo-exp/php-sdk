<?php

namespace Eppo\Config;

use Eppo\API\APIRequestWrapper;
use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Bandits\IBanditVariationIndexer;
use Eppo\DTO\Bandit\BanditVariation;
use Eppo\DTO\Flag;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidConfigurationException;
use Eppo\IFlags;
use Eppo\UFCParser;

class ConfigurationLoader implements IFlags, IBanditVariationIndexer
{
    private UFCParser $parser;

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
     */
    public function getFlag(string $key): ?Flag
    {
        $this->reloadConfigurationIfExpired();
        return $this->configurationStore->getFlag($key);
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     */
    public function getBanditByVariation($flagKey, $variation): ?string
    {
        $this->reloadConfigurationIfExpired();
        return $this->configurationStore->getBanditVariations()->getBanditByVariation($flagKey, $variation);
    }

    /**
     * @throws InvalidApiKeyException
     * @throws HttpRequestException
     */
    public function isBanditFlag($flagKey): bool
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
        $cacheMeta = $this->configurationStore->getFlagCacheMetadata();
        // A null metadata indicates the data has not yet been fetched. Otherwise, check the age.
        if ($cacheMeta == null  || $cacheMeta->getCacheAgeSeconds() >= $this->cacheAgeLimit) {
            $this->fetchAndStoreConfigurations($cacheMeta?->ETag ?? null);
        }
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidConfigurationException
     */
    public function fetchAndStoreConfigurations(?string $flagETag): void
    {
        $response = $this->apiRequestWrapper->get($flagETag);
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

            $indexer = new BanditVariationIndexer($variations);
            $this->configurationStore->setFlagConfigurations($inflated, $indexer);
        }

        // Store metadata for next time.
        $this->configurationStore->setFlagCacheMetadata($response->meta);
    }
}
