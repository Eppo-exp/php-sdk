<?php

namespace Eppo\Cache;

use Closure;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class NamespaceCache implements CacheInterface
{
    private readonly string $namespace;
    private CacheInterface $internalCache;
    private Closure $nestKeyCallback;

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

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->internalCache->get($this->nestKey($key), $default);
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->keys[$key] ??= $this->nestKey($key);
        return $this->internalCache->set($this->keys[$key], $value, $ttl);
    }

    public function delete(string $key): bool
    {
        $nested = $this->nestKey($key);
        if ($this->internalCache->delete($nested)) {
            unset($this->keys[$key]);
            return true;
        }
        return false;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clear(): bool
    {
        // `deleteMultiple` will nest the keys
        $keys = array_keys($this->keys);
        if ($this->deleteMultiple($keys)) {
            $this->keys = [];
            return true;
        } else {
            return false;
        }
    }

    public function getMultiple(iterable $keys, mixed $default = null): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->internalCache->get($this->nestKey($key), $default);
        }
        return $results;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = 3600): bool
    {
        $nestedKeyedValues = [];
        foreach ($values as $key => $value) {
            $this->keys[$key] = $this->nestKey($key);
            $nestedKeyedValues[$this->keys[$key]] = $value;
        }
        return $this->internalCache->setMultiple($nestedKeyedValues, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        if ($this->internalCache->deleteMultiple($this->nestKeys($keys))) {
            foreach ($keys as $key) {
                unset($this->keys[$key]);
            }
            return true;
        }
        return false;
    }

    public function has(string $key): bool
    {
        return $this->internalCache->has($this->nestKey($key));
    }
}
