<?php

namespace Eppo\Config;

use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\BanditFlagVariation;
use Eppo\DTO\BanditParametersResponse;
use Eppo\DTO\BanditReference;
use Eppo\DTO\ConfigResponse;
use Eppo\DTO\ConfigurationWire;
use Eppo\DTO\Flag;
use Eppo\DTO\FlagConfigResponse;
use Eppo\UFCParser;

class Configuration
{
    private array $parsedFlags = [];

    private function __construct(
        private readonly UFCParser $parser,
        private readonly FlagConfigResponse $config,
        private readonly ?BanditParametersResponse $bandits = null,
        private readonly ?string $flagETag = null,
        private readonly ?string $fetchedAt = null,
        private readonly ?string $banditsETag = null,
    ) {
    }

    public function getFlag(string $key): ?Flag
    {
        if (isset($this->parsedFlags[$key])) {
            return $this->parsedFlags[$key];
        }
        $flagObj = $this->config->flags[$key] ?? null;
        var_dump($flagObj);
        if ($flagObj !== null) {
            return $this->parser->parseFlag($flagObj);
        }
        return $this->config->flags[$key] ?? null;
    }

    public function getBandit(string $banditKey): ?Bandit
    {
        return Bandit::fromJson($this->bandits?->bandits[$banditKey]) ?? null;
    }

    public function getBanditByVariation(string $flagKey, string $variation): ?string
    {
        foreach ($this->config->banditReferences as $banditKey => $banditReferenceObj) {
            $banditReference = BanditReference::fromJson($banditReferenceObj);
            foreach ($banditReference->flagVariations as $flagVariationObj) {
                $flagVariation = BanditFlagVariation::fromJson($flagVariationObj);
                if ($flagVariation->flagKey === $flagKey && $flagVariation->variationKey === $variation) {
                    return $banditKey;
                }
            }
        }
        return null;
    }

    public function toConfigurationWire(): ConfigurationWire
    {
        return ConfigurationWire::Create([
            'version' => 1,
            'config' => ConfigResponse::create(
                [
                    "response" =>
                        json_encode(
                            $this->config->toArray()
                        ),
                    "eTag" => $this->flagETag,
                    "fetchedAt" => $this->fetchedAt
                ]
            ),

            'bandits' => $this->bandits ? ConfigResponse::create([
                "response" =>
                    json_encode($this->bandits->toArray()),
                "eTag" => $this->banditsETag,
                "fetchedAt" => $this->fetchedAt
            ]) : null
        ]);
    }

    public static function fromConfigurationWire(ConfigurationWire $configurationWire): self
    {
        $flags = FlagConfigResponse::create(json_decode($configurationWire->config->response, true));
        $bandits = $configurationWire->bandits ? BanditParametersResponse::create(
            json_decode($configurationWire->bandits->response, true)
        ) : null;

        return new self(new UFCParser(), $flags, $bandits);
    }
}
