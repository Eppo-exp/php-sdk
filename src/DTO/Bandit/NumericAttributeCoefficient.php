<?php

namespace Eppo\DTO\Bandit;

use Eppo\DTO\IDeserializable;

class NumericAttributeCoefficient implements IDeserializable {
    public string $AttributeKey;
    public float $Coefficient;
    public float $MissingValueCoefficient;

    public function __construct(string $attributeKey, float $coefficient, float $missingValueCoefficient) {
        $this->AttributeKey = $attributeKey;
        $this->Coefficient = $coefficient;
        $this->MissingValueCoefficient = $missingValueCoefficient;
    }

    public static function fromJson($json): IDeserializable
    {
        return new self($json['attributeKey'], $json['coefficient'], $json['missingValueCoefficient']);
    }

    public static function arrayFromJson($numericCoefficients): array
    {
        return array_map(fn($item) => self::fromJson($item), $numericCoefficients);
    }
}
