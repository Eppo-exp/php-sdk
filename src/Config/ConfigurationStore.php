<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Bandits\IBanditVariationIndexer;
use Eppo\Cache\CacheType;
use Eppo\Cache\NamespaceCache;
use Eppo\DTO\Flag;
use Eppo\Exception\EppoClientException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Validator;
use Psr\SimpleCache\CacheInterface;

class ConfigurationStore implements IConfigurationStore
{
    private CacheInterface $flagCache;
    private CacheInterface $metadataCache;
    private const FLAG_META = "flagResourceMetadata";
    private const BANDIT_VARIATION_KEY = 'banditVariations';

    /**
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
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
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key ${key}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array $flags
     * @param IBanditVariationIndexer|null $banditVariations
     * @throws EppoClientException
     */
    public function setUnifiedFlagConfiguration(array $flags, ?IBanditVariationIndexer $banditVariations = null): void
    {
        try {
            // Clear stored config before setting data.
            $this->flagCache->clear();

            $this->setFlags($flags);
            if ($banditVariations == null) {
                $this->metadataCache->delete(self::BANDIT_VARIATION_KEY);
            } else {
                $this->metadataCache->set(self::BANDIT_VARIATION_KEY, serialize($banditVariations));
            }
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
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
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key: " . $e->getMessage());
        }
    }

    public function getMetadata(string $key): mixed
    {
        try {
            $meta = $this->metadataCache->get($key);
            if ($meta != null) {
                return unserialize($meta) ?: null; // unserialize returns false if there was a problem decoding.
            }
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key: " . $e->getMessage());
        }

        return null;
    }

    /**
     * @throws EppoClientException
     */
    public function getBanditVariations(): IBanditVariationIndexer
    {
        try {
            $data = $this->metadataCache->get(self::BANDIT_VARIATION_KEY);
            if ($data !== null) {
                return unserialize($data);
            }
            return BanditVariationIndexer::empty();
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // We know that the key does not contain illegal characters so we should not end up here.
            throw EppoClientException::From($e);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setMetadata(string $key, mixed $metadata): void
    {
        Validator::validateNotEqual($key, self::FLAG_META, "Unable to use reserved key, {self::FLAG_META}");
        try {
            $this->metadataCache->set($key, serialize($metadata));
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key: " . $e->getMessage());
        }
    }
}
