<?php

namespace Eppo\Tests\Config;

use Eppo\API\APIResource;
use Eppo\API\APIRequestWrapper;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigurationLoader;
use Eppo\Config\ConfigurationStore;
use Eppo\DTO\Bandit\Bandit;
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
            true,
            "ETAG"
        );
        $flagsJson = json_decode($flagsRaw, true);
        $flags = array_map(fn($flag) => (new UFCParser())->parseFlag($flag), $flagsJson['flags']);
        $banditsRaw = '{
            "bandits": {
                "cold_start_bandit": {
                    "banditKey": "cold_start_bandit",
                    "modelName": "falcon",
                    "updatedAt": "2023-09-13T04:52:06.462Z",
                    "modelVersion": "cold start",
                    "modelData": {
                        "gamma": 1.0,
                        "defaultActionScore": 0.0,
                        "actionProbabilityFloor": 0.0,
                        "coefficients": {}
                    }
                }
            }
        }';

        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()]
        )->getMock();

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->once())
            ->method('getUFC')
            ->willReturn($flagsResourceResponse);
        $apiWrapper->expects($this->once())
            ->method('getBandits')
            ->willReturn($banditsRaw);

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());

        $loader = new ConfigurationLoader($apiWrapper, $configStore);
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

        $bandit = $loader->getBandit('cold_start_bandit');
        $this->assertNotNull($bandit);
        $this->assertInstanceOf(Bandit::class, $bandit);
        $this->assertEquals('cold_start_bandit', $bandit->banditKey);
    }

    public function testLoadsOnGet(): void
    {
        // Arrange: Load some flag data to be returned by the APIRequestWrapper
        // Load mock response data
        $flagsRaw = file_get_contents(self::MOCK_RESPONSE_FILENAME);
        $flagsJson = json_decode($flagsRaw, true);
        $flags = array_map(fn($flag) => (new UFCParser())->parseFlag($flag), $flagsJson['flags']);
        $banditsRaw = '{"bandits": {}}';

        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->disableOriginalConstructor()->getMock();

        $cache = DefaultCacheFactory::create();
        // Act: Create a new FCL and retrieve a flag
        $loader = new ConfigurationLoader($apiWrapper, new ConfigurationStore($cache));

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->once())
            ->method('getUFC')
            ->willReturn(new APIResource($flagsRaw, true, "ETAG"));
        $apiWrapper->expects($this->once())
            ->method('getBandits')
            ->willReturn($banditsRaw);

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
        $banditsRaw = '{"bandits": {}}';

        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->disableOriginalConstructor()->getMock();

        $cache = DefaultCacheFactory::create();
        // Act: Create a new FCL with a 0sec ttl and retrieve a flag
        $loader = new ConfigurationLoader($apiWrapper, new ConfigurationStore($cache), cacheAgeLimit: 0);

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->exactly(2))
            ->method('getUFC')
            ->willReturn(new APIResource($flagsRaw, true, "ETAG"));
        $apiWrapper->expects($this->exactly(2))
            ->method('getBandits')
            ->willReturn($banditsRaw);

        $flag = $loader->getFlag(self::FLAG_KEY);
        $flagAgain = $loader->getFlag(self::FLAG_KEY);

        // Assert: non-null flag, api called only once via Mock `expects` above.
        $this->assertNotNull($flag);
        $this->assertNotNull($flagAgain);
        $this->assertEquals($flag, $flagAgain);
    }

    public function testRunsWithoutBandits(): void
    {
        // Arrange: Load some flag data to be returned by the APIRequestWrapper
        // Load mock response data
        $flagsJson = json_decode(file_get_contents(self::MOCK_RESPONSE_FILENAME), true);

        unset($flagsJson['bandits']); // Remove the Bandit Variations from the response
        $flagResponse = json_encode($flagsJson);


        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->disableOriginalConstructor()->getMock();
        $apiWrapper->expects($this->exactly(1))
            ->method('getUFC')
            ->willReturn($flagResponse);
        $apiWrapper->expects($this->exactly(1))
            ->method('getBandits')
            ->willReturn("");

        // Act: Load a flag, expecting the Config loader not to throw and to successfully return the flag.
        $cache = DefaultCacheFactory::create();
        $loader = new ConfigurationLoader($apiWrapper, new ConfigurationStore($cache));
        $flag = $loader->getFlag(self::FLAG_KEY);

        // Assert.
        $this->assertNotNull($flag);
    }
}
