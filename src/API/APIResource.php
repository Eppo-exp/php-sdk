<?php

namespace Eppo\API;

class APIResource
{
    public readonly CachedResourceMeta $meta;

    public function __construct(
        public readonly ?string $body,
        int $timestamp,
        public readonly bool $isModified,
        ?string $ETag
    ) {
        $this->meta = new CachedResourceMeta($timestamp, $ETag);
    }
}
