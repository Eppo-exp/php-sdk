<?php

namespace Eppo;

use Eppo\DTO\Flag;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;

class FlagConfigurationLoader
{
    private UFCParser $parser;
    public function __construct(private readonly APIRequestWrapper $apiRequestWrapper, private readonly ConfigurationStore $configurationStore)
    {
        $this->parser = new UFCParser();
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     */
    public function getConfiguration(string $flagKey): ?Flag
    {
        if ($this->apiRequestWrapper->isUnauthorized) {
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

    /**
     * @throws InvalidArgumentException
     * @throws HttpRequestException
     * @throws ClientExceptionInterface
     */
    public function fetchAndStoreConfigurations(): array
    {
        $responseData = json_decode($this->apiRequestWrapper->get(), true);
        if (!$responseData) {
            return [];
        }

        $this->configurationStore->setConfigurations($responseData['flags']);
        return $responseData['flags'];
    }
}
