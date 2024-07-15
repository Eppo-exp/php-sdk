<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Bandits\IBanditVariationIndexer;
use Eppo\Cache\CacheType;
use Eppo\Cache\NamespaceCache;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Flag;
use Eppo\Exception\EppoClientException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Validator;
use Eppo\Exception\InvalidConfigurationException;
use Psr\SimpleCache\CacheInterface;

class ConfigurationStore implements IConfigurationStore
{
    private CacheInterface $flagCache;
    private CacheInterface $banditCache;
    private CacheInterface $metadataCache;
    private const FLAG_META = "flagResourceMetadata";
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

    public function getMetadata(string $key): ?string
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
     * @return BanditVariationIndexer
     * @throws InvalidConfigurationException
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

    public function setBanditModels(array $bandits):  void {
        try {
            // Clear all stored config before setting data.
            $this->rootCache->clear();

            // Set last fetch timestamp.
            //$this->metadataCache->set(self::FLAG_TIMESTAMP, time());
            $this->setBandits($bandits);

        } catch (InvalidArgumentException $e) {
            throw InvalidConfigurationException::from($e);
        }
    }

}
