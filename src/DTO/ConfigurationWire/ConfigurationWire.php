<?php

namespace Eppo\DTO\ConfigurationWire;

class ConfigurationWire
{
    public int $version;
    public ?ConfigResponse $config;
    public ?ConfigResponse $bandits;

    public function __construct()
    {
        $this->version = 1;
    }

    public function toArray(): array
    {
        $arr = ['version' => $this->version];
        if ($this->config) {
            $arr['config'] = $this->config->toArray();
        }
        if ($this->bandits) {
            $arr['bandits'] = $this->bandits->toArray();
        }
        return $arr;
    }

    public static function fromArray(array $arr): self
    {
        $dto = new self();
        $dto->version = $arr['version'] ?? 1;
        if (isset($arr['config'])) {
            $dto->config = ConfigResponse::fromJson($arr['config']);
        }
        if (isset($arr['bandits'])) {
            $dto->bandits = ConfigResponse::fromJson($arr['bandits']);
        }
        return $dto;
    }

    public static function fromResponses(ConfigResponse $flags, ?ConfigResponse $bandits): self
    {
        $dto = new self();
        $dto->config = $flags;
        $dto->bandits = $bandits;
        return $dto;
    }

    public static function fromJsonString(string $jsonEncodedString): self
    {
        return ConfigurationWire::fromArray(json_decode($jsonEncodedString, associative: true));
    }

    public function toJsonString(): string
    {
        return json_encode($this->toArray());
    }
}
