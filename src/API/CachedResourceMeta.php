<?php

namespace Eppo\API;

class CachedResourceMeta
{
    public function __construct(
        public readonly int $timestamp,
        public readonly ?string $ETag
    ) {
    }

    public function getCacheAgeSeconds(): int
    {
        return time() - $this->timestamp;
    }
}
