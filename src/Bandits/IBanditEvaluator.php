<?php

namespace Eppo\Bandits;

use Eppo\DTO\Bandit\AttributeSet;
use Eppo\DTO\Bandit\BanditEvaluation;
use Eppo\DTO\Bandit\BanditModelData;

interface IBanditEvaluator
{
    /**
     * @param string $flagKey
     * @param string $subjectKey
     * @param AttributeSet $subject
     * @param array<string, AttributeSet> $actionsWithContexts
     * @param BanditModelData $banditModel
     * @return BanditEvaluation
     */
    public function evaluateBandit(
        string $flagKey,
        string $subjectKey,
        AttributeSet $subject,
        array $actionsWithContexts,
        BanditModelData $banditModel
    ): BanditEvaluation;
}
