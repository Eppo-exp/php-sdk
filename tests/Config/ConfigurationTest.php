<?php

namespace Eppo\Tests\Config;

use Eppo\Config\Configuration;
use Eppo\DTO\ConfigurationWire;
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

        $configurationWire = new ConfigurationWire();
        $configurationWire->version = $configData['version'];

        $configResponse = new \Eppo\DTO\ConfigResponse();
        $configResponse->response = $configData['config']['response'];
        $configResponse->eTag = $configData['config']['eTag'] ?? null;
        $configResponse->fetchedAt = $configData['config']['fetchedAt'] ?? null;
        $configurationWire->config = $configResponse;

        if (isset($configData['bandits'])) {
            $banditsResponse = new \Eppo\DTO\ConfigResponse();
            $banditsResponse->response = $configData['bandits']['response'];
            $banditsResponse->eTag = $configData['bandits']['eTag'] ?? null;
            $banditsResponse->fetchedAt = $configData['bandits']['fetchedAt'] ?? null;
            $configurationWire->bandits = $banditsResponse;
        }

        $flagConfigData = json_decode($configurationWire->config->response, true);
        if (isset($flagConfigData['environment']) && is_array($flagConfigData['environment'])) {
            $flagConfigData['environment'] = $flagConfigData['environment']['name'] ?? 'Test';
            $configurationWire->config->response = json_encode($flagConfigData);
        }

        if ($configurationWire->bandits) {
            $banditData = json_decode($configurationWire->bandits->response, true);
            if (!isset($banditData['bandits'])) {
                $banditData = ['bandits' => $banditData];
                $configurationWire->bandits->response = json_encode($banditData);
            }
        }

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
