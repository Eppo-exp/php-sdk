<?php
namespace Eppo\DTO\Bandit;

class BanditVariationMap implements IBanditVariationMap {

    /**
     * Map of flag key+variation value => bandit
     * $_banditFlags[$flagKey][$variationValue] = $banditKey;
     *
     * @var array<string, array<string, string>>
     */
    private array $_banditFlags = [];

    /**
     * @param array $banditVariations
     */
    public function __construct(array $banditVariations) {
        foreach ($banditVariations as $key => $json) {
            $banditVariation = BanditVariation::fromJson($json);
            if (!array_key_exists($banditVariation->flagKey, $this->_banditFlags)) {
                $this->_banditFlags[$banditVariation->flagKey] = [];
            }
            if (!in_array($banditVariation->variationValue, $this->_banditFlags[$banditVariation->flagKey])) {
                $this->_banditFlags[$banditVariation->flagKey][$banditVariation->variationValue] = $banditVariation->key;
            }
        }
    }

    public function getBanditByVariation($flagKey, $variation): ?string
    {
        return $this->_banditFlags[$flagKey][$variation] ?? null;
    }

    public function isBanditFlag($flagKey): bool
    {
        return array_key_exists($flagKey, $this->_banditFlags);
    }
}
