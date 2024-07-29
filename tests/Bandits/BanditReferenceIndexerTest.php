<?php

namespace Eppo\Tests\Bandits;

use Eppo\Bandits\BanditReferenceIndexer;
use Eppo\DTO\Bandit\BanditFlagVariation;
use Eppo\DTO\BanditReference;
use Eppo\Exception\InvalidConfigurationException;
use PHPUnit\Framework\TestCase;

final class BanditReferenceIndexerTest extends TestCase
{
    /**
     * @var array<string, BanditReference>
     */
    private static array $banditReferences = [];

    public static function setUpBeforeClass(): void
    {
        // Bandits:Flags can be m:n

        // One bandit referenced by two flags.
        self::$banditReferences['bandit_one'] = new BanditReference(
            'v123',
            [
                new BanditFlagVariation(
                    'bandit_one',
                    'bandit_one_flag',
                    'bandit_one_flag_allocation',
                    'bandit_one_flag_variation',
                    'bandit_one_flag_variation'
                ),
                new BanditFlagVariation(
                    'bandit_one',
                    'multi_bandit_flag',
                    'multi_bandit_flag_allocation',
                    'bandit_one_multi_flag_variation',
                    'bandit_one_multi_flag_variation'
                )
            ]
        );

        self::$banditReferences['bandit_two'] = new BanditReference(
            'v456',
            [
                new BanditFlagVariation(
                    'bandit_two',
                    'bandit_two_flag',
                    'bandit_two_flag_allocation',
                    'bandit_two_flag_variation',
                    'bandit_two_flag_variation'
                )
            ]
        );

        // `multi_bandit_flag` references two bandits.
        self::$banditReferences['bandit_three'] = new BanditReference(
            'v789',
            [
                new BanditFlagVariation(
                    'bandit_three',
                    'multi_bandit_flag',
                    'multi_bandit_flag_allocation',
                    'bandit_three_multi_flag_variation',
                    'bandit_three_multi_flag_variation'
                )
            ]
        );

        self::$banditReferences['bandit_four'] = new BanditReference(
            'not defined',
            []
        );
    }

    public function testSurvivesSerialization(): void
    {
        $indexer = BanditReferenceIndexer::from(self::$banditReferences);
        $this->assertTrue($indexer->hasBandits());
        $this->assertEquals(
            'bandit_three',
            $indexer->getBanditByVariation('multi_bandit_flag', 'bandit_three_multi_flag_variation')
        );

        $serialized = serialize($indexer);
        $unserialized = unserialize($serialized);
        $this->assertTrue($unserialized->hasBandits());
        $this->assertEquals(
            'bandit_three',
            $unserialized->getBanditByVariation('multi_bandit_flag', 'bandit_three_multi_flag_variation')
        );
    }


    public function testEmptyIndexerWorks(): void
    {
        $indexer = BanditReferenceIndexer::empty();
        $this->assertNull($indexer->getBanditByVariation('bandit_one_flag', 'bandit_one_flag_variation'));
        $this->assertFalse($indexer->hasBandits());
    }

    public function testFromEmpty(): void
    {
        $indexer = BanditReferenceIndexer::from([]);
        $this->assertNull($indexer->getBanditByVariation('bandit_one_flag', 'bandit_one_flag_variation'));
        $this->assertFalse($indexer->hasBandits());
    }

    public function testBanditReferenceWithNoFlagVariations(): void
    {
        $indexer = BanditReferenceIndexer::from(['bandit_one' => new BanditReference('v123', [])]);

        $this->assertNull($indexer->getBanditByVariation('bandit_one', 'bandit_one_flag_variation'));
        $this->assertFalse($indexer->hasBandits());
        $this->assertEquals([], $indexer->getBanditModelVersionReferences());
    }

    public function testGetBanditByVariation(): void
    {
        $indexer = BanditReferenceIndexer::from(self::$banditReferences);
        $this->assertTrue($indexer->hasBandits());

        $this->expectBandit($indexer, null, 'non_bandit_flag', 'control');
        $this->expectBandit($indexer, null, 'bandit_one_flag', 'control');
        $this->expectBandit($indexer, null, 'bandit_one_flag', 'bandit_two_flag_variation');

        $this->expectBandit($indexer, 'bandit_one', 'bandit_one_flag', 'bandit_one_flag_variation');
        $this->expectBandit($indexer, 'bandit_two', 'bandit_two_flag', 'bandit_two_flag_variation');
        $this->expectBandit($indexer, 'bandit_one', 'multi_bandit_flag', 'bandit_one_multi_flag_variation');
        $this->expectBandit($indexer, 'bandit_three', 'multi_bandit_flag', 'bandit_three_multi_flag_variation');

    }

    public function testBadBanditVariation(): void
    {
        // Add an illegal variation to the list for the indexer; bandit_two_flag.bandit_two_flag_variation already maps
        // to `bandit_two`.
        $refsToLoad = self::$banditReferences;
        $refsToLoad['bandit_four'] = new BanditReference(
            'v123',
            [
                new BanditFlagVariation(
                    'bandit_four',
                    'bandit_two_flag',
                    'bandit_four_flag_allocation',
                    'bandit_two_flag_variation',
                    'bandit_two_flag_variation'
                )
            ]
        );

        $this->expectException(InvalidConfigurationException::class);

        BanditReferenceIndexer::from($refsToLoad);
    }

    public function testGetBanditModelsEmptyReferences()
    {
        $indexer = BanditReferenceIndexer::empty();
        $this->assertEquals([], $indexer->getBanditModelVersionReferences());
    }

    public function testGetBanditModelsNoVariations()
    {
        $banditReferences = [
            'bandit1' => new BanditReference('model1', []),
            'bandit2' => new BanditReference('model2', []),
        ];
        $indexer = BanditReferenceIndexer::from($banditReferences);
        $this->assertEquals(
            [],
            $indexer->getBanditModelVersionReferences()
        );
    }

    public function testGetBanditModelVersions()
    {
        $indexer = BanditReferenceIndexer::from(self::$banditReferences);

        $this->assertEquals(
            ['bandit_one' => 'v123', 'bandit_two' => 'v456', 'bandit_three' => 'v789'],
            $indexer->getBanditModelVersionReferences()
        );
    }

    private function expectBandit(
        BanditReferenceIndexer $indexer,
        ?string $banditKey,
        string $flagKey,
        string $variationValue
    ): void {
        $actual = $indexer->getBanditByVariation($flagKey, $variationValue);
        $this->assertEquals(
            $banditKey,
            $actual,
            "Bandit Key [$actual] does not match expected [$banditKey] for $flagKey / $variationValue"
        );
    }
}
