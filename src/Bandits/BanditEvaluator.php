<?php

namespace Eppo\Bandits;

use Eppo\DTO\Bandit\ActionCoefficients;
use Eppo\DTO\Bandit\AttributeSet;
use Eppo\DTO\Bandit\BanditEvaluation;
use Eppo\DTO\Bandit\BanditModelData;
use Eppo\DTO\Bandit\ContextAttributes;
use Eppo\DTO\Bandit\NumericAttributeCoefficient;
use Eppo\Exception\BanditEvaluationException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Sharder;

class BanditEvaluator implements IBanditEvaluator
{
    public function __construct(private readonly int $totalShards = 10000)
    {
    }


    /**
     * @param string $flagKey
     * @param ContextAttributes $subject
     * @param array<string, ContextAttributes> $actionsWithContexts
     * @param BanditModelData $banditModel
     * @return BanditEvaluation
     * @throws BanditEvaluationException
     * @throws InvalidArgumentException
     */
    public function evaluateBandit(
        string $flagKey,
        ContextAttributes $subject,
        array $actionsWithContexts,
        BanditModelData $banditModel
    ): BanditEvaluation {
        if (empty($actionsWithContexts)) {
            throw new InvalidArgumentException("No actions provided for bandit evaluation");
        }

        // Score all potential actions.
        $actionScores = self::scoreActions($subject->getAttributes(), $actionsWithContexts, $banditModel);

        // Assign action weights using FALCON.
        $actionWeights = self::weighActions(
            $actionScores,
            $banditModel->gamma,
            $banditModel->actionProbabilityFloor
        );

        // Shuffle the actions and select one based on the subject's bucket.
        $selectedAction = self::selectAction($flagKey, $subject->getKey(), $actionWeights);

        $selectedActionContext = $actionsWithContexts[$selectedAction];
        $actionScore = $actionScores[$selectedAction];
        $actionWeight = $actionWeights[$selectedAction];

        // Determine gap, if any between the selected action and the highest scoring one.
        $max = max($actionScores);
        $gap = $max - $actionScore;

        return new BanditEvaluation(
            $flagKey,
            $subject->getKey(),
            $subject->getAttributes(),
            $selectedAction,
            $actionsWithContexts[$selectedAction]->getAttributes(),
            $actionScore,
            $actionWeight,
            $banditModel->gamma,
            $gap
        );
    }

    /**
     * @param AttributeSet $subjectAttributes
     * @param array<string, ContextAttributes> $actionsWithContexts
     * @param BanditModelData $banditModel
     * @return array<string, float>
     */
    public static function scoreActions(
        AttributeSet $subjectAttributes,
        array $actionsWithContexts,
        BanditModelData $banditModel
    ): array {
        $scores = [];
        foreach ($actionsWithContexts as $key => $actionContext) {
            if (isset($banditModel->coefficients[$key])) {
                $scores[$key] = self::scoreAction(
                    $subjectAttributes,
                    $actionContext->getAttributes(),
                    $banditModel->coefficients[$key]
                );
            } else {
                $scores[$key] = $banditModel->defaultActionScore;
            }
        }
        return $scores;
    }

    /**
     * @param AttributeSet $subjectAttributes
     * @param AttributeSet $actionAttributes
     * @param ActionCoefficients $coefficients
     * @return float
     */
    private static function scoreAction(
        AttributeSet $subjectAttributes,
        AttributeSet $actionAttributes,
        ActionCoefficients $coefficients
    ): float {
        $score = $coefficients->intercept;

        $score += self::scoreNumericAttributes(
            $coefficients->subjectNumericCoefficients,
            $subjectAttributes->numericAttributes
        );
        $score += self::scoreCategoricalAttributes(
            $coefficients->subjectCategoricalCoefficients,
            $subjectAttributes->categoricalAttributes
        );
        $score += self::scoreNumericAttributes(
            $coefficients->actionNumericCoefficients,
            $actionAttributes->numericAttributes
        );
        $score += self::scoreCategoricalAttributes(
            $coefficients->actionCategoricalCoefficients,
            $actionAttributes->categoricalAttributes
        );

        return $score;
    }

    /**
     * @param array<string, float> $actionScores
     * @param float $gamma
     * @param float $probabilityFloor
     * @return array<string, float>
     */
    public static function weighActions(array $actionScores, float $gamma, float $probabilityFloor): array
    {
        $numberOfActions = count($actionScores);
        $bestActionKey = array_keys($actionScores, max($actionScores))[0];


        $minProbability = $probabilityFloor / $numberOfActions;

        $weights = array_filter(
            $actionScores,
            function ($key) use ($bestActionKey) {
                return $key !== $bestActionKey;
            },
            ARRAY_FILTER_USE_KEY
        );

        $bestScore = $actionScores[$bestActionKey];
        $weights = array_map(
            function ($score) use ($minProbability, $bestScore, $gamma, $numberOfActions) {
                return max($minProbability, 1.0 / ($numberOfActions + $gamma * ($bestScore - $score)));
            },
            $weights
        );

        $remainingWeight = max(0.0, 1.0 - array_sum($weights));
        $weights[$bestActionKey] = $remainingWeight;

        return $weights;
    }

    /**
     * @param string $flagKey
     * @param string $subjectKey
     * @param array<string, float> $actionWeights
     * @return string
     * @throws BanditEvaluationException
     */
    private function selectAction(string $flagKey, string $subjectKey, array $actionWeights): string
    {
        // Use a list of key-value pairs to make sorting easier.
        $weightPairs = array_map(
            fn($key) => new ActionValue($key, $actionWeights[$key]),
            array_keys($actionWeights)
        );
        
        $sortedWeights = $this->sortActionsByShards($weightPairs, $subjectKey, $flagKey);

        // Bucket the user
        $shard = Sharder::getShard("$flagKey-$subjectKey", $this->totalShards);
        $cumulativeWeight = 0.0;
        $shardValue = $shard / $this->totalShards;

        foreach ($sortedWeights as $weightData) {
            $cumulativeWeight += $weightData->value;
            if ($cumulativeWeight > $shardValue) {
                return $weightData->action;
            }
        }

        throw new BanditEvaluationException(
            "[Eppo SDK] No action selected for $flagKey $subjectKey"
        );
    }

    /**
     * @param array<NumericAttributeCoefficient> $coefficients
     * @param array<string, float> $attributes
     * @return float
     */
    public static function scoreNumericAttributes(array $coefficients, array $attributes): float
    {
        $score = 0.0;
        foreach ($coefficients as $coefficient) {
            $attributeKey = $coefficient->attributeKey;
            if (array_key_exists($attributeKey, $attributes)) {
                $score += $coefficient->coefficient * $attributes[$attributeKey];
            } else {
                $score += $coefficient->missingValueCoefficient;
            }
        }
        return $score;
    }

    public static function scoreCategoricalAttributes(array $coefficients, array $attributes): float
    {
        $score = 0.0;
        foreach ($coefficients as $coefficient) {
            $attributeKey = $coefficient->attributeKey;
            $valueCoefficients = $coefficient->valueCoefficients;
            if (
                array_key_exists($attributeKey, $attributes) && array_key_exists(
                    $attributes[$attributeKey],
                    $valueCoefficients
                )
            ) {
                $score += $valueCoefficients[$attributes[$attributeKey]];
            } else {
                $score += $coefficient->missingValueCoefficient;
            }
        }
        return $score;
    }

    /**
     * @param ActionValue[] $weightPairs
     * @param string $subjectKey
     * @param string $flagKey
     * @return ActionValue[]
     */
    public function sortActionsByShards(array $weightPairs, string $subjectKey, string $flagKey): array
    {
        // usort sorts in place.
        usort(
            $weightPairs,
            function (ActionValue $a, ActionValue $b) use ($subjectKey, $flagKey) {
                $aValue = Sharder::getShard("$flagKey-$subjectKey-{$a->action}", $this->totalShards);
                $bValue = Sharder::getShard("$flagKey-$subjectKey-{$b->action}", $this->totalShards);

                if ($aValue == $bValue) {
                    return $a->action <=> $b->action;
                }
                return $aValue <=> $bValue;
            }
        );
        return $weightPairs;
    }
}
