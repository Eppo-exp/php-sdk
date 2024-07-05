<?php

namespace Eppo\Tests\Bandits;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\DTO\Bandit\BanditVariation;
use Eppo\Exception\InvalidConfigurationException;
use PHPUnit\Framework\TestCase;

final class BanditVariationIndexerTest extends TestCase
{
    /**
     * @var array<string, array<BanditVariation>>
     */
    private static array $variations = [];

    public static function setUpBeforeClass(): void
    {
        // Bandits:Flags can be m:n

        // One bandit referenced by two flags.
        self::$variations['bandit_one'][] = new BanditVariation(
            'bandit_one',
            'bandit_one_flag',
            'bandit_one_flag_variation',
            'bandit_one_flag_variation'
        );
        self::$variations['bandit_one'][] = new BanditVariation(
            'bandit_one',
            'multi_bandit_flag',
            'bandit_one_multi_flag_variation',
            'bandit_one_multi_flag_variation'
        );

        self::$variations['bandit_two'][] = new BanditVariation(
            'bandit_two',
            'bandit_two_flag',
            'bandit_two_flag_variation',
            'bandit_two_flag_variation'
        );

        // `multi_bandit_flag` references two bandits.
        self::$variations['bandit_three'][] = new BanditVariation(
            'bandit_three',
            'multi_bandit_flag',
            'bandit_three_multi_flag_variation',
            'bandit_three_multi_flag_variation'
        );

        self::$variations['bandit_four'] = [];
    }

    public function testIsBanditFlag()
    {
        $indexer = new BanditVariationIndexer(self::$variations);
        $this->assertTrue($indexer->isBanditFlag('bandit_one_flag'));
        $this->assertTrue($indexer->isBanditFlag('multi_bandit_flag'));
        $this->assertTrue($indexer->isBanditFlag('bandit_two_flag'));
        $this->assertFalse($indexer->isBanditFlag('non_bandit_flag'));

        // Should not match bandit key or variation
        $this->assertFalse($indexer->isBanditFlag('bandit_one'));
        $this->assertFalse($indexer->isBanditFlag('bandit_one_flag_variation'));
    }

    public function testGetBanditByVariation(): void
    {
        $indexer = new BanditVariationIndexer(self::$variations);
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
        self::$variations['bandit_four'][] = new BanditVariation(
            'bandit_four',
            'bandit_two_flag',
            'bandit_two_flag_variation',
            'bandit_two_flag_variation'
        );

        $this->expectException(InvalidConfigurationException::class);

        new BanditVariationIndexer(self::$variations);
    }

    private function expectBandit(
        BanditVariationIndexer $indexer,
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
