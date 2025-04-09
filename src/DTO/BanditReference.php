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

    public static function fromJson(mixed $json): BanditReference
    {
        $flagVariations = [];
        if (isset($json['flagVariations']) && is_array($json['flagVariations'])) {
            foreach ($json['flagVariations'] as $variation) {
                $flagVariations[] = BanditFlagVariation::fromJson($variation);
            }
        }

        return new BanditReference(
            modelVersion: $json['modelVersion'],
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
