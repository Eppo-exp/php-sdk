<?php

namespace Eppo\DTO;

class BanditReference
{
    /**
     * @param string $modelVersion
     * @param BanditFlagVariation[] $flagVariations
     */
    public function __construct(
        public readonly string $modelVersion,
        public readonly array $flagVariations
    ) {
    }

    public static function fromArray(mixed $arr): BanditReference
    {
        $flagVariations = [];
        if (isset($arr['flagVariations']) && is_array($arr['flagVariations'])) {
            foreach ($arr['flagVariations'] as $variation) {
                $flagVariations[] = BanditFlagVariation::fromArray($variation);
            }
        }

        return new BanditReference(
            modelVersion: $arr['modelVersion'],
            flagVariations: $flagVariations
        );
    }

    public function __serialize(): array
    {
        return ['modelVersion' => $this->modelVersion, 'flagVariations' => $this->flagVariations];
    }

    public function __unserialize(array $data): void
    {
        $this->modelVersion = $data['modelVersion'];
        $this->flagVariations = $data['flagVariations'];
    }
}
