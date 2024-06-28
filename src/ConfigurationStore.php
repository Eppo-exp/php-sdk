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
    private CacheInterface $cache;
    private CacheInterface $flagCache;

    private CacheInterface $metadataCache;

    const FLAG_TIMESTAMP = "flagTimestamp";

    /**
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
        $this->flagCache = new Cache\NamespaceCache(CacheType::FLAG, $cache);
        $this->metadataCache = new Cache\NamespaceCache(CacheType::META, $cache);
    }

    public function get(string $key): ?Flag
    {
        try {
            $result = $this->flagCache->get($key);
            if ($result == null) return null;

            $inflated = unserialize($result);
            return $inflated === false ? null : $inflated;
        } catch (InvalidArgumentException $e) {

            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key ${key}: " . $e->getMessage());
            return null;
        }
    }
    private function setFlag(Flag $flag): void
    {
        try {
            $this->flagCache->set($flag->key, serialize($flag));
        } catch (InvalidArgumentException $e) {
            $key = $flag->key;

            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key ${key}: " . $e->getMessage());
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
