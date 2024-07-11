<?php

namespace Eppo\Tests\Config;

use Eppo\API\APIResource;
use Eppo\API\APIRequestWrapper;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigurationLoader;
use Eppo\Config\ConfigurationStore;
use Eppo\DTO\Flag;
use Eppo\UFCParser;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18Client;
use PHPUnit\Framework\TestCase;

class ConfigurationLoaderTest extends TestCase
{
    private const FLAG_KEY = 'kill-switch';

    private const MOCK_RESPONSE_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mockdata' .
        DIRECTORY_SEPARATOR . 'ufc-v1.json';


    public function tearDown(): void
    {
        DefaultCacheFactory::clearCache();
    }

    public function testLoadsConfiguration(): void
    {
        // Load mock response data
        $flagsRaw = file_get_contents(self::MOCK_RESPONSE_FILENAME);
        $flagsResourceResponse = new APIResource(
            $flagsRaw,
            time(),
            true,
            "ETAG"
        );
        $flagsJson = json_decode($flagsRaw, true);
        $flags = array_map(fn($flag) => (new UFCParser())->parseFlag($flag), $flagsJson['flags']);

        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()]
        )->getMock();

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->once())
            ->method('get')
            ->willReturn($flagsResourceResponse);

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());

        $loader = new ConfigurationLoader($apiWrapper, $configStore, cacheAgeLimit: PHP_INT_MAX);
        $loader->fetchAndStoreConfigurations(null);


        $flag = $loader->getFlag(self::FLAG_KEY);
        $this->assertInstanceOf(Flag::class, $flag);
        $this->assertEquals(self::FLAG_KEY, $flag->key);
        $this->assertEquals($flags[self::FLAG_KEY], $flag);

        $this->assertTrue($loader->isBanditFlag('cold_start_bandit_flag'));
        $this->assertFalse($loader->isBanditFlag('kill-switch'));
        $this->assertEquals(
            'cold_start_bandit',
            $loader->getBanditByVariation('cold_start_bandit_flag', 'cold_start_bandit')
        );
    }

    public function testLoadsOnGet(): void
    {
        // Arrange: Load some flag data to be returned by the APIRequestWrapper
        // Load mock response data
        $flagsRaw = file_get_contents(self::MOCK_RESPONSE_FILENAME);
        $flagsJson = json_decode($flagsRaw, true);
        $flags = array_map(fn($flag) => (new UFCParser())->parseFlag($flag), $flagsJson['flags']);

        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->disableOriginalConstructor()->getMock();

        $cache = DefaultCacheFactory::create();
        // Act: Create a new FCL and retrieve a flag
        $loader = new ConfigurationLoader($apiWrapper, new ConfigurationStore($cache));

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->once())
            ->method('get')
            ->willReturn(new APIResource($flagsRaw, time(), true, "ETAG"));


        $flag = $loader->getFlag(self::FLAG_KEY);

        // Assert: non-null flag, api called only once via Mock `expects` above.
        $this->assertNotNull($flag);
    }

    public function testReloadsOnExpiredCache(): void
    {
        // Arrange: Load some flag data to be returned by the APIRequestWrapper
        // Load mock response data
        $flagsRaw = file_get_contents(self::MOCK_RESPONSE_FILENAME);
        $flagsJson = json_decode($flagsRaw, true);
        $flags = array_map(fn($flag) => (new UFCParser())->parseFlag($flag), $flagsJson['flags']);

        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->disableOriginalConstructor()->getMock();

        $cache = DefaultCacheFactory::create();
        // Act: Create a new FCL with a 0sec ttl and retrieve a flag
        $loader = new ConfigurationLoader($apiWrapper, new ConfigurationStore($cache), cacheAgeLimit: 0);

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->exactly(2))
            ->method('get')
            ->willReturn(new APIResource($flagsRaw, time(), true, "ETAG"));

        $flag = $loader->getFlag(self::FLAG_KEY);
        $flagAgain = $loader->getFlag(self::FLAG_KEY);

        // Assert: non-null flag, api called only once via Mock `expects` above.
        $this->assertNotNull($flag);
        $this->assertNotNull($flagAgain);
        $this->assertEquals($flag, $flagAgain);
    }
}
