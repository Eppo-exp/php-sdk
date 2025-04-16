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
     * @param array $arr
     * @return NumericAttributeCoefficient
     */
    public static function fromArray(array $arr): NumericAttributeCoefficient
    {
        return new self($arr['attributeKey'], $arr['coefficient'], $arr['missingValueCoefficient']);
    }

    /**
     * @param array $numericCoefficients
     * @return NumericAttributeCoefficient[]
     */
    public static function parseArray(array $numericCoefficients): array
    {
        return array_map(fn($item) => self::fromArray($item), $numericCoefficients);
    }
}
