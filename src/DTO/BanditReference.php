<?php

namespace Eppo\DTO;

use Eppo\DTO\Bandit\BanditFlagVariation;

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
        return new BanditReference(
            modelVersion: $json['modelVersion'],
            flagVariations: array_map(fn($v) => BanditFlagVariation::fromJson($v), $json['flagVariations'])
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
