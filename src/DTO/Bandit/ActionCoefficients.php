<?php

namespace Eppo\DTO\Bandit;

class ActionCoefficients
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
        public readonly string $actionKey,
        public readonly float $intercept,
        public readonly array $subjectNumericCoefficients = [],
        public readonly array $subjectCategoricalCoefficients = [],
        public readonly array $actionNumericCoefficients = [],
        public readonly array $actionCategoricalCoefficients = []
    ) {
    }

    public static function arrayFromJson($coefficients): array
    {
        $res = [];
        foreach ($coefficients as $key => $coefficient) {
            $res[$key] = ActionCoefficients::fromJson($coefficient);
        }
        return $res;
    }

    public static function fromJson($json): ActionCoefficients
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
