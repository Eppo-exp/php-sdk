<?php

namespace Eppo\Config;

use Eppo\DTO\ConfigurationWire\ConfigurationWire;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Throwable;

class ConfigurationStore implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const CONFIG_KEY = "EPPO_configuration_v1";
    private ?Configuration $configuration = null;

    public function __construct(private readonly CacheInterface $cache)
    {
        $this->setLogger(new NullLogger());
    }

    public function getConfiguration(): Configuration
    {
        if ($this->configuration !== null) {
            return $this->configuration;
        }
        try {
            $cachedConfig = $this->cache->get(self::CONFIG_KEY);
            if (!$cachedConfig) {
                return Configuration::emptyConfig(); // Empty config
            }

            $arr = json_decode($cachedConfig, true);
            if ($arr === null) {
                return Configuration::emptyConfig();
            }

            $configurationWire = ConfigurationWire::fromArray($arr);
            $this->configuration = Configuration::fromConfigurationWire($configurationWire);

            return $this->configuration;
        } catch (Throwable $e) {
            // Safe to ignore as the const `CONFIG_KEY` contains no invalid characters
            $this->logger->error(
                '[Eppo SDK] Error loading config from cache ' . $e->getMessage(),
                ['exception' => $e]
            );
            return Configuration::emptyConfig();
        }
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        try {
            $this->cache->set(self::CONFIG_KEY, json_encode($configuration->toConfigurationWire()->toArray()));
        } catch (Throwable $e) {
            // Safe to ignore as the const `CONFIG_KEY` contains no invalid characters
            $this->logger->error(
                '[Eppo SDK] Error saving config to cache ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
