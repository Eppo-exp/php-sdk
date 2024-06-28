<?php

namespace Eppo\Cache;

use Exception;
use Psr\SimpleCache\CacheInterface;
use Sarahman\SimpleCache\FileSystemCache;

class DefaultCacheFactory
{
    /**
     * @throws Exception
     */
    public static  function create(): CacheInterface
    {
        return new FileSystemCache(__DIR__ . '/../../cache/');
    }

    /**
     * Utility method to clear caches.
     *
     * By virtue of using a persistent cache store, some uses cases, such as tests, can cause data to persist in an
     * unhelpful manner, requiring the ability to flush the caches.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        try {
            DefaultCacheFactory::create()->clear();
        } catch (Exception $e) {
        }
    }
}
