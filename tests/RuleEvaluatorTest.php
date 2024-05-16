<?php

namespace Eppo\Tests;

use Eppo\DTO\Allocation;
use Eppo\DTO\Condition;
use Eppo\DTO\Flag;
use Eppo\DTO\Rule;
use Eppo\DTO\Shard;
use Eppo\DTO\ShardRange;
use Eppo\DTO\Split;
use Eppo\DTO\Variation;
use Eppo\DTO\VariationType;
use Eppo\RuleEvaluator;
use Google\Api\Distribution\Range;
use PHPUnit\Framework\TestCase;

final class RuleEvaluatorTest extends TestCase
{
    private Rule $ruleWithEmptyConditions;

    private Rule $ruleWithMatchesCondition;

    private Rule $numericRule;

    private Rule $semverRule;
    /**
     * @var array|int[]
     */
    private array $subject;

    /**
     * @var Split[]
     */
    private array $matchingSplits;
    private Variation $matchVariation;

    /**
     * @param string|null $name
     * @param array $data
     * @param string $dataName
     */
    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->ruleWithEmptyConditions = new Rule([]);
        $this->subject = ['age' => 20];

        $this->matchingSplits = [new Split('match', [new Shard("na", [new ShardRange(0, 10)])], [])];
$this->matchVariation = new Variation('match', 'foo');
//        $numericRuleCondition1 = new Condition('totalSales', 'LTE', 100);
//        $numericRuleCondition2 = new Condition('totalSales', 'GTE', 10);
//
//        $this->numericRule = new Rule([$numericRuleCondition1, $numericRuleCondition2]);
//
//        // semver
//        $semverRuleCondition1 = new Condition();
//        $semverRuleCondition1->value = '1.0.0';
//        $semverRuleCondition1->operator = 'GTE';
//        $semverRuleCondition1->attribute = 'appVersion';
//
//        $semverRuleCondition2 = new Condition();
//        $semverRuleCondition2->value = '2.11.0';
//        $semverRuleCondition2->operator = 'LTE';
//        $semverRuleCondition2->attribute = 'appVersion';
//
//        $this->semverRule = new Rule();
//        $this->semverRule->allocationKey = 'allocation1';
//        $this->semverRule->conditions = [$semverRuleCondition1, $semverRuleCondition2];
//
//        $ruleWithMatchesConditionCondition = new Condition();
//        $ruleWithMatchesConditionCondition->attribute = 'user_id';
//        $ruleWithMatchesConditionCondition->value = '[0-9]+';
//        $ruleWithMatchesConditionCondition->operator = 'MATCHES';
//
//        $this->ruleWithMatchesCondition = new Rule();
//        $this->ruleWithMatchesCondition->allocationKey = 'allocation1';
//        $this->ruleWithMatchesCondition->conditions = [$ruleWithMatchesConditionCondition];
    }

    // Rule Matching

    // matches rule but no shards
    // matches rule and some shards
    // matches rule and all shards
    // no match

    // Flag Evaluation

    public function testDisabledFlag(): void
    {
        $flag = new Flag('disabled', false, [], VariationType::BOOLEAN, [], 10);
        $this->assertNull(RuleEvaluator::evaluateFlag($flag, 'Elvis', []));
    }

    public function testFlagWithInactiveAllocations(): void
    {
        $now = time();
        $overAlloc = new Allocation('over', [], $this->matchingSplits, false, endAt: $now - 10000);
        $hasntStartedAlloc = new Allocation('hasntStarted', [], $this->matchingSplits, false, $now + 1000 * 60);

        $flag = new Flag('inactive_allocs', true, [$overAlloc, $hasntStartedAlloc], VariationType::BOOLEAN, [$this->matchVariation->key => $this->matchVariation], 10);
        $this->assertNull(RuleEvaluator::evaluateFlag($flag, 'Elvis', $this->subject));
    }

    public function testFlagWithoutAllocations(): void
    {
        $flag = new Flag('no_allocs', true, [], VariationType::BOOLEAN, [], 10);
        $this->assertNull(RuleEvaluator::evaluateFlag($flag, 'Elvis', $this->subject));
    }

    public function testMatchesVariationWithoutRules(): void
    {
        $allocation1 = new Allocation('alloc1', [], $this->matchingSplits, false);
        $basicVariation = new Variation('foo', 'bar');
        $flag = new Flag('matches', true, [$allocation1], VariationType::STRING, ["match"=>$basicVariation], 10);
        $this->assertNotNull(RuleEvaluator::evaluateFlag($flag, 'Elvis', $this->subject));
        $this->assertNotNull(RuleEvaluator::evaluateFlag($flag, 'Elvis', $this->subject));
    }


    // no rules
    public function testMatchesEmptyRuleSet(): void
    {
        $this->assertTrue(RuleEvaluator::matchesAnyRule([], $this->subject));
    }


}
