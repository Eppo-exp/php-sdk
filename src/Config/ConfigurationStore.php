<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditReferenceIndexer;
use Eppo\Bandits\IBanditReferenceIndexer;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Flag;
use Psr\SimpleCache\CacheInterface;

class ConfigurationStore
{
    private const CONFIGURATION_KEY = 'eppo_configuration';
    private ?Configuration $currentConfiguration = null;

    // Key for storing bandit variations in the metadata cache.
    private const BANDIT_VARIATION_KEY = 'banditVariations';

    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function getConfiguration(): ?Configuration
    {
        if ($this->currentConfiguration === null) {
            try {
                $this->currentConfiguration = $this->cache->get(self::CONFIGURATION_KEY);
            } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
                syslog(LOG_WARNING, "[EPPO SDK] Error retrieving configuration: " . $e->getMessage());
                return null;
            }
        }
        return $this->currentConfiguration;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        try {
            $this->currentConfiguration = $configuration;
            $this->cache->set(self::CONFIGURATION_KEY, $configuration);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            syslog(LOG_WARNING, "[EPPO SDK] Error storing configuration: " . $e->getMessage());
        }
    }

    /**
     * @param string $key
     * @return Flag|null
     */
    public function getFlag(string $key): ?Flag
    {
        $config = $this->getConfiguration();
        return $config?->getFlag($key);
    }


    public function getBanditReferenceIndexer(): IBanditReferenceIndexer
    {
        $config = $this->getConfiguration();
        return $config?->banditReferenceIndexer ?? BanditReferenceIndexer::empty();
    }


    public function getBandit(string $key): ?Bandit
    {
        $config = $this->getConfiguration();
        return $config?->getBandit($key);
    }
}
