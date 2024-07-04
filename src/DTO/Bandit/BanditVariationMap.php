<?php

namespace Eppo\DTO\Bandit;

class BanditVariationMap implements IBanditVariationMap
{
    /**
     * Map of flag key+variation value => bandit
     * $_banditFlags[$flagKey][$variationValue] = $banditKey;
     *
     * @var array<string, array<string, string>>
     */
    private array $banditFlags = [];

    /**
     * @param array $banditVariations
     */
    public function __construct(array $banditVariations)
    {
        foreach ($banditVariations as $key => $json) {
            $banditVariation = BanditVariation::fromJson($json);
            $flagKey = $banditVariation->flagKey;
            if (!array_key_exists($flagKey, $this->banditFlags)) {
                $this->banditFlags[$flagKey] = [];
            }

            $variationValue = $banditVariation->variationValue;
            if (!in_array($variationValue, $this->banditFlags[$flagKey])) {
                $this->banditFlags[$flagKey][$variationValue] = $banditVariation->key;
            }
        }
    }

    public function getBanditByVariation($flagKey, $variation): ?string
    {
        return $this->banditFlags[$flagKey][$variation] ?? null;
    }

    public function isBanditFlag($flagKey): bool
    {
        return array_key_exists($flagKey, $this->banditFlags);
    }
}
