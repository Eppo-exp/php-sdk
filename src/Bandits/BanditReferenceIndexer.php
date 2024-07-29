<?php

namespace Eppo\Bandits;

use Eppo\DTO\Bandit\BanditFlagVariation;
use Eppo\DTO\BanditReference;
use Eppo\Exception\InvalidConfigurationException;

class BanditReferenceIndexer implements IBanditReferenceIndexer
{
    // By just serializing the indexed variations, we cut down on cache size.
    public function __serialize(): array
    {
        return [
            'flagIndex' => $this->flagIndex,
            'banditReferences' => $this->banditReferences
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->flagIndex = $data['flagIndex'];
        $this->banditReferences = $data['banditReferences'];
    }

    /**
     * Map of flag key+variation value => bandit
     * $_banditFlags[$flagKey][$variationValue] = $banditKey;
     *
     * @var array<string, array<string, string>>
     */
    private array $flagIndex = [];

    /**
     * @var array<string, BanditReference>
     */
    private array $banditReferences = [];


    private function __construct()
    {
    }

    /**
     * @param array<string, array<BanditFlagVariation>> $banditVariations
     * @throws InvalidConfigurationException
     */
    private function setVariations(array $banditVariations): void
    {
        foreach ($banditVariations as $listOfVariations) {
            foreach ($listOfVariations as $banditVariation) {
                // If this flag key has not already been indexed, index it now
                $flagKey = $banditVariation->flagKey;
                $this->flagIndex[$flagKey] ??= [];

                // If there is already an entry for this flag/variation, and it is not the current bandit key,
                // throw exception.
                $variationValue = $banditVariation->variationValue;
                if (
                    array_key_exists(
                        $variationValue,
                        $this->flagIndex[$flagKey]
                    ) && $this->flagIndex[$flagKey][$variationValue] !== $banditVariation->key
                ) {
                    throw new InvalidConfigurationException(
                        "Ambiguous mapping for flag: '{$flagKey}', variation: '{$variationValue}'."
                    );
                }

                // Update the index for this triple (flagKey, variationValue) => banditKey
                $this->flagIndex[$flagKey][$variationValue] = $banditVariation->key;
            }
        }
    }

    /**
     * @param string $flagKey
     * @param string $variation
     * @return string|null
     */
    public function getBanditByVariation(string $flagKey, string $variation): ?string
    {
        return $this->flagIndex[$flagKey][$variation] ?? null;
    }

    public static function empty(): IBanditReferenceIndexer
    {
        return new BanditReferenceIndexer();
    }

    /**
     * @param array<string, BanditReference> $banditReferences
     * @return IBanditReferenceIndexer
     * @throws InvalidConfigurationException
     */
    public static function from(array $banditReferences): IBanditReferenceIndexer
    {
        $bvi = new BanditReferenceIndexer();

        $variations = array_map(
            function ($banditVariation) {
                return $banditVariation->flagVariations;
            },
            $banditReferences
        );

        $bvi->setVariations($variations);
        return $bvi;
    }

    public function hasBandits(): bool
    {
        return count($this->flagIndex) > 0;
    }
}
