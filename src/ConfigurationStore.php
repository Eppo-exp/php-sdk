<?php

namespace Eppo;

use Eppo\Cache\CacheType;
use Eppo\Cache\ICacheFactory;
use Eppo\DTO\Flag;
use Eppo\Exception\EppoClientException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ConfigurationStore implements IConfigurationStore
{
    private CacheInterface $flagCache;

    private CacheInterface $metadataCache;

    const FLAG_TIMESTAMP = "flagTimestamp";

    /**
     * @param ICacheFactory $cacheFactory
     */
    public function __construct(ICacheFactory $cacheFactory)
    {
        $this->flagCache = $cacheFactory->createCache(CacheType::FLAG);
        $this->metadataCache = $cacheFactory->createCache(CacheType::META);
    }

    public function get(string $key): ?Flag
    {
        try {
            $result = $this->flagCache->get($key);
            if ($result == null) return null;

            $inflated = unserialize($result);
            return $inflated === false ? null : $inflated;
        } catch (InvalidArgumentException $e) {
            syslog(LOG_WARNING, "[EPPO SDK] Invalid flag key ${key}: " . $e->getMessage());
            return null;
        }
    }
    private function setFlag(Flag $flag): void
    {
        try {
            $this->flagCache->set($flag->key, serialize($flag));
        } catch (InvalidArgumentException $e) {
            $key = $flag->key;

            // Simple cache throws exceptions when a keystring is not a legal value.
            syslog(LOG_WARNING, "[EPPO SDK] Illegal key value ${key}: " . $e->getMessage());
        }
    }

    /**
     * @throws EppoClientException
     */
    public function setFlags(array $flags): void
    {
        // Set last fetch timestamp.
        try {
            $this->metadataCache->set(self::FLAG_TIMESTAMP, time());
        } catch (InvalidArgumentException $e) {
            throw EppoClientException::From($e);
        }

        foreach($flags as $flag) {
            $this->setFlag($flag);
        }
    }

    public function getFlagCacheAge(): int
    {
        try {
            $lastFetch = $this->metadataCache->get(self::FLAG_TIMESTAMP);
            if ($lastFetch == null) {
                return -1;
            }
        } catch (InvalidArgumentException $e) {
            return -1;
        }
        return time() - $lastFetch;
    }
}
