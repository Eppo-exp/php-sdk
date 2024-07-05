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
     * @param array $json
     * @return CategoricalAttributeCoefficient
     */
    public static function fromJson(array $json): CategoricalAttributeCoefficient
    {
        return new self($json['attributeKey'], $json['missingValueCoefficient'], $json['valueCoefficients']);
    }

    /**
     * @param array $categoricalCoefficients
     * @return CategoricalAttributeCoefficient[]
     */
    public static function arrayFromJson(array $categoricalCoefficients): array
    {
        return array_map(fn($item) => self::fromJson($item), $categoricalCoefficients);
    }
}
