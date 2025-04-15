<?php

namespace Eppo\Tests\Config;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class MockCache implements CacheInterface
{
    private array $cache = [];
    private bool $throwOnGet = false;
    private bool $throwOnSet = false;

    public function __construct(bool $throwOnGet = false, bool $throwOnSet = false)
    {
        $this->throwOnGet = $throwOnGet;
        $this->throwOnSet = $throwOnSet;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->throwOnGet) {
            throw new class extends \Exception implements InvalidArgumentException {
            };
        }

        return $this->cache[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        if ($this->throwOnSet) {
            throw new class extends \Exception implements InvalidArgumentException {
            };
        }

        $this->cache[$key] = $value;
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    public function getCache(): array
    {
        return $this->cache;
    }
}
