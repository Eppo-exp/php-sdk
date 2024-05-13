<?php

namespace Eppo;

use Eppo\DTO\ExperimentConfiguration;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use GuzzleHttp\Exception\GuzzleException;
use Http\Discovery\Psr18Client;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ExperimentConfigurationRequester
{

    private APIRequestWrapper $apiClient;

    private ConfigurationStore $configurationStore;


    /**
     * @param APIRequestWrapper $httpClient
     * @param ConfigurationStore $configurationStore
     */
    public function __construct(APIRequestWrapper $httpClient, ConfigurationStore $configurationStore)
    {
        $this->apiClient = $httpClient;
        $this->configurationStore = $configurationStore;
    }

    /**
     * @param string $experiment
     *
     * @return ExperimentConfiguration
     *
     * @throws GuzzleException
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     */
    public function getConfiguration(string $experiment): ?ExperimentConfiguration
    {
        if ($this->apiClient->isUnauthorized) {
            throw new InvalidApiKeyException();
        }

        $configuration = $this->configurationStore->getConfiguration($experiment);

        if (!$configuration) {
            $configurations = $this->fetchAndStoreConfigurations();
            if (!$configurations || !count($configurations) || !array_key_exists($experiment, $configurations)) {
                return null;
            }

            $configuration = $configurations[$experiment];
        }

        return new ExperimentConfiguration($configuration);
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     */
    public function fetchAndStoreConfigurations(): array
    {
        $responseData = json_decode($this->apiClient->get(), true);
        if (!$responseData) {
            return [];
        }
        $this->configurationStore->setConfigurations($responseData['flags']);
        return $responseData['flags'];
    }
}
