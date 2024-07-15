<?php

namespace Eppo\Bandits;

/**
 * Indexes bandit variations by flag key and variation value for fast lookup.
 */
interface IBanditVariationIndexer
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
     * Determines whether the given flag is associated with any bandits.
     *
     * @param string $flagKey
     * @return bool
     */
    public function isBanditFlag(string $flagKey): bool;

    /**
     * Determines whether the indexer has indexed any Bandit references.
     * @return bool
     */
    public function hasBandits(): bool;
}
