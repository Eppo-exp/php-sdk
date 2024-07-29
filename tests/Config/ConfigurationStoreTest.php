<?php

namespace Eppo\Tests\Config;

use DateTime;
use Eppo\Bandits\BanditReferenceIndexer;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigurationStore;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Bandit\BanditFlagVariation;
use Eppo\DTO\Bandit\BanditModelData;
use Eppo\DTO\BanditReference;
use Eppo\DTO\Flag;
use Eppo\DTO\VariationType;
use Eppo\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigurationStoreTest extends TestCase
{
    public function tearDown(): void
    {
        DefaultCacheFactory::clearCache();
    }

    public function testFlushesCacheOnReload(): void
    {
        $flag1 = new Flag('flag1', true, [], VariationType::STRING, [], 10_000);
        $flag2 = new Flag('flag2', true, [], VariationType::STRING, [], 10_000);
        $flag3 = new Flag('flag3', true, [], VariationType::STRING, [], 10_000);

        $firstFlags = [$flag1, $flag2];

        $secondFlags = [$flag1, $flag3];

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());


        $configStore->setUnifiedFlagConfiguration($firstFlags);

        $this->assertHasFlag($flag1, 'flag1', $configStore);
        $this->assertHasFlag($flag2, 'flag2', $configStore);
        $this->assertHasFlag($flag3, 'flag3', $configStore, hasFlag: false);

        $configStore->setUnifiedFlagConfiguration($secondFlags);

        $this->assertHasFlag($flag1, 'flag1', $configStore);
        $this->assertHasFlag($flag2, 'flag2', $configStore, hasFlag: false);
        $this->assertHasFlag($flag3, 'flag3', $configStore);
    }

    public function testSetsEmptyVariationsWhenNull(): void
    {
        $configStore = new ConfigurationStore(DefaultCacheFactory::create());

        $banditReferences = [
            'bandit' => new BanditReference(
                'v123',
                [
                    new BanditFlagVariation(
                        'bandit',
                        'bandit_flag',
                        'bandit_flag_allocation',
                        'bandit_flag_variation',
                        'bandit_flag_variation'
                    )
                ]
            )
        ];

        $banditVariations = BanditReferenceIndexer::from($banditReferences);

        $configStore->setUnifiedFlagConfiguration([], $banditVariations);

        // Verify Object has been stored.
        $recoveredBanditVariations = $configStore->getBanditReferenceIndex();
        $this->assertNotNull($recoveredBanditVariations);
        $this->assertTrue($recoveredBanditVariations->hasBandits());


        // The action that we're testing
        $configStore->setUnifiedFlagConfiguration([], null);

        // Assert the variations have been emptied.
        $recoveredBanditVariations = $configStore->getBanditReferenceIndex();
        $this->assertNotNull($recoveredBanditVariations);
        $this->assertFalse($recoveredBanditVariations->hasBandits());
    }

    public function testStoresBanditVariations(): void
    {
        $configStore = new ConfigurationStore(DefaultCacheFactory::create());

        $banditReferences = [
            'bandit' => new BanditReference(
                'v123',
                [
                    new BanditFlagVariation(
                        'bandit',
                        'bandit_flag',
                        'bandit_flag_allocation',
                        'bandit_flag_variation',
                        'bandit_flag_variation'
                    )
                ]
            )
        ];

        $banditVariations = BanditReferenceIndexer::from($banditReferences);

        $configStore->setUnifiedFlagConfiguration([], $banditVariations);

        $recoveredBanditVariations = $configStore->getBanditReferenceIndex();

        $this->assertFalse($banditVariations === $recoveredBanditVariations);
        $this->assertEquals(
            $banditVariations->getBanditByVariation('bandit_flag', 'bandit_flag_variation'),
            $recoveredBanditVariations->getBanditByVariation('bandit_flag', 'bandit_flag_variation')
        );
    }

    public function testThrowsOnReservedKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());

        $configStore->setMetadata('banditVariations', ["foo" => "bar"]);
    }

    public function testStoresBandits(): void
    {
        $bandits = [
            'weaklyTheBanditKey' => new Bandit(
                'stronglyTheBanditKey',
                'falcon',
                new DateTime(),
                'v123',
                new BanditModelData(1.0, [], 0.1, 0.1)
            )
        ];

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());
        $configStore->setBandits($bandits);

        $banditOne = $configStore->getBandit('stronglyTheBanditKey');

        $this->assertNull($configStore->getBandit('weaklyTheBanditKey'));
        $this->assertNotNull($banditOne);

        $this->assertEquals('stronglyTheBanditKey', $banditOne->banditKey);
        $this->assertEquals('falcon', $banditOne->modelName);
    }

    private function assertHasFlag(
        Flag $expected,
        string $flagKey,
        ConfigurationStore $configStore,
        bool $hasFlag = true
    ): void {
        $actual = $configStore->getFlag($flagKey);
        if (!$hasFlag) {
            $this->assertNull($actual);
            return;
        }
        $this->assertNotNull($actual);
        $this->assertEquals($actual, $expected);
    }
}
