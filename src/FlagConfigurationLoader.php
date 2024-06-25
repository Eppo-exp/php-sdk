<?php

namespace Eppo;

use Eppo\DTO\Flag;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;

class FlagConfigurationLoader implements IFlags
{
    private UFCParser $parser;

    public function __construct(
        private readonly APIRequestWrapper $apiRequestWrapper,
        private readonly IConfigurationStore $configurationStore,
        private readonly int $cacheAgeLimit = 30
    ) {
        $this->parser = new UFCParser();
    }

    public function get(string $key): ?Flag
    {
        $this->maybeReloadConfiguration();
        return $this->configurationStore->get($key);
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     */
    public function maybeReloadConfiguration(): void
    {
        $cacheAge = $this->configurationStore->getFlagCacheAge();
        if ($cacheAge < 0 || $cacheAge >= $this->cacheAgeLimit) {
            $this->fetchAndStoreConfigurations();
        }
    }

    /**
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     */
    public function fetchAndStoreConfigurations(): void
    {
        $responseData = json_decode($this->apiRequestWrapper->get(), true);

        if (!$responseData) {
            syslog(LOG_WARNING, "[Eppo SDK] Empty or invalid response from the configuration server.");
            return;
        }

        $inflated = array_map(fn($object) => $this->parser->parseFlag($object), $responseData['flags']);
        $this->configurationStore->setFlags($inflated);
    }
}
