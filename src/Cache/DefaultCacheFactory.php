<?php

namespace Eppo\Cache;

use Exception;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

class DefaultCacheFactory
{
    /**
     * @throws Exception
     */
    public static function create(): CacheInterface
    {
        $psr6Cache = new FilesystemAdapter(
            ".EppoCache"
        );

        return new Psr16Cache($psr6Cache);
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
