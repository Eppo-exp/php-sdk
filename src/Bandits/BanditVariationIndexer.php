<?php

namespace Eppo\Bandits;

use Eppo\DTO\Bandit\BanditVariation;
use Eppo\Exception\InvalidConfigurationException;


class BanditVariationIndexer implements IBanditVariationIndexer
{

    /**
     * Map of flag key+variation value => bandit
     * $_banditFlags[$flagKey][$variationValue] = $banditKey;
     *
     * @var array<string, array<string, string>>
     */
    private array $_banditFlags = [];

    /**
     * @param array<string, array<BanditVariation>> $banditVariations
     * @throws InvalidConfigurationException
     */
    public function __construct(array $banditVariations)
    {
        foreach ($banditVariations as $banditKey => $listOfVariations) {
            foreach ($listOfVariations as $banditVariation) {
                if (!array_key_exists($banditVariation->flagKey, $this->_banditFlags)) {
                    $this->_banditFlags[$banditVariation->flagKey] = [];
                }

                // If there is already an entry for this flag/variation and it is not the current bandit key, throw exception.
                if (array_key_exists(
                        $banditVariation->variationValue,
                        $this->_banditFlags[$banditVariation->flagKey]
                    ) && $this->_banditFlags[$banditVariation->flagKey][$banditVariation->variationValue] !== $banditVariation->key) {
                    throw new InvalidConfigurationException(
                        "Variation '{$banditVariation->variationValue}' is already in use for flag '{$banditVariation->flagKey}'."
                    );
                }
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
