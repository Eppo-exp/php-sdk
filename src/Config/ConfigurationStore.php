<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Cache\CacheType;
use Eppo\Cache\NamespaceCache;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Flag;
use Eppo\Exception\EppoClientException;
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
     * @param array $flags
     * @param Bandit[] $bandits
     * @param BanditVariationIndexer|null $banditVariations
     * @throws EppoClientException
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
            throw EppoClientException::from($e);
        }
    }

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
     * @throws EppoClientException
     */
    public function getBanditVariations(): BanditVariationIndexer
    {
        try {
            return unserialize($this->metadataCache->get(self::BANDIT_VARIATION_KEY));
        } catch (InvalidArgumentException $e) {
            // We know that the key does not contain illegal characters, so we should not end up here.
            throw EppoClientException::From($e);
        }
    }

    private function setBandits(array $bandits): void
    {
        foreach ($bandits as $bandit) {
            try {
                $this->banditCache->set($bandit->key, serialize($bandit));
            } catch (InvalidArgumentException $e) {
                $message = $e->getMessage();
                syslog(LOG_WARNING, "[Eppo SDK]: Error \"{$message}\" encountered while setting bandit");
            }
        }
    }

    /**
     * @throws EppoClientException
     */
    public function getBandit(string $banditKey): array
    {
        try {
            return $this->banditCache->get($banditKey);
        } catch (InvalidArgumentException $e) {
            throw EppoClientException::From($e);
        }
    }
}
