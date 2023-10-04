<?php

namespace Eppo;

use Eppo\DTO\ExperimentConfiguration;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\SimpleCache\InvalidArgumentException;

class ExperimentConfigurationRequester
{
    /** @var string */
    const RAC_ENDPOINT = '/api/randomized_assignment/v3/config';

    /** @var HttpClient */
    private $httpClient;

    /** @var ConfigurationStore */
    private $configurationStore;

    /**
     * @param HttpClient $httpClient
     * @param ConfigurationStore $configurationStore
     */
    public function __construct(HttpClient $httpClient, ConfigurationStore $configurationStore)
    {
        $this->httpClient = $httpClient;
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
        if ($this->httpClient->isUnauthorized) {
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
     * @throws GuzzleException
     * @throws HttpRequestException
     * @throws InvalidArgumentException
     */
    public function fetchAndStoreConfigurations(): array
    {
        $responseData = json_decode($this->httpClient->get(self::RAC_ENDPOINT), true);
        if (!$responseData) {
            return [];
        }

        $this->configurationStore->setConfigurations($responseData['flags']);
        return $responseData['flags'];
    }
}
