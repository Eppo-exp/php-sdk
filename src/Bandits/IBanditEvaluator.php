<?php

namespace Eppo\Bandits;

use BanditEvaluation;
use Eppo\DTO\Bandit\BanditModelData;
use Eppo\DTO\Bandit\ContextAttributes;

interface IBanditEvaluator
{
    /**
     * @param string $flagKey
     * @param ContextAttributes $subject
     * @param array<string, ContextAttributes> $actionsWithContexts
     * @param BanditModelData $banditModel
     * @return BanditEvaluation
     */
    public function evaluateBandit(
        string $flagKey,
        ContextAttributes $subject,
        array $actionsWithContexts,
        BanditModelData $banditModel
    ): BanditEvaluation;
}
