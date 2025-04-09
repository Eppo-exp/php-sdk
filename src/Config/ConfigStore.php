<?php

namespace Eppo\Config;

use Eppo\DTO\ConfigurationWire\ConfigurationWire;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ConfigStore
{
    private const CONFIG_KEY = "EPPO_configuration_v1";
    private Configuration $configuration;

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getConfiguration(): Configuration
    {
        if ($this->configuration === null) {
            try {
                $json = json_decode($this->cache->get(self::CONFIG_KEY), true);
                $configurationWire = ConfigurationWire::create($json ?? []); // Blank config wired default.
                $this->configuration = Configuration::fromConfigurationWire($configurationWire);
            } catch (InvalidArgumentException $e) {
                // Safe to ignore as the const `CONFIG_KEY` contains no invalid characters
            }
        }
        return $this->configuration;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        try {
            $this->cache->set(self::CONFIG_KEY, json_encode($configuration->toConfigurationWire()->toArray()));
        } catch (InvalidArgumentException $e) {
            // Safe to ignore as the const `CONFIG_KEY` contains no invalid characters
        }
    }
}
