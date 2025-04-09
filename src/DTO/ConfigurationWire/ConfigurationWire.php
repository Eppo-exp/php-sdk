<?php

namespace Eppo\DTO\ConfigurationWire;

use Eppo\Traits\ToArray;

class ConfigurationWire
{
    use ToArray;

    public int $version;
    public ?ConfigResponse $config;
    public ?ConfigResponse $bandits;

    public function __construct()
    {
        $this->version = 1;
    }

    public static function create(array $json): self
    {
        $dto = new self();
        $dto->version = $json['version'] ?? 1;
        $dto->config = isset($json['config']) ? ConfigResponse::create($json['config']) : null;
        $dto->bandits = isset($json['bandits']) ? ConfigResponse::create($json['bandits']) : null;
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
}
