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
            'activeBanditKeys' => $this->activeBanditKeys,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->flagIndex = $data['flagIndex'];
        $this->banditReferences = $data['banditReferences'];
        $this->activeBanditKeys = $data['activeBanditKeys'];
    }

    /**
     * @var array Keys of bandits with non-empty FlagVariations
     */
    private array $activeBanditKeys = [];

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
     * Indexes the bandit flag variations by Flag,Variation => Bandit and sets `banditModels`.
     * @param array<string, array<BanditFlagVariation>> $banditVariations
     * @throws InvalidConfigurationException
     */
    private function indexBanditFlags(array $banditVariations): void
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

                // Gather every bandit key referenced, dedupe later.
                $banditKeys[] = $banditVariation->key;
            }
        }

        // array_unique preserves array keys so duplicate entries that are dropped leave holes in the list of numeric
        // keys, then `array_values` builds a new array from the values with reset numeric keys.
        $this->activeBanditKeys = array_values(array_unique($banditKeys));
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

        $bvi->indexBanditFlags($variations);
        $bvi->banditReferences = $banditReferences;
        return $bvi;
    }

    public function hasBandits(): bool
    {
        return count($this->flagIndex) > 0;
    }

    public function getBanditModelKeys(): array
    {
        // banditKey => modelVersion strictly for bandits with references.
        return array_map(
            fn($banditKey) => $this->banditReferences[$banditKey]->modelVersion,
            $this->activeBanditKeys
        );
    }
}
