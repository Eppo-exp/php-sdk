<?php

namespace Eppo\Bandits;

use Eppo\DTO\Bandit\BanditVariation;
use Eppo\Exception\InvalidConfigurationException;

class BanditVariationIndexer implements IBanditVariationIndexer
{
    // By just serializing the indexed variations, we cut down on cache size.
    public function __serialize(): array
    {
        return $this->banditFlags;
    }

    public function __unserialize(array $data): void
    {
        $this->banditFlags = $data;
    }

    /**
     * Map of flag key+variation value => bandit
     * $_banditFlags[$flagKey][$variationValue] = $banditKey;
     *
     * @var array<string, array<string, string>>
     */
    private array $banditFlags = [];


    private function __construct()
    {
    }

    /**
     * @param array<string, array<BanditVariation>> $banditVariations
     * @throws InvalidConfigurationException
     */
    private function setVariations(array $banditVariations): void
    {
        foreach ($banditVariations as $listOfVariations) {
            foreach ($listOfVariations as $banditVariation) {
                // If this flag key has not already been indexed, index it now
                $flagKey = $banditVariation->flagKey;
                $this->banditFlags[$flagKey] ??= [];

                // If there is already an entry for this flag/variation, and it is not the current bandit key,
                // throw exception.
                $variationValue = $banditVariation->variationValue;
                if (
                    array_key_exists(
                        $variationValue,
                        $this->banditFlags[$flagKey]
                    ) && $this->banditFlags[$flagKey][$variationValue] !== $banditVariation->banditKey
                ) {
                    throw new InvalidConfigurationException(
                        "Ambiguous mapping for flag: '{$flagKey}', variation: '{$variationValue}'."
                    );
                }

                // Update the index for this triple (flagKey, variationValue) => banditKey
                $this->banditFlags[$flagKey][$variationValue] = $banditVariation->banditKey;
            }
        }
    }

    public function getBanditByVariation($flagKey, $variation): ?string
    {
        return $this->banditFlags[$flagKey][$variation] ?? null;
    }

    public function isBanditFlag($flagKey): bool
    {
        return isset($this->banditFlags[$flagKey]);
    }

    public static function empty(): BanditVariationIndexer
    {
        return new BanditVariationIndexer();
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function from(array $banditVariations): BanditVariationIndexer
    {
        $bvi = new BanditVariationIndexer();
        $bvi->setVariations($banditVariations);
        return $bvi;
    }

    public function hasBandits(): bool
    {
        return count($this->banditFlags) > 0;
    }
}
