<?php

namespace Eppo\DTO\Bandit;

interface IBanditVariationMap
{
    public function getBanditByVariation($flagKey, $variation): ?string;

    public function isBanditFlag($flagKey): bool;
}
