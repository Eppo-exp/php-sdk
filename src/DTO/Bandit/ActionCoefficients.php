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
        // Since PHP doesn't enforce array typing and this dev spent an unfortunate amount of time debugging, we ensure
        // coefficients passed are of the correct type.
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

    /**
     * @param array $coefficients
     * @return array
     * @throws InvalidArgumentException
     */
    public static function arrayFromJson(array $coefficients): array
    {
        $res = [];
        foreach ($coefficients as $key => $coefficient) {
            $res[$key] = ActionCoefficients::fromJson($coefficient);
        }
        return $res;
    }

    /**
     * @param array $json
     * @return ActionCoefficients
     * @throws InvalidArgumentException
     */
    public static function fromJson(array $json): ActionCoefficients
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
