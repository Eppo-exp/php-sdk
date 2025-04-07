<?php

namespace Eppo\Config;

use Eppo\Bandits\IBanditReferenceIndexer;
use Eppo\Bandits\IBandits;
use Eppo\Flags\IFlags;

class Configuration implements IFlags, IBandits
{
    public function __construct(
        public readonly array $flags,
        public readonly array $bandits,
        public readonly IBanditReferenceIndexer $banditReferenceIndexer,
        public readonly string $eTag,
        public readonly int $fetchedAt
    ) {
    }

    public function getFlag(string $key): ?Flag
    {
        return $this->flags[$key] ?? null;
    }

    public function getBandit(string $banditKey): ?Bandit\Bandit
    {
        return $this->bandits[$banditKey] ?? null;
    }

    public function getBanditReferenceIndexer(): IBanditReferenceIndexer
    {
        return $this->banditReferenceIndexer;
    }

    public function getBanditByVariation(string $flagKey, string $variation)
    {
    }
}
