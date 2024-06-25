<?php

namespace Eppo\Cache;

use Psr\SimpleCache\CacheInterface;

interface ICacheFactory {
    public function createCache(CacheType $type) : CacheInterface;
}