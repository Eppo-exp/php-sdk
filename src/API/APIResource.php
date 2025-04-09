<?php

namespace Eppo\API;

class APIResource
{
    public function __construct(
        public readonly ?string $body,
        public readonly bool $isModified,
        public readonly ?string $eTag
    ) {
    }
}
