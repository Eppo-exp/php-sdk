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
        $this->flags = FlagConfigResponse::fromJson($flagJson ?? []);
        $this->bandits = BanditParametersResponse::fromJson($banditsJson ?? []);
    }

    public static function fromUfcResponses(ConfigResponse $flagsConfig, ?ConfigResponse $banditsConfig): Configuration
    {
        return new self($flagsConfig, $banditsConfig);
    }

    public static function fromConfigurationWire(ConfigurationWire $configurationWire): self
    {
        return new self($configurationWire?->config ?? null, $configurationWire?->bandits ?? null);
    }

    public static function fromFlags(array $flags, ?array $bandits = null)
    {
        $fcr = FlagConfigResponse::fromJson(["flags" => $flags]);
        $flagsConfig = new ConfigResponse(response: json_encode($fcr));
        $banditsConfig = $bandits ? new ConfigResponse(
            response: json_encode(BanditParametersResponse::fromJson(["bandits" => $bandits]))
        ) : null;
        return new self($flagsConfig, $banditsConfig);
    }

    public static function emptyConfig(): self
    {
        return self::fromFlags([]);
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
        if (!isset($this->bandits->bandits[$banditKey])) {
            return null;
        }
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
            flags: $this->flagsConfig,
            bandits: $this->banditsConfig
        );
    }

    public function getFetchedAt(): ?string
    {
        return $this?->flagsConfig?->fetchedAt ?? null;
    }

    public function getFlagETag(): ?string
    {
        return $this->flagsConfig?->eTag ?? null;
    }
}
