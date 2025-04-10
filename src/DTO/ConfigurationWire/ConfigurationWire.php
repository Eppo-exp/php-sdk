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

    public static function create(array $json): self
    {
        $dto = new self();
        $dto->version = $json['version'] ?? 1;
        if (isset($json['config'])) {
            $dto->config = ConfigResponse::create($json['config']);
        }
        if (isset($json['bandits'])) {
            $dto->bandits = ConfigResponse::create($json['bandits']);
        }
        return $dto;
    }

    public static function fromResponses(int $version, ConfigResponse $flags, ?ConfigResponse $bandits): self
    {
        $dto = new self();
        $dto->version = $version;
        $dto->config = $flags;
        $dto->bandits = $bandits;
        return $dto;
    }

    public static function fromJsonString(string $jsonEncodedString): self
    {
        return ConfigurationWire::create(json_decode($jsonEncodedString, associative: true));
    }

    public function toJsonString(): string
    {
        return json_encode($this->toArray());
    }
}
