<?php

namespace Eppo\Cache;

use Psr\SimpleCache\CacheInterface;

class NamespaceCache implements CacheInterface
{
    private readonly string $namespace;
    private CacheInterface $internalCache;
    private \Closure $nestKeyCallback;

    const SEPARATOR = '_';

    public function __construct(CacheType $cacheType, CacheInterface $internalCache)
    {
        $this->namespace = $cacheType->value;
        $this->internalCache = $internalCache;
        $this->nestKeyCallback = function ($key): string {
            return $this->nestKey($key);
        };
    }

    private function nestKey(string $key): string
    {
        $newkey = $this->namespace . self::SEPARATOR . $key;
        return $newkey;
    }

    private function nestKeys(iterable $keys): array
    {
        return array_map($this->nestKeyCallback, [...$keys]);
    }

    public function get(string $key, mixed $default = null)
    {
        return $this->internalCache->get($this->nestKey($key));
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return $this->internalCache->set($this->nestKey($key), $value, $ttl);
    }

    public function delete(string $key)
    {
        return $this->internalCache->delete($this->nestKey($key));
    }

    public function clear(): bool
    {
        return $this->internalCache->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null)
    {
        return $this->getMultiple($this->nestKeys($keys), $default);
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null)
    {
        return $this->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys)
    {
        return $this->deleteMultiple($this->nestKeys($keys));
    }

    public function has(string $key)
    {
        return $this->internalCache->has($this->nestKey($key));
    }
}