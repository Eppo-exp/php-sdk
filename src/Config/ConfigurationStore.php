<?php

namespace Eppo\Config;

use Eppo\API\CachedResourceMeta;
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
    private const FLAG_META = "flagResourceMetadata";
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
    public function setFlagConfigurations(array $flags, ?BanditVariationIndexer $banditVariations = null): void
    {
        try {
            // Clear all stored config before setting data.
            $this->flagCache->clear();

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

    public function getFlagCacheMetadata(): ?CachedResourceMeta
    {
        try {
            $meta = $this->metadataCache->get(self::FLAG_META);
            if ($meta != null) {
                return unserialize($meta) ?: null; // unserialize returns false if there was a problem decoding.
            }
        } catch (InvalidArgumentException $e) {
        }

        return null;
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

    public function setFlagCacheMetadata(CachedResourceMeta $metadata): void
    {
        $this->metadataCache->set(self::FLAG_META, serialize($metadata));
    }
}
