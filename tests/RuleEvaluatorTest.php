<?php

namespace Eppo\Tests;

use Eppo\DTO\Condition;
use Eppo\DTO\Rule;
use Eppo\RuleEvaluator;
use PHPUnit\Framework\TestCase;

final class RuleEvaluatorTest extends TestCase
{
    /** @var Rule */
    private $ruleWithEmptyConditions;

    /** @var Rule */
    private $ruleWithMatchesCondition;

    /** @var Rule */
    private $numericRule;

    /** @var Rule */
    private $semverRule;

    /**
     * @param string|null $name
     * @param array $data
     * @param string $dataName
     */
    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->ruleWithEmptyConditions = new Rule();
        $this->ruleWithEmptyConditions->allocationKey = 'allocation1';
        $this->ruleWithEmptyConditions->conditions = [];

        $numericRuleCondition1 = new Condition();
        $numericRuleCondition1->value = 100;
        $numericRuleCondition1->operator = 'LTE';
        $numericRuleCondition1->attribute = 'totalSales';

        $numericRuleCondition2 = new Condition();
        $numericRuleCondition2->value = 10;
        $numericRuleCondition2->operator = 'GTE';
        $numericRuleCondition2->attribute = 'totalSales';

        $this->numericRule = new Rule();
        $this->numericRule->allocationKey = 'allocation1';
        $this->numericRule->conditions = [$numericRuleCondition1, $numericRuleCondition2];

        // semver
        $semverRuleCondition1 = new Condition();
        $semverRuleCondition1->value = '1.0.0';
        $semverRuleCondition1->operator = 'GTE';
        $semverRuleCondition1->attribute = 'appVersion';

        $semverRuleCondition2 = new Condition();
        $semverRuleCondition2->value = '2.11.0';
        $semverRuleCondition2->operator = 'LTE';
        $semverRuleCondition2->attribute = 'appVersion';

        $this->semverRule = new Rule();
        $this->semverRule->allocationKey = 'allocation1';
        $this->semverRule->conditions = [$semverRuleCondition1, $semverRuleCondition2];

        $ruleWithMatchesConditionCondition = new Condition();
        $ruleWithMatchesConditionCondition->attribute = 'user_id';
        $ruleWithMatchesConditionCondition->value = '[0-9]+';
        $ruleWithMatchesConditionCondition->operator = 'MATCHES';

        $this->ruleWithMatchesCondition = new Rule();
        $this->ruleWithMatchesCondition->allocationKey = 'allocation1';
        $this->ruleWithMatchesCondition->conditions = [$ruleWithMatchesConditionCondition];
    }

    public function testReturnsNullIfRulesArrayIsEmpty()
    {
        $rules = [];
        $this->assertNull(
            RuleEvaluator::findMatchingRule(['name' => 'my-user'], $rules),
        );
    }

    public function testReturnsNullIfAttributesDoNotMatchAnyRules()
    {
        $rules = [$this->numericRule];
        $this->assertNull(
            RuleEvaluator::findMatchingRule(['totalSales' => 101], $rules),
        );
    }

    public function testReturnsTrueIfAttributesMatchAndConditions() {
        $rules = [$this->numericRule];
        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['totalSales' => 100], $rules),
            $this->numericRule
        );
    }

    public function testReturnsTrueIfAttributesMatchSemverConditions() {
        $rules = [$this->semverRule];
        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['appVersion' => '1.10.0'], $rules),
            $this->semverRule
        );
    }

    public function testReturnsNullIfThereIsNoAttributeForTheCondition() {
        $rules = [$this->numericRule];
        $this->assertNull(
            RuleEvaluator::findMatchingRule(['unknown' => 'test'], $rules),
        );
    }

    public function testReturnsTrueIfRulesHaveNoConditions() {
        $rules = [$this->ruleWithEmptyConditions];
        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['totalSales' => 101], $rules),
            $this->ruleWithEmptyConditions
        );
    }

    public function testReturnsNullIfUsingNumericOperatorWithString() {
        $rules = [$this->numericRule, $this->ruleWithMatchesCondition];

        $this->assertNull(
            RuleEvaluator::findMatchingRule(['totalSales' => 'stringValue'], $rules),
        );
    }

    public function testHandlesRuleWithMatchesOperator() {
        $rules = [$this->ruleWithMatchesCondition];

        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['user_id' => '14'], $rules),
            $this->ruleWithMatchesCondition
        );

        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['user_id' => 15], $rules),
            $this->ruleWithMatchesCondition
        );
    }

    public function testHandlesOneOfRuleTypeWithBoolean() {
        $oneOfRule = new Rule();
        $oneOfRule->allocationKey = 'allocation1';
        $oneOfRule->conditions[] = new Condition();
        $oneOfRule->conditions[0]->operator = 'ONE_OF';
        $oneOfRule->conditions[0]->value = ['true'];
        $oneOfRule->conditions[0]->attribute = 'enabled';

        $notOneOfRule = new Rule();
        $notOneOfRule->allocationKey = 'allocation1';
        $notOneOfRule->conditions[] = new Condition();
        $notOneOfRule->conditions[0]->operator = 'NOT_ONE_OF';
        $notOneOfRule->conditions[0]->value = ['true'];
        $notOneOfRule->conditions[0]->attribute = 'enabled';

        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['enabled' => true], [$oneOfRule]),
            $oneOfRule
        );
        $this->assertNull(
            RuleEvaluator::findMatchingRule(['enabled' => false], [$oneOfRule]),
        );

        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['enabled' => false], [$notOneOfRule]),
            $notOneOfRule
        );
        $this->assertNull(
            RuleEvaluator::findMatchingRule(['enabled' => true], [$notOneOfRule]),
        );
    }

    public function testHandlesOneOfRuleTypeWithString() {
        $oneOfRule = new Rule();
        $oneOfRule->allocationKey = 'allocation1';
        $oneOfRule->conditions[] = new Condition();
        $oneOfRule->conditions[0]->operator = 'ONE_OF';
        $oneOfRule->conditions[0]->value = ['user1', 'user2'];
        $oneOfRule->conditions[0]->attribute = 'userId';

        $notOneOfRule = new Rule();
        $notOneOfRule->allocationKey = 'allocation1';
        $notOneOfRule->conditions[] = new Condition();
        $notOneOfRule->conditions[0]->operator = 'NOT_ONE_OF';
        $notOneOfRule->conditions[0]->value = ['user14'];
        $notOneOfRule->conditions[0]->attribute = 'userId';

        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['userId' => 'user1'], [$oneOfRule]),
            $oneOfRule
        );
        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['userId' => 'user2'], [$oneOfRule]),
            $oneOfRule
        );
        $this->assertNull(
            RuleEvaluator::findMatchingRule(['userId' => 'user3'], [$oneOfRule]),
        );
        $this->assertNull(
            RuleEvaluator::findMatchingRule(['userId' => 'user14'], [$notOneOfRule]),
        );
        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['userId' => 'user15'], [$notOneOfRule]),
            $notOneOfRule
        );
    }

    public function testDoesCaseInsensitiveMatchingWithOneOfOperator() {
        $oneOfRule = new Rule();
        $oneOfRule->allocationKey = 'allocation1';
        $oneOfRule->conditions[] = new Condition();
        $oneOfRule->conditions[0]->operator = 'ONE_OF';
        $oneOfRule->conditions[0]->value = ['CA', 'US'];
        $oneOfRule->conditions[0]->attribute = 'country';

        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['country' => 'us'], [$oneOfRule]),
            $oneOfRule
        );

        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['country' => 'cA'], [$oneOfRule]),
            $oneOfRule
        );
    }

    public function testDoesCaseInsensitiveMatchingWithNotOneOfOperator() {
        $notOneOfRule = new Rule();
        $notOneOfRule->allocationKey = 'allocation1';
        $notOneOfRule->conditions[] = new Condition();
        $notOneOfRule->conditions[0]->operator = 'NOT_ONE_OF';
        $notOneOfRule->conditions[0]->value = ['1.0.BB', '1Ab'];
        $notOneOfRule->conditions[0]->attribute = 'deviceType';

        $this->assertNull(
            RuleEvaluator::findMatchingRule(['deviceType' => '1ab'], [$notOneOfRule]),
        );
    }

    public function testHandlesOneOfRuleWithNumber() {
        $oneOfRule = new Rule();
        $oneOfRule->allocationKey = 'allocation1';
        $oneOfRule->conditions[] = new Condition();
        $oneOfRule->conditions[0]->operator = 'ONE_OF';
        $oneOfRule->conditions[0]->value = ['1', '2'];
        $oneOfRule->conditions[0]->attribute = 'userId';

        $notOneOfRule = new Rule();
        $notOneOfRule->allocationKey = 'allocation1';
        $notOneOfRule->conditions[] = new Condition();
        $notOneOfRule->conditions[0]->operator = 'NOT_ONE_OF';
        $notOneOfRule->conditions[0]->value = ['14'];
        $notOneOfRule->conditions[0]->attribute = 'userId';

        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['userId' => 1], [$oneOfRule]),
            $oneOfRule
        );
        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['userId' => '2'], [$oneOfRule]),
            $oneOfRule
        );
        $this->assertNull(
            RuleEvaluator::findMatchingRule(['userId' => 3], [$oneOfRule]),
        );
        $this->assertNull(
            RuleEvaluator::findMatchingRule(['userId' => 14], [$notOneOfRule]),
        );
        $this->assertEquals(
            RuleEvaluator::findMatchingRule(['userId' => '15'], [$notOneOfRule]),
            $notOneOfRule
        );
    }
}
