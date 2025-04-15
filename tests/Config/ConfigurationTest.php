<?php

namespace Eppo\Tests\Config;

use Eppo\Config\Configuration;
use Eppo\DTO\ConfigurationWire\ConfigurationWire;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    private string $testDataPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDataPath = dirname(__DIR__) . '/data/configuration-wire/';
    }

    public function testFromConfigurationWire(): void
    {
        $jsonData = file_get_contents($this->testDataPath . 'bandit-flags-v1.json');
        $this->assertNotFalse($jsonData, 'Failed to load test data file');

        $configData = json_decode($jsonData, true);
        $this->assertIsArray($configData, 'Failed to parse JSON data');

        $configurationWire = ConfigurationWire::fromArray($configData);
        $configuration = Configuration::fromConfigurationWire($configurationWire);

        $this->assertInstanceOf(Configuration::class, $configuration);

        $nonBanditFlag = $configuration->getFlag('non_bandit_flag');
        $this->assertNotNull($nonBanditFlag);
        $this->assertEquals('non_bandit_flag', $nonBanditFlag->key);
        $this->assertTrue($nonBanditFlag->enabled);

        $bannerBanditFlag = $configuration->getFlag('banner_bandit_flag');
        $this->assertNotNull($bannerBanditFlag);
        $this->assertEquals('banner_bandit_flag', $bannerBanditFlag->key);
        $this->assertTrue($bannerBanditFlag->enabled);

        $banditKey = $configuration->getBanditByVariation('banner_bandit_flag', 'banner_bandit');
        $this->assertEquals('banner_bandit', $banditKey);

        $bandit = $configuration->getBandit('banner_bandit');
        $this->assertNotNull($bandit);
        $this->assertEquals('banner_bandit', $bandit->banditKey);
        $this->assertEquals('123', $bandit->modelVersion);

        $newConfigurationWire = $configuration->toConfigurationWire();
        $this->assertInstanceOf(ConfigurationWire::class, $newConfigurationWire);
        $this->assertEquals(1, $newConfigurationWire->version);
        $this->assertNotNull($newConfigurationWire->config);
        $this->assertNotNull($newConfigurationWire->bandits);
    }
}
