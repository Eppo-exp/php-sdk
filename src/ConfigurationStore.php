<?php

namespace Eppo;

use Eppo\DTO\Flag;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ConfigurationStore implements IConfigurationStore
{
    /** @var CacheInterface */
    private $cache;

    /**
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function setFlag(Flag $flag): void
    {
        try {
            $this->cache->set($flag->key, serialize($flag));
        } catch (InvalidArgumentException $e) {
            $key = $flag->key;

            // Simple cache throws exceptions when a keystring is not a legal value.
            syslog(LOG_WARNING, "[EPPO SDK] Illegal key value ${key}: " . $e->getMessage());
        }
    }

    public function setFlags(array $flags): void
    {
        foreach($flags as $flag) {
            $this->setFlag($flag);
        }
    }

    public function get(string $key): ?Flag
    {
        try {
            $result = $this->cache->get($key);
            if ($result == null) return null;

            $inflated = unserialize($result);
            return $inflated === false ? null : $inflated;
        } catch (InvalidArgumentException $e) {
            syslog(LOG_WARNING, "[EPPO SDK] Invalid flag key ${key}: " . $e->getMessage());
            return null;
        }
    }
}
