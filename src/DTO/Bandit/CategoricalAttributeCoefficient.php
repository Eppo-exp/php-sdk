<?php

namespace Eppo\DTO\Bandit;

class CategoricalAttributeCoefficient
{
    /**
     * @param string $attributeKey
     * @param float $missingValueCoefficient
     * @param array<string, float> $valueCoefficients
     */
    public function __construct(
        public readonly string $attributeKey,
        public readonly float $missingValueCoefficient,
        public readonly array $valueCoefficients
    ) {
    }

    /**
     * @param array $arr
     * @return CategoricalAttributeCoefficient
     */
    public static function fromArray(array $arr): CategoricalAttributeCoefficient
    {
        return new self($arr['attributeKey'], $arr['missingValueCoefficient'], $arr['valueCoefficients']);
    }

    /**
     * @param array $categoricalCoefficients
     * @return CategoricalAttributeCoefficient[]
     */
    public static function parseArray(array $categoricalCoefficients): array
    {
        return array_map(fn($item) => self::fromArray($item), $categoricalCoefficients);
    }
}
