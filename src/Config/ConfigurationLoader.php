<?php

namespace Eppo\Config;

use Eppo\API\APIRequestWrapper;
use Eppo\DTO\ConfigurationWire\ConfigResponse;
use Eppo\DTO\FlagConfigResponse;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;

class ConfigurationLoader
{
    public function __construct(
        private readonly APIRequestWrapper $apiRequestWrapper,
        public readonly ConfigurationStore $configurationStore,
        private readonly int $cacheAgeLimitMillis = 30 * 1000
    ) {
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     */
    public function reloadConfigurationIfExpired(): void
    {
        $flagCacheAge = $this->getCacheAgeInMillis();
        if ($flagCacheAge >= ($this->cacheAgeLimitMillis)) {
            $this->reloadConfiguration();
        }
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     */
    public function fetchAndStoreConfiguration(?string $flagETag): void
    {
        $response = $this->apiRequestWrapper->getUFC($flagETag);
        if ($response->isModified) {
            $configResponse = new ConfigResponse(
                $response->body,
                date('c'),
                $response->eTag
            );
            $responseData = json_decode($response->body, true);

            if ($responseData === null) {
                syslog(LOG_WARNING, "[Eppo SDK] Empty or invalid response from the configuration server.");
                return;
            }
            $fcr = FlagConfigResponse::create($responseData);
            $banditResponse = null;
            if (count($fcr->banditReferences) > 0) {
                $bandits = $this->apiRequestWrapper->getBandits();
                $banditResponse = new ConfigResponse($bandits->body, date('c'), $bandits->eTag);
            }

            $configuration = Configuration::fromUfcResponses($configResponse, $banditResponse);
            $this->configurationStore->setConfiguration($configuration);
        }
    }

    private function getCacheAgeInMillis(): int
    {
        $timestamp = $this->configurationStore->getConfiguration()->getFetchedAt();
        if (!$timestamp) {
            return PHP_INT_MAX;
        }
        try {
            $dateTime = new \DateTime($timestamp);
            $timestampMillis = (int)($dateTime->format('U.u') * 1000);
            return $this->milliTime() - $timestampMillis;
        } catch (\Exception $e) {
            return PHP_INT_MAX;
        }
    }

    /**
     * @return void
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     */
    public function reloadConfiguration(): void
    {
        $flagETag = $this->configurationStore->getConfiguration()->getFlagETag();
        $this->fetchAndStoreConfiguration($flagETag);
    }

    private function milliTime(): int
    {
        return intval(microtime(true) * 1000);
    }
}
