<?php

namespace Eppo;

use Eppo\DTO\Allocation;
use Eppo\DTO\Condition;
use Eppo\DTO\ExperimentConfiguration;
use Eppo\DTO\Flag;
use Eppo\DTO\Rule;
use Eppo\DTO\ShardRange;
use Eppo\DTO\Split;
use Eppo\DTO\Variation;
use Eppo\DTO\Shard;
use Eppo\Exception\InvalidApiKeyException;

class FlagConfigurationLoader
{
    const UFC_ENDPOINT = '/api/flag-config/v1/config';

    private UFCParser $parser;
    public function __construct(private readonly HttpClient $httpClient, private readonly ConfigurationStore $configurationStore)
    {
        $this->parser = new UFCParser();
    }

    public function getConfiguration(string $flagKey): ?Flag
    {
        if ($this->httpClient->isUnauthorized) {
            throw new InvalidApiKeyException();
        }

        $configuration = $this->configurationStore->getConfiguration($flagKey);

        if (!$configuration) {
            $configurations = $this->fetchAndStoreConfigurations();
            if (!$configurations || !count($configurations) || !array_key_exists($flagKey, $configurations)) {
                return null;
            }

            $configuration = $configurations[$flagKey];
        }

        return $this->parser->parseFlag($configuration);
    }

    public function fetchAndStoreConfigurations(): array
    {
        $responseData = json_decode($this->httpClient->get(self::UFC_ENDPOINT), true);
        if (!$responseData) {
            return [];
        }

        $this->configurationStore->setConfigurations($responseData['flags']);
        return $responseData['flags'];
    }
}