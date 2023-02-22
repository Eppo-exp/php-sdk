<?php

namespace Eppo;

use Eppo\DTO\ExperimentConfiguration;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use GuzzleHttp\Exception\GuzzleException;

class ExperimentConfigurationRequester
{
    const RAC_ENDPOINT = '/api/randomized_assignment/v2/config';

    /** @var HttpClient */
    private $httpClient;

    /** @var ConfigurationStore */
    private $configurationStore;

    public function __construct(HttpClient $httpClient, ConfigurationStore $configurationStore) {
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
     */
    public function getConfiguration(string $experiment): ExperimentConfiguration {
        if ($this->httpClient->isUnauthorized) {
            throw new InvalidApiKeyException();
        }

        $configuration = $this->configurationStore->getConfiguration($experiment);

        if (!$configuration) {
            var_dump('no configuration found in apcu');
            $this->fetchAndStoreConfigurations();
        }

        return new ExperimentConfiguration();
    }

    /**
     * @return array
     *
     * @throws GuzzleException
     * @throws HttpRequestException
     */
    public function fetchAndStoreConfigurations() {
        $responseData = json_decode($this->httpClient->get(self::RAC_ENDPOINT), true);
        $this->configurationStore->setConfigurations($responseData['flags']);
        return $responseData['flags'];
    }
}