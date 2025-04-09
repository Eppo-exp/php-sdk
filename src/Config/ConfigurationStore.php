<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditReferenceIndexer;
use Eppo\Bandits\IBanditReferenceIndexer;
use Eppo\Cache\CacheType;
use Eppo\Cache\NamespaceCache;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Flag;
use Eppo\Exception\EppoClientException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Exception\InvalidConfigurationException;
use Eppo\Validator;
use Psr\SimpleCache\CacheInterface;

class ConfigurationStore
{
    private CacheInterface $flagCache;
    private CacheInterface $banditCache;
    private CacheInterface $metadataCache;

    // Key for storing bandit variations in the metadata cache.
    private const BANDIT_VARIATION_KEY = 'banditVariations';

    /**
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
        $this->flagCache = new NamespaceCache(CacheType::FLAG, $cache);
        $this->banditCache = new NamespaceCache(CacheType::BANDIT, $cache);
        $this->metadataCache = new NamespaceCache(CacheType::META, $cache);
    }

    /**
     * @param string $key
     * @return Flag|null
     */
    public function getFlag(string $key): ?Flag
    {
        try {
            $result = $this->flagCache->get($key);
            if ($result == null) {
                return null;
            }

            $inflated = $result;
            return $inflated === false ? null : $inflated;
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key ${key}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * @param array $flags
     * @param IBanditReferenceIndexer|null $banditVariations
     * @throws EppoClientException
     */
    public function setUnifiedFlagConfiguration(array $flags, ?IBanditReferenceIndexer $banditVariations = null): void
    {
        try {
            // Clear stored config before setting data.
            $this->flagCache->clear();

            $this->setFlags($flags);
            if ($banditVariations == null) {
                $this->metadataCache->delete(self::BANDIT_VARIATION_KEY);
            } else {
                $this->metadataCache->set(self::BANDIT_VARIATION_KEY, $banditVariations);
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
        $keyed = [];
        array_walk($flags, function (Flag &$value) use (&$keyed) {
            $keyed[$value->key] = $value;
        });

        try {
            $this->flagCache->setMultiple($keyed);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key: " . $e->getMessage());
        }
    }

    public function getMetadata(string $key): mixed
    {
        try {
            return $this->metadataCache->get($key);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key: " . $e->getMessage());
        }

        return null;
    }

    public function getBanditReferenceIndexer(): IBanditReferenceIndexer
    {
        try {
            $data = $this->metadataCache->get(self::BANDIT_VARIATION_KEY);
            if ($data !== null) {
                return $data;
            }
            return BanditReferenceIndexer::empty();
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
        Validator::validateNotEqual(
            $key,
            self::BANDIT_VARIATION_KEY,
            'Unable to use reserved key, ' . self::BANDIT_VARIATION_KEY
        );
        try {
            $this->metadataCache->set($key, $metadata);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            syslog(LOG_WARNING, "[EPPO SDK] Illegal flag key: " . $e->getMessage());
        }
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function setBandits(array $bandits): void
    {
        try {
            $this->banditCache->clear();
            $keyed = [];
            array_walk($bandits, function (Bandit &$value) use (&$keyed) {
                $keyed[$value->banditKey] = $value;
            });
            $this->banditCache->setMultiple($keyed);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            throw InvalidConfigurationException::from($e);
        }
    }


    public function getBandit(string $banditKey): ?Bandit
    {
        try {
            return $this->banditCache->get($banditKey);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            throw new InvalidConfigurationException(
                "Illegal bandit key ${$banditKey}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
