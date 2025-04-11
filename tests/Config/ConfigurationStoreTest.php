<?php

namespace Eppo\Tests\Config;

use DateTime;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\Configuration;
use Eppo\Config\ConfigurationStore;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Bandit\BanditModelData;
use Eppo\DTO\BanditFlagVariation;
use Eppo\DTO\BanditReference;
use Eppo\DTO\ConfigurationWire\ConfigResponse;
use Eppo\DTO\ConfigurationWire\ConfigurationWire;
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

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());

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

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());
        $configStore->setConfiguration(Configuration::fromFlags([], $bandits));

        $banditOne = $configStore->getConfiguration()->getBandit('banditIndex');

        $this->assertNull($configStore->getConfiguration()->getBandit('banditKey'));
        $this->assertNotNull($banditOne);

        $this->assertEquals('banditKey', $banditOne->banditKey);
        $this->assertEquals('falcon', $banditOne->modelName);
    }

    public function testGetConfigurationFromCache(): void
    {
        $mockCache = new MockCache();
        $configKey = "EPPO_configuration_v1";

        $flag = new Flag('test_flag', true, [], VariationType::STRING, [], 10_000);
        $flags = ['test_flag' => $flag];
        $configuration = Configuration::fromFlags($flags);
        $configWire = $configuration->toConfigurationWire();

        $mockCache->set($configKey, json_encode($configWire->toArray()));

        $configStore = new ConfigurationStore($mockCache);

        $retrievedConfig = $configStore->getConfiguration();

        $this->assertNotNull($retrievedConfig);
        $this->assertNotNull($retrievedConfig->getFlag('test_flag'));
        $this->assertEquals($flag, $retrievedConfig->getFlag('test_flag'));
    }

    public function testSetConfigurationToCache(): void
    {
        $mockCache = new MockCache();
        $configStore = new ConfigurationStore($mockCache);

        $flag = new Flag('test_flag', true, [], VariationType::STRING, [], 10_000);
        $flags = ['test_flag' => $flag];
        $configuration = Configuration::fromFlags($flags);

        $configStore->setConfiguration($configuration);

        $cacheData = $mockCache->getCache();
        $this->assertArrayHasKey("EPPO_configuration_v1", $cacheData);

        $cachedConfig = json_decode($cacheData["EPPO_configuration_v1"], true);
        $this->assertIsArray($cachedConfig);

        $configWire = ConfigurationWire::fromJson($cachedConfig);
        $reconstructedConfig = Configuration::fromConfigurationWire($configWire);

        $this->assertNotNull($reconstructedConfig->getFlag('test_flag'));
        $this->assertEquals($flag->key, $reconstructedConfig->getFlag('test_flag')->key);
    }

    public function testGetConfigurationWithEmptyCache(): void
    {
        $mockCache = new MockCache();
        $configStore = new ConfigurationStore($mockCache);

        $configuration = $configStore->getConfiguration();

        $this->assertNotNull($configuration);
        $this->assertNull($configuration->getFlag('any_flag'));
    }

    public function testGetConfigurationWithCacheException(): void
    {
        $mockCache = new MockCache(throwOnGet: true);
        $configStore = new ConfigurationStore($mockCache);

        $configuration = $configStore->getConfiguration();

        $this->assertNotNull($configuration);
        $this->assertNull($configuration->getFlag('any_flag'));
    }

    public function testSetConfigurationWithCacheException(): void
    {
        $mockCache = new MockCache(throwOnSet: true);
        $configStore = new ConfigurationStore($mockCache);

        $flag = new Flag('test_flag', true, [], VariationType::STRING, [], 10_000);
        $flags = ['test_flag' => $flag];
        $configuration = Configuration::fromFlags($flags);

        try {
            $configStore->setConfiguration($configuration);
            $this->assertTrue(true); // If we get here, no exception was thrown
        } catch (\Exception $e) {
            $this->fail('setConfiguration should not throw exceptions from cache');
        }
    }

    public function testConfigurationInAndOut(): void
    {
        $mockCache = new MockCache();
        $configStore = new ConfigurationStore($mockCache);
        $configuration = Configuration::fromConfigurationWire($this->getBanditConfigurationWire());
        $configStore->setConfiguration($configuration);

        $newConfigStore = new ConfigurationStore($mockCache);
        $retrievedConfig = $newConfigStore->getConfiguration();

        $this->assertNotNull($retrievedConfig);
        $this->assertNotNull($retrievedConfig->getFlag('banner_bandit_flag'));

        $this->assertEquals(
            $configuration->toConfigurationWire()->toArray(),
            $retrievedConfig->toConfigurationWire()->toArray()
        );
    }


    private function getBanditConfigurationWire(): ConfigurationWire
    {
        $jsonData = file_get_contents(dirname(__DIR__) . '/data/configuration-wire/bandit-flags-v1.json');
        $this->assertNotFalse($jsonData, 'Failed to load test data file');

        $configData = json_decode($jsonData, true);
        $this->assertIsArray($configData, 'Failed to parse JSON data');

        return ConfigurationWire::fromJson($configData);
    }

    private function assertHasFlag(
        Flag $expected,
        string $flagKey,
        ConfigurationStore $configStore,
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
