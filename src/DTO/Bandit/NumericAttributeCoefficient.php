<?php

namespace Eppo\DTO\Bandit;

class NumericAttributeCoefficient
{
    public function __construct(
        public readonly string $attributeKey,
        public readonly float $coefficient,
        public readonly float $missingValueCoefficient
    ) {
    }

    public static function fromJson($json): NumericAttributeCoefficient
    {
        return new self($json['attributeKey'], $json['coefficient'], $json['missingValueCoefficient']);
    }

    public static function arrayFromJson($numericCoefficients): array
    {
        return array_map(fn($item) => self::fromJson($item), $numericCoefficients);
    }
}
