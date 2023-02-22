<?php

namespace Eppo;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class ConfigurationStore
{
    /** @var CacheInterface */
    private $cache;

    /**
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache) {
        $this->cache = $cache;
    }

    /**
     * @param string $key
     * @return array
     * @throws InvalidArgumentException
     */
    public function getConfiguration(string $key): array {
        $value = $this->cache->get($key);
        return $value ? json_decode($value, true) : [];
    }

    /**
     * @param array $configs
     * @return void
     * @throws InvalidArgumentException
     */
    public function setConfigurations(array $configs) {
        foreach ($configs as $key => $value) {
            $this->cache->set($key, json_encode($value), 200);
        }
    }
}
