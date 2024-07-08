<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Cache\CacheType;
use Eppo\Cache\NamespaceCache;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Flag;
use Eppo\Exception\InvalidConfigurationException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ConfigurationStore implements IConfigurationStore
{
    private CacheInterface $rootCache;
    private CacheInterface $flagCache;
    private CacheInterface $banditCache;
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
        $this->banditCache = new NamespaceCache(CacheType::BANDIT, $cache);
        $this->metadataCache = new NamespaceCache(CacheType::META, $cache);
    }

    /**
     * @param string $key
     * @return Flag|null
     * @throws InvalidConfigurationException
     */
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
            throw new InvalidConfigurationException("Illegal flag key ${key}: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function setFlag(Flag $flag): void
    {
        try {
            $this->flagCache->set($flag->key, serialize($flag));
        } catch (InvalidArgumentException $e) {
            $key = $flag->key;

            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            throw new InvalidConfigurationException("Illegal flag key ${key}: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array $flags
     * @param array $bandits
     * @param BanditVariationIndexer|null $banditVariations
     * @return void
     * @throws InvalidConfigurationException
     */
    public function setConfigurations(
        array $flags,
        array $bandits,
        BanditVariationIndexer $banditVariations = null
    ): void {
        try {
            // Clear all stored config before setting data.
            $this->rootCache->clear();

            // Set last fetch timestamp.
            $this->metadataCache->set(self::FLAG_TIMESTAMP, time());
            $this->setFlags($flags);
            $this->setBandits($bandits);
            $this->metadataCache->set(self::BANDIT_VARIATION_KEY, serialize($banditVariations));
        } catch (InvalidArgumentException $e) {
            throw InvalidConfigurationException::from($e);
        }
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function setFlags(array $flags): void
    {
        foreach ($flags as $flag) {
            $this->setFlag($flag);
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
     * @return BanditVariationIndexer
     * @throws InvalidConfigurationException
     */
    public function getBanditVariations(): BanditVariationIndexer
    {
        try {
            return unserialize($this->metadataCache->get(self::BANDIT_VARIATION_KEY));
        } catch (InvalidArgumentException $e) {
            // We know that the key does not contain illegal characters, so we should not end up here.
            throw InvalidConfigurationException::From($e);
        }
    }

    /**
     * @param Bandit[] $bandits
     * @return void
     */
    private function setBandits(array $bandits): void
    {
        foreach ($bandits as $bandit) {
            try {
                $this->banditCache->set($bandit->banditKey, serialize($bandit));
            } catch (InvalidArgumentException $e) {
                $message = $e->getMessage();
                syslog(LOG_WARNING, "[Eppo SDK]: Error \"{$message}\" encountered while setting bandit");
            }
        }
    }


    /**
     * @param string $banditKey
     * @return Bandit|null
     * @throws InvalidConfigurationException
     */
    public function getBandit(string $banditKey): ?Bandit
    {
        try {
            return unserialize($this->banditCache->get($banditKey));
        } catch (InvalidArgumentException $e) {
            // Simple cache throws exceptions when a keystring is not a legal value (characters {}()/@: are illegal)
            throw new InvalidConfigurationException(
                "Illegal bandit key ${$banditKey}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
