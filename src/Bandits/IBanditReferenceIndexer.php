<?php

namespace Eppo\Bandits;

/**
 * Indexes bandit variations by flag key and variation value for fast lookup.
 */
interface IBanditReferenceIndexer
{
    /**
     *  Gets the bandit key by flag key and variation.
     *
     * @param string $flagKey
     * @param string $variation
     * @return string|null
     */
    public function getBanditByVariation(string $flagKey, string $variation): ?string;

    /**
     * Determines whether the indexer has indexed any Bandit references.
     * @return bool
     */
    public function hasBandits(): bool;

    /**
     * @return string[] Bandit model version keys; these are universally unique across all Bandits.
     */
    public function getBanditModelKeys(): array;
}
