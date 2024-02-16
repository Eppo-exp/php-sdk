<?php

declare(strict_types=1);

namespace Eppo;

use Eppo\DTO\Condition;
use Eppo\DTO\Rule;
use Composer\Semver\Comparator;

final class RuleEvaluator
{
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
    private static function matchesRule(array $subjectAttributes, Rule $rule): bool
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
        return array_map(function($condition) use ($subjectAttributes) {
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
                case 'GTE':
                    if (is_numeric($value) && is_numeric($condition->value)) {
                        return $value >= $condition->value;
                    }

                    return Comparator::greaterThanOrEqualTo($value, $condition->value);
                case 'GT':
                    if (is_numeric($value) && is_numeric($condition->value)) {
                        return $value > $condition->value;
                    }
                    
                    return Comparator::greaterThan($value, $condition->value);
                case 'LTE':
                    if (is_numeric($value) && is_numeric($condition->value)) {
                        return $value <= $condition->value;
                    }

                    return Comparator::lessThanOrEqualTo($value, $condition->value);
                case 'LT':
                    if (is_numeric($value) && is_numeric($condition->value)) {
                        return $value < $condition->value;
                    }

                    return Comparator::lessThan($value, $condition->value);
                case 'MATCHES':
                    return preg_match('/' . $condition->value . '/i', (string) $value) === 1;
                case 'ONE_OF':
                    return self::isOneOf($value, $condition->value);
                case 'NOT_ONE_OF':
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
        return array_values(array_filter($conditionValues, function($value) use ($attributeValue) {
            return strtolower($value) === strtolower($attributeValue);
        }));
    }
}
