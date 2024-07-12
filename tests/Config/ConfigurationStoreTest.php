<?php

namespace Eppo\Tests\Config;

use DateTime;
use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigurationStore;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Bandit\BanditModelData;
use Eppo\DTO\Bandit\BanditVariation;
use Eppo\DTO\Flag;
use Eppo\DTO\VariationType;
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


        $configStore->setConfigurations($firstFlags, []);

        $this->assertHasFlag($flag1, 'flag1', $configStore);
        $this->assertHasFlag($flag2, 'flag2', $configStore);
        $this->assertHasFlag($flag3, 'flag3', $configStore, hasFlag: false);

        $configStore->setConfigurations($secondFlags, []);

        $this->assertHasFlag($flag1, 'flag1', $configStore);
        $this->assertHasFlag($flag2, 'flag2', $configStore, hasFlag: false);
        $this->assertHasFlag($flag3, 'flag3', $configStore);
    }

    public function testStoresBanditVariations(): void
    {
        $configStore = new ConfigurationStore(DefaultCacheFactory::create());

        $variations = [
            'bandit' => [
                new BanditVariation(
                    'bandit',
                    'bandit_flag',
                    'bandit_flag_variation',
                    'bandit_flag_variation'
                )
            ]
        ];

        $banditVariations = new BanditVariationIndexer($variations);

        $configStore->setConfigurations([], [], $banditVariations);

        $recoveredBanditVariations = $configStore->getBanditVariations();

        $this->assertFalse($banditVariations === $recoveredBanditVariations);
        $this->assertEquals(
            $banditVariations->isBanditFlag('bandit_flag'),
            $recoveredBanditVariations->isBanditFlag('bandit_flag')
        );
        $this->assertEquals(
            $banditVariations->getBanditByVariation('bandit_flag', 'bandit_flag_variation'),
            $recoveredBanditVariations->getBanditByVariation('bandit_flag', 'bandit_flag_variation')
        );
    }

    public function testStoresBandits(): void
    {
        $bandits = [
            'banditOne' => new Bandit(
                'banditOne',
                'falcon',
                new DateTime(),
                'v123',
                new BanditModelData(1.0, [], 0.1, 0.1)
            )
        ];

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());
        $configStore->setConfigurations([], $bandits);

        $banditOne = $configStore->getBandit('banditOne');

        $this->assertNotNull($banditOne);

        $this->assertEquals('banditOne', $banditOne->banditKey);
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
