<?php

namespace Eppo\Flags;

use Eppo\APIRequestWrapper;
use Eppo\DTO\Flag;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\UFCParser;

class FlagConfigurationLoader implements IFlags
{
    private UFCParser $parser;

    public function __construct(
        private readonly APIRequestWrapper $apiRequestWrapper,
        private readonly IConfigurationStore $configurationStore
    ) {
        $this->parser = new UFCParser();
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

    public function get(string $key): ?Flag
    {
        return $this->configurationStore->get($key);
    }
}
