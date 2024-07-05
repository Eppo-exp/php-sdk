<?php

namespace Eppo\Tests\Bandits;

use Eppo\Bandits\BanditEvaluator;
use Eppo\DTO\Bandit\ActionCoefficients;
use Eppo\DTO\Bandit\AttributeSet;
use Eppo\DTO\Bandit\BanditModelData;
use Eppo\DTO\Bandit\CategoricalAttributeCoefficient;
use Eppo\DTO\Bandit\ContextAttributes;
use Eppo\DTO\Bandit\NumericAttributeCoefficient;
use PHPUnit\Framework\TestCase;

class BanditEvaluatorTest extends TestCase
{
    public function setUp(): void
    {
        $this->evaluator = new BanditEvaluator();
    }

    public function testShouldScoreNumericAttributes()
    {
        $subjectAttributes = AttributeSet::fromArray(['age' => 30, 'height' => 170]);
        $numericCoefficients = [
            new NumericAttributeCoefficient('age', 2.0, 0.5),
            new NumericAttributeCoefficient('height', 1.5, 0.3)
        ];
        $expectedScore = 30 * 2.0 + 170 * 1.5;

        $actualScore = BanditEvaluator::scoreNumericAttributes(
            $numericCoefficients,
            $subjectAttributes->numericAttributes
        );

        $this->assertEquals($expectedScore, $actualScore);
    }

    public function testShouldScoreNumericAttributesWithMissingValues()
    {
        $subjectAttributes = AttributeSet::fromArray(['age' => 30]);
        $numericCoefficients = [
            new NumericAttributeCoefficient('age', 2.0, 0.5),
            new NumericAttributeCoefficient('height', 1.5, 0.3)
        ];

        $expectedScore = 30 * 2.0 + 0.3; // Missing height value with default intercept

        $actualScore = BanditEvaluator::scoreNumericAttributes(
            $numericCoefficients,
            $subjectAttributes->numericAttributes
        );

        $this->assertEquals($expectedScore, $actualScore);
    }

    public function testShouldScoreNumericAttributesWithAllMissingValues()
    {
        $subjectAttributes = new AttributeSet();
        $numericCoefficients = [
            new NumericAttributeCoefficient('age', 2.0, 0.5),
            new NumericAttributeCoefficient('height', 1.5, 0.3)
        ];
        $expectedScore = 0.5 + 0.3; // Default intercepts for all missing attributes

        $actualScore = BanditEvaluator::scoreNumericAttributes(
            $numericCoefficients,
            $subjectAttributes->numericAttributes
        );

        $this->assertEquals($expectedScore, $actualScore);
    }

    public function testShouldScoreNumericAttributesNoCoefficients()
    {
        $subjectAttributes = AttributeSet::fromArray(['age' => 30, 'height' => 170]);
        $numericCoefficients = [];
        $expectedScore = 0.0; // No coefficients to apply

        $actualScore = BanditEvaluator::scoreNumericAttributes(
            $numericCoefficients,
            $subjectAttributes->numericAttributes
        );

        $this->assertEquals($expectedScore, $actualScore);
    }

    public function testShouldScoreNumericAttributesNegativeCoefficients()
    {
        $subjectAttributes = AttributeSet::fromArray(['age' => 30, 'height' => 170]);
        $numericCoefficients = [
            new NumericAttributeCoefficient('age', -2.0, 0.5),
            new NumericAttributeCoefficient('height', -1.5, 0.3)
        ];

        $expectedScore = 30 * -2.0 + 170 * -1.5;

        $actualScore = BanditEvaluator::scoreNumericAttributes(
            $numericCoefficients,
            $subjectAttributes->numericAttributes
        );

        $this->assertEquals($expectedScore, $actualScore);
    }


    public function testShouldScoreCategoricalAttributes(): void
    {
        $subjectAttributes = AttributeSet::fromArray(['color' => 'red', 'size' => 'large']);

        $categoricalCoefficients = [
            new CategoricalAttributeCoefficient('color', 0.2, ['red' => 1.0, 'blue' => 0.5]),
            new CategoricalAttributeCoefficient('size', 0.3, ['large' => 2.0, 'small' => 1.0])
        ];

        $expectedScore = 1.0 + 2.0;

        $actualScore = BanditEvaluator::scoreCategoricalAttributes(
            $categoricalCoefficients,
            $subjectAttributes->categoricalAttributes
        );

        $this->assertEquals($expectedScore, $actualScore);
    }

    public function testScoreCategoricalAttributesSomeMissing(): void
    {
        $subjectAttributes = AttributeSet::fromArray(['color' => 'red']);

        $categoricalCoefficients = [
            new CategoricalAttributeCoefficient('color', 0.2, ['red' => 1.0, 'blue' => 0.5]),
            new CategoricalAttributeCoefficient('size', 0.3, ['large' => 2.0, 'small' => 1.0])
        ];

        $expectedScore = 1.0 + 0.3;

        $actualScore = BanditEvaluator::scoreCategoricalAttributes(
            $categoricalCoefficients,
            $subjectAttributes->categoricalAttributes
        );

        $this->assertEquals($expectedScore, $actualScore);
    }

    public function testScoreCategoricalAttributesAllMissing()
    {
        $subjectAttributes = new AttributeSet();
        $categoricalCoefficients = [
            new CategoricalAttributeCoefficient('color', 0.2, ['red' => 1.0, 'blue' => 0.5]),
            new CategoricalAttributeCoefficient('size', 0.3, ['large' => 2.0, 'small' => 1.0])
        ];
        $expectedScore = 0.2 + 0.3;

        $actualScore = BanditEvaluator::scoreCategoricalAttributes(
            $categoricalCoefficients,
            $subjectAttributes->categoricalAttributes
        );

        $this->assertEquals($expectedScore, $actualScore);
    }

    /**
     * Test scoring categorical attributes with no coefficients
     */
    public function testScoreCategoricalAttributesNoCoefficients()
    {
        $subjectAttributes = AttributeSet::fromArray(['color' => 'red', 'size' => 'large']);
        $expectedScore = 0;

        $actualScore = BanditEvaluator::ScoreCategoricalAttributes([], $subjectAttributes->categoricalAttributes);
        $this->assertEquals($expectedScore, $actualScore);
    }

    public function testScoreCategoricalAttributesNegativeCoefficients()
    {
        $subjectAttributes = AttributeSet::fromArray(['color' => 'red', 'size' => 'large']);
        $categoricalCoefficients = [
            new CategoricalAttributeCoefficient('color', -0.2, ['red' => -1.0, 'blue' => -0.5]),
            new CategoricalAttributeCoefficient('size', -0.3, ['large' => -2.0, 'small' => -1.0])
        ];


        $expectedScore = -1.0 - 2.0;

        $actualScore = BanditEvaluator::ScoreCategoricalAttributes(
            $categoricalCoefficients,
            $subjectAttributes->categoricalAttributes
        );
        $this->assertEquals($expectedScore, $actualScore);
    }

    /**
     * Test weighing a single action
     */
    public function testWeighOneAction()
    {
        $scores = ['action' => 87.0];
        $expectedWeights = ['action' => 1.0];

        $actualWeights = BanditEvaluator::weighActions($scores, 10, 0.1);
        $this->assertEquals($expectedWeights, $actualWeights);
    }

    public function testWeighMultipleActionsToFloor()
    {
        $scores = array(
            'action' => 87.0,
            'action2' => 1.0,
            'action3' => 15.0,
            'action4' => 2.7,
            'action5' => 0.5,
        );

        $gamma = 10;
        $minProbability = 0.1;

        $expectedFloorValue = $minProbability / count($scores); // Calculate floor value

        // Calculate expected winner weight based on floor value and number of actions
        $expectedWinnerWeight = 1 - ($expectedFloorValue * (count($scores) - 1));

        $expectedWeights = array(
            'action' => $expectedWinnerWeight,
            'action2' => $expectedFloorValue,
            'action3' => $expectedFloorValue,
            'action4' => $expectedFloorValue,
            'action5' => $expectedFloorValue,
        );

        $actualWeights = BanditEvaluator::weighActions($scores, $gamma, $minProbability);
        $this->assertEquals($expectedWeights, $actualWeights);
    }

    public function testWeighMultipleActionsSmallSpread()
    {
        $scores = array(
            'Ovechkin' => 8.0,
            'Crosby' => 87.0,
            'Lemieux' => 66.0,
            'Gretzky' => 99.0,
            'Lindros' => 88.0,
        );

        $gamma = 0.1;
        $minProbability = 0.1;

        $weights = BanditEvaluator::weighActions($scores, $gamma, $minProbability);

        $this->assertEquals(1.0, array_sum(array_map(function ($aScore) {
            return $aScore;
        }, $weights))); // Weights sum to 1

        // Sorts the array, in place, descending and maintains associative keys (array_keys call below is also ordered).
        arsort($weights);
        $orderedKeys = array_keys($weights);

        $expectedOrder = array('Gretzky', 'Lindros', 'Crosby', 'Lemieux', 'Ovechkin');

        $this->assertEquals($expectedOrder, $orderedKeys);
    }

    public function testWeighWithGamma()
    {
        $scores = array(
            'action' => 2.0,
            'action2' => 0.5,
        );

        $smallGamma = 1;
        $largeGamma = 10;
        $minProbability = 0.1;

        $smallGammaWeights = BanditEvaluator::weighActions($scores, $smallGamma, $minProbability);
        $largeGammaWeights = BanditEvaluator::weighActions($scores, $largeGamma, $minProbability);

        $this->assertLessThan(
            $largeGammaWeights['action'],
            $smallGammaWeights['action']
        ); // Winner weight lower with larger gamma

        $this->assertGreaterThan(
            $largeGammaWeights['action2'],
            $smallGammaWeights['action2']
        ); // Non-winner weight higher with larger gamma
    }

    public function testWeighEvenField()
    {
        $scores = array(
            'action1' => 0.5,
            'action2' => 0.5,
            'action3' => 0.5,
            'action4' => 0.5,
        );

        $expectedWeights = array(
            'action1' => 0.25, // 1/4
            'action2' => 0.25,
            'action3' => 0.25,
            'action4' => 0.25,
        );

        $gamma = 0.1;
        $minProbability = 0.1;

        $weights = BanditEvaluator::weighActions($scores, $gamma, $minProbability);
        $this->assertEquals($expectedWeights, $weights);
    }

    public function testEvaluateBandit()
    {
        // Mock data (constants, arrays, objects)
        $flagKey = 'test_flag';
        $subjectKey = 'test_subject';

        $subject = ContextAttributes::fromArray($subjectKey, [
            'age' => 25.0,
            'location' => 'US',
        ]);

        $actionContexts = [
            'action1' =>
                ContextAttributes::fromArray('action1', [
                    'price' => 10.0,
                    'category' => 'A',
                ]),
            'action2' =>
                ContextAttributes::fromArray('action2', [
                    'price' => 20.0,
                    'category' => 'B',
                ])
        ];

        $coefficients = [
            'action1' => new ActionCoefficients('action1', 0.5, [
                new NumericAttributeCoefficient('age', 0.1, 0.0)], [
                new CategoricalAttributeCoefficient(
                    'location',
                    0.0,
                    ['US' => 0.2]
                )], [
                new NumericAttributeCoefficient('price', 0.05, 0.0)], [
                new CategoricalAttributeCoefficient(
                    'category',
                    0.0,
                    ['A' => 0.3]
                ),
                ]),
            'action2' => new ActionCoefficients('action2', 0.3, [
                new NumericAttributeCoefficient('age', 0.1, 0.0)], [
                new CategoricalAttributeCoefficient(
                    'location',
                    0.0,
                    ['US' => 0.2]
                )], [
                new NumericAttributeCoefficient('price', 0.05, 0.0)], [
                new CategoricalAttributeCoefficient(
                    'category',
                    0.0,
                    ['B' => 0.3]
                ),
                ]),
        ];

        $banditModel = new BanditModelData(
            0.1,
            $coefficients,
            0.0,
            0.1
        );

        $evaluator = new BanditEvaluator(10_000);

        $evaluation = $evaluator->evaluateBandit($flagKey, $subject, $actionContexts, $banditModel);

        $this->assertNotNull($evaluation);

        $this->assertEquals($flagKey, $evaluation->flagKey);
        $this->assertEquals($subjectKey, $evaluation->subjectKey);


        $this->assertEquals(
            $subject->getAttributes()->numericAttributes,
            $evaluation->subjectAttributes->numericAttributes
        );
        $this->assertEquals(
            $subject->getAttributes()->categoricalAttributes,
            $evaluation->subjectAttributes->categoricalAttributes
        );

        $this->assertEquals('action2', $evaluation->selectedAction);

        $this->assertEquals($banditModel->gamma, $evaluation->gamma);

        $this->assertEquals(4.3, $evaluation->actionScore);
        $this->assertEqualsWithDelta(0.5074, $evaluation->actionWeight, 0.0001);
    }
}