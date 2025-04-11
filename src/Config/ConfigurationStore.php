<?php

namespace Eppo\Config;

use Eppo\DTO\ConfigurationWire\ConfigurationWire;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ConfigurationStore
{
    private const CONFIG_KEY = "EPPO_configuration_v1";
    private ?Configuration $configuration = null;

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getConfiguration(): Configuration
    {
        if ($this->configuration === null) {
            try {
                $cachedConfig = $this->cache->get(self::CONFIG_KEY);
                if (!$cachedConfig) {
                    return Configuration::emptyConfig(); // Empty config
                }

                $json = json_decode($cachedConfig, true);
                if ($json === null) {
                    return Configuration::emptyConfig();
                }

                $configurationWire = ConfigurationWire::fromJson($json);
                $this->configuration = Configuration::fromConfigurationWire($configurationWire);
            } catch (InvalidArgumentException $e) {
                // Safe to ignore as the const `CONFIG_KEY` contains no invalid characters
                return Configuration::emptyConfig();
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
