<?php

namespace Eppo\Cache;

use Exception;
use Psr\SimpleCache\CacheInterface;
use Sarahman\SimpleCache\FileSystemCache;

class DefaultCacheFactory implements ICacheFactory
{

    public function __construct()
    {
    }

    /**
     * @throws Exception
     */
    public function createCache(CacheType $type): CacheInterface
    {
        return new FileSystemCache(__DIR__ . '/../../cache/' . $type->value);
    }

    /**
     * Utility method to clear caches.
     *
     * By virtue of using a persistent cache store, some uses cases, such as tests, can cause data to persist in an
     * unhelpful manner, requiring the ability to flush the caches.
     *
     * @return void
     */
    public static function clearCaches(): void
    {
        $factory = new DefaultCacheFactory();
        foreach ([CacheType::FLAG, CacheType::META] as $type) {
            try {
                $factory->createCache($type)->clear();
            } catch (Exception $e) {
            }
        }
    }
}