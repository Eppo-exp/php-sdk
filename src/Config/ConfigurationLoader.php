<?php

namespace Eppo\Config;

use Eppo\APIRequestWrapper;
use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Bandits\IBanditVariationIndexer;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Bandit\BanditVariation;
use Eppo\DTO\Flag;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidConfigurationException;
use Eppo\Flags\IFlags;
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
        $cacheAge = $this->configurationStore->getFlagCacheAgeSeconds();
        if ($cacheAge < 0 || $cacheAge >= $this->cacheAgeLimit) {
            $this->fetchAndStoreConfigurations();
        }
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidConfigurationException
     */
    public function fetchAndStoreConfigurations(): void
    {
        $responseData = json_decode($this->apiRequestWrapper->getUFC(), true);
        if (!$responseData) {
            syslog(LOG_WARNING, "[Eppo SDK] Empty or invalid response from the configuration server.");
            return;
        }

        $inflated = array_map(fn($object) => $this->parser->parseFlag($object), $responseData['flags']);
        $variations = [];
        if (isset($responseData['bandits'])) {
            $variations = array_map(
                fn($listOfVariations) => array_map(fn($json) => BanditVariation::fromJson($json), $listOfVariations),
                $responseData['bandits']
            );
        } else {
            syslog(LOG_WARNING, "[EPPO SDK] No bandit-flag variations found in UFC response.");
        }

        $indexer = new BanditVariationIndexer($variations);

        $banditModelResponse = json_decode($this->apiRequestWrapper->getBandits(), true);
        if (!$banditModelResponse || !isset($banditModelResponse['bandits'])) {
            syslog(LOG_WARNING, "[Eppo SDK] Empty or invalid response from the configuration server.");
            $bandits = [];
        } else {
            $bandits = array_map(fn($json) => Bandit::fromJson($json), $banditModelResponse['bandits']);
        }

        $this->configurationStore->setConfigurations($inflated, $bandits, $indexer);
    }
}
