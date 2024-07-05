<?php

namespace Eppo\DTO\Bandit;

use Eppo\Exception\InvalidArgumentException;

class ActionCoefficients
{
    /**
     * @param string $actionKey
     * @param float $intercept
     * @param NumericAttributeCoefficient[] $subjectNumericCoefficients
     * @param CategoricalAttributeCoefficient[] $subjectCategoricalCoefficients
     * @param NumericAttributeCoefficient[] $actionNumericCoefficients
     * @param CategoricalAttributeCoefficient[] $actionCategoricalCoefficients
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly string $actionKey,
        public readonly float $intercept,
        public readonly array $subjectNumericCoefficients = [],
        public readonly array $subjectCategoricalCoefficients = [],
        public readonly array $actionNumericCoefficients = [],
        public readonly array $actionCategoricalCoefficients = []
    ) {
        foreach ([...$this->subjectNumericCoefficients, ...$this->actionNumericCoefficients] as $numericCoefficient) {
            if (!($numericCoefficient instanceof NumericAttributeCoefficient)) {
                throw new InvalidArgumentException("Unexpected non-numeric attribute coefficient encountered");
            }
        }
        foreach (
            [
                ...$this->subjectCategoricalCoefficients,
                ...$this->actionCategoricalCoefficients
            ] as $categoricalCoefficient
        ) {
            if (!($categoricalCoefficient instanceof CategoricalAttributeCoefficient)) {
                throw new InvalidArgumentException("Unexpected non-categorical attribute coefficient encountered");
            }
        }
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
