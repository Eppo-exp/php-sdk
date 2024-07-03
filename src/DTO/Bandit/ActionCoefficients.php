<?php

namespace Eppo\DTO\Bandit;

use Eppo\DTO\IDeserializable;

class ActionCoefficients implements IDeserializable
{
    /**
     * @param string $actionKey
     * @param float $intercept
     * @param NumericAttributeCoefficient[] $subjectNumericCoefficients
     * @param CategoricalAttributeCoefficient[] $subjectCategoricalCoefficients
     * @param NumericAttributeCoefficient[] $actionNumericCoefficients
     * @param CategoricalAttributeCoefficient[] $actionCategoricalCoefficients
     */
    public function __construct(
        public string $actionKey,
        public float $intercept,
        public array $subjectNumericCoefficients = [],
        public array $subjectCategoricalCoefficients = [],
        public array $actionNumericCoefficients = [],
        public array $actionCategoricalCoefficients = []
    ) {
    }

    public static function arrayFromJson($coefficients)
    {
        $res = [];
        foreach ($coefficients as $key => $coefficient) {
            $res[$key] = ActionCoefficients::fromJson($coefficient);
        }
        return $res;
    }

    public static function fromJson($json): IDeserializable
    {
        return new ActionCoefficients(
            $json['actionKey'],
            $json['intercept'],
            NumericAttributeCoefficient::arrayFromJson($json['subjectNumericCoefficients']),
            CategoricalAttributeCoefficient::arrayFromJson($json['subjectCategoricalCoefficients']),
            NumericAttributeCoefficient::arrayFromJson($json['actionNumericCoefficients']),
            CategoricalAttributeCoefficient::arrayFromJson($json['actionCategoricalCoefficients'])
        );
    }
}
