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

    /**
     * @param array $json
     * @return NumericAttributeCoefficient
     */
    public static function fromJson(array $json): NumericAttributeCoefficient
    {
        return new self($json['attributeKey'], $json['coefficient'], $json['missingValueCoefficient']);
    }

    /**
     * @param array $numericCoefficients
     * @return NumericAttributeCoefficient[]
     */
    public static function arrayFromJson(array $numericCoefficients): array
    {
        return array_map(fn($item) => self::fromJson($item), $numericCoefficients);
    }
}
