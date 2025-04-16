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
        $currentConfig = $this->configurationStore->getConfiguration();
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
            $fcr = FlagConfigResponse::fromArray($responseData);
            $banditResponse = null;
            // If the flags reference Bandits, load bandits from the API, or reuse models already downloaded.
            if (count($fcr->banditReferences) > 0) {
                // Assume we can reuse bandits.
                $canReuseBandits = true;
                $currentBandits = $currentConfig->getBanditModels();

                // Check each referenced bandit model (what we need) against the current bandits (what we have).
                foreach ($fcr->banditReferences as $banditKey => $banditReference) {
                    if (
                        !array_key_exists(
                            $banditKey,
                            $currentBandits
                        ) || $banditReference->modelVersion !== $currentBandits[$banditKey]
                    ) {
                        // We don't have a bandit for this key or the model versions don't match.
                        $canReuseBandits = false;
                        break;
                    }
                }

                if ($canReuseBandits) {
                    // Get the bandit ConfigResponse from the most recent configuration.
                    $banditResponse = $currentConfig->toConfigurationWire()->bandits;
                } else {
                    // Fetch the bandits from the API and build a ConfigResponse to populate the new
                    // Configuration object.
                    $banditResource = $this->apiRequestWrapper->getBandits();
                    if (!$banditResource?->body) {
                        syslog(E_ERROR, "[Eppo SDK] Empty or invalid bandit response from the configuration server.");
                    } else {
                        $banditResponse = new ConfigResponse($banditResource->body, date('c'), $banditResource->eTag);
                    }
                }
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
