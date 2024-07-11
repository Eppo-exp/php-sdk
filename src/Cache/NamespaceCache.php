<?php

namespace Eppo\Cache;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class NamespaceCache implements CacheInterface
{
    private readonly string $namespace;
    private CacheInterface $internalCache;
    private \Closure $nestKeyCallback;

    private array $keys = [];

    private const SEPARATOR = '_';

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
        $this->keys[$key] = $key;
        return $this->internalCache->set($this->nestKey($key), $value, $ttl);
    }

    public function delete(string $key)
    {
        unset($this->keys[$key]);
        return $this->internalCache->delete($this->nestKey($key));
    }

    public function clear(): bool
    {
        // Only delete values with the correct prefix
        foreach ($this->keys as $key) {
            try {
                $this->internalCache->delete($this->nestKey($key));
            } catch (InvalidArgumentException $e) {
            }
            unset($this->keys[$key]);
        }
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): array
    {
        return $this->internalCache->getMultiple($this->nestKeys($keys), $default);
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->keys[$key] = $key;
        }
        return $this->internalCache->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->keys[$key]);
        }
        return $this->internalCache->deleteMultiple($this->nestKeys($keys));
    }

    public function has(string $key): bool
    {
        return $this->internalCache->has($this->nestKey($key));
    }
}
