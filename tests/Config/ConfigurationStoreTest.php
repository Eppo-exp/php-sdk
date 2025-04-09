<?php

namespace Eppo\Tests\Config;

use DateTime;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigStore;
use Eppo\Config\Configuration;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Bandit\BanditModelData;
use Eppo\DTO\BanditFlagVariation;
use Eppo\DTO\BanditReference;
use Eppo\DTO\ConfigurationWire\ConfigResponse;
use Eppo\DTO\Flag;
use Eppo\DTO\VariationType;
use PHPUnit\Framework\TestCase;

class ConfigurationStoreTest extends TestCase
{
    public function tearDown(): void
    {
        DefaultCacheFactory::clearCache();
    }

    public function testActivatesNewConfiguration(): void
    {
        $flag1 = new Flag('flag1', true, [], VariationType::STRING, [], 10_000);
        $flag2 = new Flag('flag2', true, [], VariationType::STRING, [], 10_000);
        $flag3 = new Flag('flag3', true, [], VariationType::STRING, [], 10_000);

        $firstFlags = ['flag1' => $flag1, 'flag2' => $flag2];
        $secondFlags = ['flag1' => $flag1, 'flag3' => $flag3];

        $configStore = new ConfigStore(DefaultCacheFactory::create());

        $configuration = Configuration::fromFlags($firstFlags);
        $configStore->setConfiguration($configuration);

        $this->assertHasFlag($flag1, 'flag1', $configStore);
        $this->assertHasFlag($flag2, 'flag2', $configStore);
        $this->assertHasFlag($flag3, 'flag3', $configStore, hasFlag: false);

        $configStore->setConfiguration(Configuration::fromFlags($secondFlags));

        $this->assertHasFlag($flag1, 'flag1', $configStore);
        $this->assertHasFlag($flag2, 'flag2', $configStore, hasFlag: false);
        $this->assertHasFlag($flag3, 'flag3', $configStore);
    }

    public function testStoresBanditVariations(): void
    {
        $configStore = new ConfigStore(DefaultCacheFactory::create());

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

        $banditsConfig = new ConfigResponse();

        $flagsConfig = new ConfigResponse(
            response: json_encode(['banditReferences' => $banditReferences])
        );


        $configStore->setConfiguration(Configuration::fromUfcResponses($flagsConfig, $banditsConfig));

        $this->assertEquals(
            "bandit",
            $configStore->getConfiguration()->getBanditByVariation('bandit_flag', 'bandit_flag_variation')
        );
        $this->assertNull(
            $configStore->getConfiguration()->getBanditByVariation('bandit', 'bandit_flag_variation')
        );
    }

    public function testStoresBandits(): void
    {
        $bandits = [
            'banditIndex' => new Bandit(
                'banditKey',
                'falcon',
                new DateTime(),
                'v123',
                new BanditModelData(1.0, [], 0.1, 0.1)
            )
        ];

        $configStore = new ConfigStore(DefaultCacheFactory::create());
        $configStore->setConfiguration(Configuration::fromFlags([], $bandits));

        $banditOne = $configStore->getConfiguration()->getBandit('banditIndex');

        $this->assertNull($configStore->getConfiguration()->getBandit('banditKey'));
        $this->assertNotNull($banditOne);

        $this->assertEquals('banditKey', $banditOne->banditKey);
        $this->assertEquals('falcon', $banditOne->modelName);
    }

    private function assertHasFlag(
        Flag $expected,
        string $flagKey,
        ConfigStore $configStore,
        bool $hasFlag = true
    ): void {
        $config = $configStore->getConfiguration();
        $actual = $config->getFlag($flagKey);
        if (!$hasFlag) {
            $this->assertNull($actual);
            return;
        }
        $this->assertNotNull($actual);
        $this->assertEquals($actual, $expected);
    }
}
