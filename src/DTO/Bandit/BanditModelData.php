<?php

namespace Eppo\DTO\Bandit;


class BanditModelData
{
    /**
     * @param float $gamma
     * @param array<string, ActionCoefficients> $coefficients
     * @param float $defaultActionScore
     * @param float $actionProbabilityFloor
     */
    public function __construct(
        public readonly float $gamma,
        public readonly array $coefficients,
        public readonly float $defaultActionScore,
        public readonly float $actionProbabilityFloor
    ) {
    }

    public static function fromJson($json): BanditModelData
    {
        return new BanditModelData(
            $json['gamma'],
            ActionCoefficients::arrayFromJson($json['coefficients']),
            $json['defaultActionScore'],
            $json['actionProbabilityFloor']
        );
    }
}
