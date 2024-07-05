<?php

namespace Eppo\DTO\Bandit;

class BanditVariation
{
    public function __construct(
        public readonly string $key,
        public readonly string $flagKey,
        public readonly string $variationKey,
        public readonly string $variationValue
    ) {
    }

    public static function fromJson($json): BanditVariation
    {
        return new self($json['key'], $json['flagKey'], $json['variationKey'], $json['variationValue']);
    }
}
