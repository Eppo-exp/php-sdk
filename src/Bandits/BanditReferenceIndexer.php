<?php

namespace Eppo\Bandits;

use Eppo\DTO\BanditFlagVariation;
use Eppo\DTO\BanditReference;
use Eppo\Exception\InvalidConfigurationException;

class BanditReferenceIndexer implements IBanditReferenceIndexer
{
    // magic methods to store just the underlying data when serialized to cache.
    public function __serialize(): array
    {
        return [
            'flagIndex' => $this->flagIndex,
            'banditReferences' => $this->banditReferences,
            'banditKeys' => $this->banditKeys,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->flagIndex = $data['flagIndex'];
        $this->banditReferences = $data['banditReferences'];
        $this->banditKeys = $data['banditKeys'];
    }

    /**
     * @var array Keys of bandits with non-empty FlagVariations
     */
    private array $banditKeys = [];

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
    private function setFlagVariationIndex(array $banditVariations): void
    {
        $banditKeys = [];
        $this->flagIndex = [];
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
                $banditKeys[] = $banditVariation->key;
            }
        }

        $this->banditKeys = array_unique($banditKeys);
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

        $bvi->setFlagVariationIndex($variations);
        $bvi->banditReferences = $banditReferences;
        return $bvi;
    }

    public function hasBandits(): bool
    {
        return count($this->flagIndex) > 0;
    }

    public function getBanditModelVersionReferences(): array
    {
        // banditKey => modelVersion strictly for bandits with references.
        return array_map(
            fn($banditReference) => $banditReference->modelVersion,
            array_filter(
                $this->banditReferences,
                fn($banditKey) => in_array($banditKey, $this->banditKeys),
                ARRAY_FILTER_USE_KEY
            )
        );
    }
}
