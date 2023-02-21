<?php

namespace Eppo;

use Eppo\DTO\ExperimentConfiguration;
use Eppo\Exception\InvalidApiKeyException;

class ExperimentConfigurationRequester
{
    const RAC_ENDPOINT = '/randomized_assignment/v2/config';

    /** @var HttpClient */
    private $httpClient;

    public function __construct(HttpClient $httpClient) {
        $this->httpClient = $httpClient;
    }

    /**
     * @param string $experiment
     *
     * @return ExperimentConfiguration
     *
     * @throws InvalidApiKeyException
     */
    public function getConfiguration($experiment): ExperimentConfiguration {
        if ($this->httpClient->isUnauthorized) {
            throw new InvalidApiKeyException();
        }
        return new ExperimentConfiguration();
    }

    public function fetchAndStoreConfigurations() {

    }
}