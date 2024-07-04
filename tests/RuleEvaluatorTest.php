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
use PHPUnit\Framework\TestCase;

final class RuleEvaluatorTest extends TestCase
{
    private const SUBJECT_KEY = 'Elvis';
    private const TOTAL_SHARDS = 10;

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
    private Rule $nonMatchNumericRule;
    private Rule $rockAndRollLegendRule;
    /**
     * @var Split[]
     */
    private array $musicSplits;
    /**
     * @var Split[]
     */
    private array $nonMatchingSplits;
    private Rule $ruleWithPreciseMatchesCondition;

    /**
     * @param string|null $name
     * @param array $data
     * @param string $dataName
     */
    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->rockAndRollLegendRule = new Rule(
            [
                new Condition('age', 'GTE', 40),
                new Condition('occupation', 'MATCHES', 'musician'),
                new Condition('albumCount', 'GTE', 50)
            ]
        );

        $this->subject = ['age' => 42, 'albumCount' => 57, 'occupation' => 'musician'];

        $this->matchingSplits = [new Split('match', [new Shard("na", [new ShardRange(0, self::TOTAL_SHARDS)])], [])];
        $this->musicSplits = [new Split('music', [new Shard("na", [new ShardRange(2, 4)])], [])];
        $this->nonMatchingSplits = [
            new Split('match', [
                new Shard("na", [
                    new ShardRange(0, 4),
                    new ShardRange(5, 9)
                ]),
                new Shard('cl', [])
            ], [])
        ];

        $this->matchVariation = new Variation('match', 'foo');
        $numericRuleCondition1 = new Condition('albumCount', 'LTE', 100);
        $numericRuleCondition2 = new Condition('albumCount', 'GTE', self::TOTAL_SHARDS);
        $numericRuleCondition3 = new Condition('albumCount', 'LTE', self::TOTAL_SHARDS);

        $this->numericRule = new Rule([$numericRuleCondition1, $numericRuleCondition2]);
        $this->nonMatchNumericRule = new Rule([$numericRuleCondition1, $numericRuleCondition3]);

        // semver
        $semverRuleCondition1 = new Condition('appVersion', 'GTE', '1.0.0');
        $semverRuleCondition2 = new Condition('appVersion', 'LTE', '2.11.0');

        $this->semverRule = new Rule([$semverRuleCondition1, $semverRuleCondition2]);

        $ruleWithMatchesConditionCondition = new Condition('user_id', 'MATCHES', '[0-9]+');
        $this->ruleWithMatchesCondition = new Rule([$ruleWithMatchesConditionCondition]);

        $ruleWithNotMatchesConditionCondition = new Condition('user_id', 'NOT_MATCHES', '[0-9]+');
        $this->ruleWithNotMatchesConditionCondition = new Rule([$ruleWithNotMatchesConditionCondition]);

        $this->ruleWithPreciseMatchesCondition = new Rule([new Condition('user_id', 'MATCHES', '^[0-9]+$')]);
    }

    public function testSemVer(): void
    {
        $this->assertTrue(RuleEvaluator::matchesRule(['appVersion' => '2.0.0'], $this->semverRule));
        $this->assertTrue(RuleEvaluator::matchesRule(['appVersion' => '2.11.0'], $this->semverRule));
        $this->assertTrue(RuleEvaluator::matchesRule(['appVersion' => '1.0.0'], $this->semverRule));

        $this->assertFalse(RuleEvaluator::matchesRule(['appVersion' => '0.0.9'], $this->semverRule));
        $this->assertFalse(RuleEvaluator::matchesRule(['appVersion' => '2.11.1'], $this->semverRule));
        $this->assertFalse(RuleEvaluator::matchesRule(['appVersion' => '3.0'], $this->semverRule));
    }

    public function testStringMatch(): void
    {
        $this->assertFalse(RuleEvaluator::matchesRule(['user_id' => 'abc'], $this->ruleWithMatchesCondition));
        $this->assertFalse(RuleEvaluator::matchesRule([], $this->ruleWithMatchesCondition));
        $this->assertTrue(RuleEvaluator::matchesRule(['user_id' => 'A123456789'], $this->ruleWithMatchesCondition));
        $this->assertTrue(RuleEvaluator::matchesRule(['user_id' => '123456789A'], $this->ruleWithMatchesCondition));
        $this->assertTrue(RuleEvaluator::matchesRule(['user_id' => '123456789'], $this->ruleWithMatchesCondition));
        $this->assertTrue(RuleEvaluator::matchesRule(['user_id' => '12'], $this->ruleWithMatchesCondition));

        $this->assertFalse(
            RuleEvaluator::matchesRule(['user_id' => '123456789A'], $this->ruleWithPreciseMatchesCondition)
        );
        $this->assertFalse(
            RuleEvaluator::matchesRule(['user_id' => 'A123456789'], $this->ruleWithPreciseMatchesCondition)
        );
        $this->assertTrue(RuleEvaluator::matchesRule(
            ['user_id' => '123456789'],
            $this->ruleWithPreciseMatchesCondition
        ));
    }

    public function testStringNotMatch(): void
    {
        // Pattern to not match is /[0-9]+/
        $this->assertTrue(RuleEvaluator::matchesRule(
            ['user_id' => 'abc'],
            $this->ruleWithNotMatchesConditionCondition
        ));
        $this->assertFalse(RuleEvaluator::matchesRule([], $this->ruleWithNotMatchesConditionCondition));
        $this->assertFalse(
            RuleEvaluator::matchesRule(['user_id' => 'A123456789'], $this->ruleWithNotMatchesConditionCondition)
        );
        $this->assertFalse(
            RuleEvaluator::matchesRule(['user_id' => '123456789A'], $this->ruleWithNotMatchesConditionCondition)
        );
        $this->assertFalse(
            RuleEvaluator::matchesRule(['user_id' => '123456789'], $this->ruleWithNotMatchesConditionCondition)
        );
        $this->assertFalse(RuleEvaluator::matchesRule(
            ['user_id' => '12'],
            $this->ruleWithNotMatchesConditionCondition
        ));

        $this->assertFalse(
            RuleEvaluator::matchesRule(['user_id' => '123456789A'], $this->ruleWithNotMatchesConditionCondition)
        );
        $this->assertFalse(
            RuleEvaluator::matchesRule(['user_id' => 'A123456789'], $this->ruleWithNotMatchesConditionCondition)
        );
        $this->assertFalse(
            RuleEvaluator::matchesRule(['user_id' => '123456789'], $this->ruleWithNotMatchesConditionCondition)
        );
    }

    public function testNoMatchingShards(): void
    {
        $this->assertFalse(
            RuleEvaluator::matchesAllShards(
                $this->nonMatchingSplits[0]->shards,
                self::SUBJECT_KEY,
                self::TOTAL_SHARDS
            )
        );
    }

    // matches rule and some shards
    public function testSomeMatchingShards(): void
    {
        $this->assertFalse(
            RuleEvaluator::matchesAllShards(
                [...$this->matchingSplits[0]->shards, ...$this->nonMatchingSplits[0]->shards],
                self::SUBJECT_KEY,
                self::TOTAL_SHARDS
            )
        );
    }

    public function testMatchesShards(): void
    {
        $this->assertTrue(
            RuleEvaluator::matchesAllShards(
                $this->matchingSplits[0]->shards,
                self::SUBJECT_KEY,
                self::TOTAL_SHARDS
            )
        );
    }

    // Flag Evaluation
    public function testFlagEvaluation(): void
    {
        $allocations = [
            new Allocation(
                'rock',
                [$this->rockAndRollLegendRule],
                $this->musicSplits,
                false
            )
        ];
        $variations = [
            'music' => new Variation('music', 'rockandroll'),
            'football' => new Variation('football', 'football'),
            'space' => new Variation('space', 'space')
        ];

        $bigFlag = new Flag(
            'HallOfFame',
            true,
            $allocations,
            VariationType::STRING,
            $variations,
            10
        );

        $result = RuleEvaluator::evaluateFlag($bigFlag, self::SUBJECT_KEY, $this->subject);
        $this->assertNotNull($result);
        $this->assertEquals('music', $result->variation->key);
        $this->assertEquals('rockandroll', $result->variation->value);
    }

    public function testDisabledFlag(): void
    {
        $flag = new Flag('disabled', false, [], VariationType::BOOLEAN, [], self::TOTAL_SHARDS);
        $this->assertNull(RuleEvaluator::evaluateFlag($flag, self::SUBJECT_KEY, []));
    }

    public function testFlagWithInactiveAllocations(): void
    {
        $now = time();
        $overAlloc = new Allocation('over', [], $this->matchingSplits, false, endAt: $now - 10000);
        $hasntStartedAlloc = new Allocation('hasntStarted', [], $this->matchingSplits, false, $now + 1000 * 60);

        $flag = new Flag(
            'inactive_allocs',
            true,
            [$overAlloc, $hasntStartedAlloc],
            VariationType::BOOLEAN,
            [$this->matchVariation->key => $this->matchVariation],
            self::TOTAL_SHARDS
        );
        $this->assertNull(RuleEvaluator::evaluateFlag($flag, self::SUBJECT_KEY, $this->subject));
    }

    public function testFlagWithoutAllocations(): void
    {
        $flag = new Flag('no_allocs', true, [], VariationType::BOOLEAN, [], self::TOTAL_SHARDS);
        $this->assertNull(RuleEvaluator::evaluateFlag($flag, self::SUBJECT_KEY, $this->subject));
    }

    public function testMatchesVariationWithoutRules(): void
    {
        $allocation1 = new Allocation('alloc1', [], $this->matchingSplits, false);
        $basicVariation = new Variation('foo', 'bar');
        $flag = new Flag(
            'matches',
            true,
            [$allocation1],
            VariationType::STRING,
            ["match" => $basicVariation],
            self::TOTAL_SHARDS
        );
        $result = RuleEvaluator::evaluateFlag($flag, self::SUBJECT_KEY, $this->subject);
        $this->assertNotNull($result);
        $this->assertEquals('bar', $result->variation->value);
    }

    public function testMatchesEmptyRuleSet(): void
    {
        $this->assertTrue(RuleEvaluator::matchesAnyRule([], $this->subject));
    }

    public function testMatchesSecondRule(): void
    {
        $this->assertTrue(
            RuleEvaluator::matchesAnyRule([$this->nonMatchNumericRule, $this->numericRule], $this->subject)
        );
    }
}
