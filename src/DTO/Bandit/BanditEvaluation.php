<?php

use Eppo\DTO\Bandit\AttributeSet;

class BanditEvaluation
{
    public function __construct(
        public readonly string $flagKey,
        public readonly string $subjectKey,
        public readonly AttributeSet $subjectAttributes,
        public readonly string $selectedAction,
        public readonly ?AttributeSet $actionAttributes,
        public readonly float $actionScore,
        public readonly float $actionWeight,
        public readonly float $gamma,
        public readonly float $optimalityGap
    ) {
    }
}
