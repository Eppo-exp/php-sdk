<?php

namespace Eppo\DTO;

class Split
{
    /**
     * @param string $variationKey
     * @param Shard[] $shards
     * @param array $extraLogging
     */
    public function __construct(
        public string $variationKey,
        public array $shards,
        public array $extraLogging
    ) {
    }
}
