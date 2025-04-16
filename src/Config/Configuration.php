<?php

namespace Eppo\Config;

use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\BanditParametersResponse;
use Eppo\DTO\ConfigurationWire\ConfigResponse;
use Eppo\DTO\ConfigurationWire\ConfigurationWire;
use Eppo\DTO\Flag;
use Eppo\DTO\FlagConfigResponse;

class Configuration
{
    private readonly FlagConfigResponse $flags;
    private readonly BanditParametersResponse $bandits;


    private function __construct(
        private readonly ConfigResponse $flagsConfig,
        private readonly ?ConfigResponse $banditsConfig
    ) {
        $flagJson = json_decode($this->flagsConfig->response, true);
        $banditsJson = json_decode($this->banditsConfig?->response ?? "", true);
        $this->flags = FlagConfigResponse::fromArray($flagJson ?? []);
        $this->bandits = BanditParametersResponse::fromArray($banditsJson ?? []);
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
        $flagsConfig = new ConfigResponse(response: json_encode(["flags" => $flags]));
        $banditsConfig = $bandits ? new ConfigResponse(
            response: json_encode(BanditParametersResponse::fromArray(["bandits" => $bandits]))
        ) : null;
        return new self($flagsConfig, $banditsConfig);
    }

    public static function emptyConfig(): self
    {
        return self::fromFlags([]);
    }

    public function getFlag(string $key): ?Flag
    {
        return $this->flags->flags[$key] ?? null;
    }

    public function getBandit(string $banditKey): ?Bandit
    {
        if (!isset($this->bandits->bandits[$banditKey])) {
            return null;
        }
        return Bandit::fromArray($this->bandits?->bandits[$banditKey]) ?? null;
    }

    public function getBanditByVariation(string $flagKey, string $variation): ?string
    {
        foreach ($this->flags->banditReferences as $banditKey => $banditReference) {
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

    public function getBanditModelVersions(): array
    {
        $models = [];
        foreach ($this->bandits->bandits as $key => $banditArr) {
            $bandit = Bandit::fromArray($banditArr);
            $models[$key] = $bandit->modelVersion;
        }
        return $models;
    }
}
