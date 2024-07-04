<?php

namespace Eppo\DTO\Bandit;

class CategoricalAttributeCoefficient
{
    public string $attributeKey;
    public float $missingValueCoefficient;

    /**
     * @var double[]
     */
    public array $valueCoefficients;

    public function __construct(string $attributeKey, float $missingValueCoefficient, array $valueCoefficients)
    {
        $this->attributeKey = $attributeKey;
        $this->missingValueCoefficient = $missingValueCoefficient;
        $this->valueCoefficients = $valueCoefficients;
    }

    public static function fromJson($json): CategoricalAttributeCoefficient
    {
        return new self($json['attributeKey'], $json['missingValueCoefficient'], $json['valueCoefficients']);
    }

    public static function arrayFromJson($categoricalCoefficients)
    {
        return array_map(fn($item) => self::fromJson($item), $categoricalCoefficients);
    }
}
