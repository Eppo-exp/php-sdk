<?php

namespace Eppo;

use Eppo\DTO\Allocation;
use Eppo\DTO\Condition;
use Eppo\DTO\ExperimentConfiguration;
use Eppo\DTO\Rule;
use Eppo\DTO\ShardRange;
use Eppo\DTO\Variation;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\SimpleCache\InvalidArgumentException;

class ExperimentConfigurationRequester
{
    const RAC_ENDPOINT = '/api/randomized_assignment/v2/config';

    /** @var HttpClient */
    private $httpClient;

    /** @var ConfigurationStore */
    private $configurationStore;

    public function __construct(HttpClient $httpClient, ConfigurationStore $configurationStore)
    {
        $this->httpClient = $httpClient;
        $this->configurationStore = $configurationStore;
    }

    /**
     * @param string $experiment
     * @return ExperimentConfiguration
     * @throws GuzzleException
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     */
    public function getConfiguration(string $experiment): ExperimentConfiguration
    {
        if ($this->httpClient->isUnauthorized) {
            throw new InvalidApiKeyException();
        }

        $configuration = $this->configurationStore->getConfiguration($experiment);

        if (!$configuration) {
            $configuration = $this->fetchAndStoreConfigurations()[$experiment];
        }

        return $this->mapArrayToExperimentConfiguration($configuration);
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
        $this->configurationStore->setConfigurations($responseData['flags']);
        return $responseData['flags'];
    }

    private function mapArrayToExperimentConfiguration(array $configuration): ExperimentConfiguration
    {
        $experimentConfiguration = new ExperimentConfiguration();

        $experimentConfiguration->setEnabled($configuration['enabled']);
        $experimentConfiguration->setSubjectShards($configuration['subjectShards']);

        $rules = [];
        foreach ($configuration['rules'] as $configRule) {
            $rule = new Rule();
            $rule->allocationKey = $configRule['allocationKey'];

            foreach ($configRule['conditions'] as $configCondition) {
                $condition = new Condition();
                $condition->value = $configCondition['value'];
                $condition->operator = $configCondition['operator'];
                $condition->attribute = $configCondition['attribute'];

                $rule->conditions[] = $condition;
            }

            $rules[] = $rule;
        }
        $experimentConfiguration->setRules($rules);

        $allocations = [];
        foreach ($configuration['allocations'] as $configAllocationName => $configAllocation) {
            $allocation = new Allocation();
            $allocation->percentExposure = $configAllocation['percentExposure'];

            foreach ($configAllocation['variations'] as $configVariation) {
                $variation = new Variation();
                $variation->shardRange = new ShardRange();
                $variation->name = $configVariation['name'];
                $variation->value = $configVariation['value'];
                $variation->shardRange->start = $configVariation['shardRange']['start'];
                $variation->shardRange->end = $configVariation['shardRange']['end'];

                $allocation->variations[] = $variation;
            }

            $allocations[$configAllocationName] = $allocation;
        }

        $experimentConfiguration->setAllocations($allocations);

        return $experimentConfiguration;
    }
}