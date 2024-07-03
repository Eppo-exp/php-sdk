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
     * @param $flagKey
     * @param $variation
     * @return string|null
     */
    public function getBanditByVariation($flagKey, $variation): ?string;

    /**
     * Determines whether the give flag is associated with any bandits.
     *
     * @param $flagKey
     * @return bool
     */
    public function isBanditFlag($flagKey): bool;
}
