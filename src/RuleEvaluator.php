<?php

declare(strict_types=1);

namespace Eppo;

use Composer\Semver\Comparator;
use Eppo\DTO\Condition;
use Eppo\DTO\Flag;
use Eppo\DTO\FlagEvaluation;
use Eppo\DTO\Operator;
use Eppo\DTO\Rule;
use Eppo\DTO\Shard;

final class RuleEvaluator
{
    /**
     * Determines which, if any, variation is applicable to the given subject.
     *
     * Returns `null` if the flag is disabled or no matching variation can be found.
     *
     * @param Flag $flag
     * @param string $subjectKey
     * @param array $subjectAttributes
     * @return FlagEvaluation|null
     */
    public static function evaluateFlag(Flag $flag, string $subjectKey, array $subjectAttributes): FlagEvaluation|null
    {
        if (!$flag->enabled)
            return null;

        $now = time();
        foreach ($flag->allocations as $allocation) {
            # Skip allocations that are not active
            if ($allocation->startAt && $now < $allocation->startAt) {
                continue;
            }
            if ($allocation->endAt && $now > $allocation->endAt) {
                continue;
            }


            $subject = ['id' => $subjectKey, ...$subjectAttributes];
            if (self::matchesAnyRule($allocation->rules, $subject)) {
                foreach ($allocation->splits as $split) {
                    # Split needs to match all shards
                    if (self::matchesAllShards($split->shards, $subjectKey, $flag->totalShards)) {
                        return new FlagEvaluation($flag->variations[$split->variationKey], $allocation->doLog, $allocation->key);
                    }
                }
            }
        }

        // No allocations matched.
        return null;
    }

    /**
     * Find the first rule in the given set of rules that matches the given subject attributes.
     *
     * @param array $subjectAttributes An associative array of subject attributes to be evaluated against the rules.
     * @param array $rules An array of rules to evaluate against the subject attributes.
     *
     * @return Rule|null Returns the first matching rule or null if no rule matches the subject attributes.
     */
    public static function findMatchingRule(array $subjectAttributes, array $rules): ?Rule
    {
        foreach ($rules as $rule) {
            if (self::matchesRule($subjectAttributes, $rule)) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * Checks if a given set of subject attributes matches a rule.
     *
     * @param array $subjectAttributes An array of subject attributes to evaluate.
     * @param Rule $rule An array containing the rule to evaluate.
     *
     * @return bool Returns true if the subject attributes match the rule, and false otherwise.
     */
    public static function matchesRule(array $subjectAttributes, Rule $rule): bool
    {
        $conditionEvaluations = self::evaluateRuleConditions($subjectAttributes, $rule->conditions);
        return !in_array(false, $conditionEvaluations, true);
    }

    /**
     * @param array $subjectAttributes
     * @param array $conditions
     * @return array
     */
    private static function evaluateRuleConditions(array $subjectAttributes, array $conditions): array
    {
        return array_map(function ($condition) use ($subjectAttributes) {
            return self::evaluateCondition($subjectAttributes, $condition);
        }, $conditions);
    }

    /**
     * @param array $subjectAttributes
     * @param Condition $condition
     * @return bool
     */
    private static function evaluateCondition(array $subjectAttributes, Condition $condition): bool
    {
        $value = $subjectAttributes[$condition->attribute] ?? null;
        if ($value !== null) {
            switch ($condition->operator) {
                case Operator::GTE:
                    if (is_numeric($value) && is_numeric($condition->value)) {
                        return $value >= $condition->value;
                    }

                    return Comparator::greaterThanOrEqualTo($value, $condition->value);
                case Operator::GT:
                    if (is_numeric($value) && is_numeric($condition->value)) {
                        return $value > $condition->value;
                    }

                    return Comparator::greaterThan($value, $condition->value);
                case Operator::LTE:
                    if (is_numeric($value) && is_numeric($condition->value)) {
                        return $value <= $condition->value;
                    }

                    return Comparator::lessThanOrEqualTo($value, $condition->value);
                case Operator::LT:
                    if (is_numeric($value) && is_numeric($condition->value)) {
                        return $value < $condition->value;
                    }

                    return Comparator::lessThan($value, $condition->value);
                case Operator::MATCHES:
                    return preg_match('/' . $condition->value . '/i', (string)$value) === 1;
                case Operator::ONE_OF:
                    return self::isOneOf($value, $condition->value);
                case Operator::NOT_ONE_OF:
                    return self::isNotOneOf($value, $condition->value);
            }
        }
        return false;
    }

    /**
     * @param $attributeValue
     * @param $conditionValue
     * @return bool
     */
    private static function isOneOf($attributeValue, $conditionValue): bool
    {
        if (is_bool($attributeValue)) {
            $attributeValue = $attributeValue ? 'true' : 'false';
        }
        return count(self::getMatchingStringValues(strval($attributeValue), $conditionValue)) > 0;
    }

    /**
     * @param $attributeValue
     * @param $conditionValue
     *
     * @return bool
     */
    private static function isNotOneOf($attributeValue, $conditionValue): bool
    {
        if (is_bool($attributeValue)) {
            $attributeValue = $attributeValue ? 'true' : 'false';
        }
        return count(self::getMatchingStringValues(strval($attributeValue), $conditionValue)) === 0;
    }

    /**
     * @param $attributeValue
     * @param $conditionValues
     * @return array
     */
    private static function getMatchingStringValues($attributeValue, $conditionValues): array
    {
        return array_values(array_filter($conditionValues, function ($value) use ($attributeValue) {
            return strtolower($value) === strtolower($attributeValue);
        }));
    }

    public static function matchesAnyRule(array $rules, array $subject): bool
    {
        if (count($rules) === 0) {
            return true;
        }
        foreach ($rules as $rule) {
            if (self::matchesRule($subject, $rule)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Shard[] $shards
     * @param string $subjectKey
     * @param int $totalShards
     */
    public static function matchesAllShards(array $shards, string $subjectKey, int $totalShards): bool
    {
        foreach ($shards as $shard) {
            if (!self::matchesShard($shard, $subjectKey, $totalShards)) {
                return false;
            }
        }
        return true;
    }

    private static function matchesShard(Shard $shard, $subjectKey, int $totalShards): bool
    {
        $hashKey = $shard->salt . '-' . $subjectKey;
        $subjectBucket = Sharder::getShard($hashKey, $totalShards);

        foreach ($shard->ranges as $range) {
            if (Sharder::isShardInRange($subjectBucket, $range)) {
                return true;
            }
        }
        return false;
    }

}
