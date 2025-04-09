<?php

namespace Eppo\Config;

use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\BanditParametersResponse;
use Eppo\DTO\BanditReference;
use Eppo\DTO\ConfigurationWire\ConfigResponse;
use Eppo\DTO\ConfigurationWire\ConfigurationWire;
use Eppo\DTO\Flag;
use Eppo\DTO\FlagConfigResponse;

class Configuration
{
    private array $parsedFlags = [];
    private readonly FlagConfigResponse $flags;
    private readonly BanditParametersResponse $bandits;


    private function __construct(
        private readonly ConfigResponse $flagsConfig,
        private readonly ?ConfigResponse $banditsConfig
    ) {
        $flagJson = json_decode($this->flagsConfig->response, true);
        $banditsJson = json_decode($this->banditsConfig?->response ?? "", true);
        $this->flags = FlagConfigResponse::create($flagJson ?? []);
        $this->bandits = BanditParametersResponse::create($banditsJson ?? []);
    }

    public static function fromUfcResponses(ConfigResponse $flagsConfig, ?ConfigResponse $banditsConfig): Configuration
    {
        return new self($flagsConfig, $banditsConfig);
    }

    public static function fromConfigurationWire(ConfigurationWire $configurationWire): self
    {
        return new self($configurationWire->config, $configurationWire->bandits);
    }

    public function getFlag(string $key): ?Flag
    {
        if (!isset($this->parsedFlags[$key])) {
            $flagObj = $this->flags->flags[$key] ?? null;
            if ($flagObj !== null) {
                $this->parsedFlags[$key] = Flag::fromJson($flagObj);
            }
        }
        return $this->parsedFlags[$key] ?? null;
    }

    public function getBandit(string $banditKey): ?Bandit
    {
        return Bandit::fromJson($this->bandits?->bandits[$banditKey]) ?? null;
    }

    public function getBanditByVariation(string $flagKey, string $variation): ?string
    {
        foreach ($this->flags->banditReferences as $banditKey => $banditReferenceObj) {
            $banditReference = BanditReference::fromJson($banditReferenceObj);
            foreach ($banditReference->flagVariations as $flagVariation) {
                if ($flagVariation->flagKey === $flagKey && $flagVariation->variationKey === $variation) {
                    return $banditKey;
                }
            }
        }
        return null;
    }

    public function toConfigurationWire(): ConfigurationWire
    {
        return ConfigurationWire::fromResponses(
            version: 1,
            flags: $this->flagsConfig,
            bandits: $this->banditsConfig
        );
    }
}
