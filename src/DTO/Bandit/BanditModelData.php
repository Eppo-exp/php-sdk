<?php

namespace Eppo\DTO\Bandit;

use Eppo\DTO\IDeserializable;

class BanditModelData implements IDeserializable
{
    /**
     * @param float $gamma
     * @param array<string, ActionCoefficients> $coefficients
     * @param float $defaultActionScore
     * @param float $actionProbabilityFloor
     */
    public function __construct(
        public float $gamma,
        public array $coefficients,
        public float $defaultActionScore,
        public float $actionProbabilityFloor
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
