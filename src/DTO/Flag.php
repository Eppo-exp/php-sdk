<?php

namespace Eppo\DTO;

class Flag
{
    /**
     * @param string $key
     * @param bool $enabled
     * @param Allocation[] $allocations
     * @param VariationType $variationType
     * @param Variation[] $variations
     * @param int $totalShards
     */
    public function __construct(
        public string $key, public bool $enabled, public array $allocations, public VariationType $variationType, public array $variations,
        public int $totalShards)
    {
    }
}
