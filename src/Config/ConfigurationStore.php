<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Cache\CacheType;
use Eppo\Cache\NamespaceCache;
use Eppo\DTO\Flag;
use Eppo\Exception\EppoClientException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ConfigurationStore implements IConfigurationStore
{
    private CacheInterface $rootCache;
    private CacheInterface $flagCache;
    private CacheInterface $metadataCache;

    private const FLAG_TIMESTAMP = "flagTimestamp";
    private const BANDIT_VARIATION_KEY = 'banditVariations';

    /**
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
        $this->rootCache = $cache;
        $this->flagCache = new NamespaceCache(CacheType::FLAG, $cache);
        $this->metadataCache = new NamespaceCache(CacheType::META, $cache);
    }

    public function getFlag(string $key): ?Flag
    {
        try {
            $result = $this->flagCache->get($key);
            if ($result == null) {
                return null;
            }

            $inflated = unserialize($result);
            return $inflated === false ? null : $inflated;
        } catch (InvalidArgumentException $e) {
            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key ${key}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array $flags
     * @param BanditVariationIndexer|null $banditVariations
     * @throws EppoClientException
     */
    public function setConfigurations(array $flags, BanditVariationIndexer $banditVariations = null): void
    {
        try {
            // Clear all stored config before setting data.
            $this->rootCache->clear();

            // Set last fetch timestamp.
            $this->metadataCache->set(self::FLAG_TIMESTAMP, time());
            $this->setFlags($flags);
            $this->metadataCache->set(self::BANDIT_VARIATION_KEY, serialize($banditVariations));
        } catch (InvalidArgumentException $e) {
            throw EppoClientException::from($e);
        }
    }

    /**
     * @param Flag[] $flags
     * @return void
     */
    private function setFlags(array $flags): void
    {
        $serialized = [];
        array_walk($flags, function (Flag &$value) use (&$serialized) {
            $serialized[$value->key] = serialize($value);
        });

        try {
            $this->flagCache->setMultiple($serialized);
        } catch (InvalidArgumentException $e) {
            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key: " . $e->getMessage());
        }
    }

    public function getFlagCacheAgeSeconds(): int
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

    /**
     * @throws EppoClientException
     */
    public function getBanditVariations(): BanditVariationIndexer
    {
        try {
            return unserialize($this->metadataCache->get(self::BANDIT_VARIATION_KEY));
        } catch (InvalidArgumentException $e) {
            // We know that the key does not contain illegal characters so we should not end up here.
            throw EppoClientException::From($e);
        }
    }
}
