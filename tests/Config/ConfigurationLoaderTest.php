<?php

namespace Eppo\Tests\Config;

use Eppo\API\APIRequestWrapper;
use Eppo\API\APIResource;
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
            ->willReturn(new APIResource($banditsRaw, true, null));

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());

        $loader = new ConfigurationLoader($apiWrapper, $configStore);
        $loader->fetchAndStoreConfigurations(null);


        $flag = $loader->getFlag(self::FLAG_KEY);
        $this->assertInstanceOf(Flag::class, $flag);
        $this->assertEquals(self::FLAG_KEY, $flag->key);
        $this->assertEquals($flags[self::FLAG_KEY], $flag);

        $this->assertEquals(
            'cold_start_bandit',
            $loader->getBanditByVariation('cold_start_bandit_flag', 'cold_start_bandit')
        );

        $bandit = $loader->getBandit('cold_start_bandit');
        $this->assertNotNull($bandit);
        $this->assertInstanceOf(Bandit::class, $bandit);
        $this->assertEquals('cold_start_bandit', $bandit->banditKey);
    }


    public function testSetsConfigurationTimestamp(): void
    {
        // Load mock response data
        $flagsRaw = file_get_contents(self::MOCK_RESPONSE_FILENAME);
        $flagsResourceResponse = new APIResource(
            $flagsRaw,
            true,
            "ETAG"
        );
        $banditsRaw = '{"bandits": {}}';

        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()]
        )->getMock();

        $apiWrapper->expects($this->exactly(2))
            ->method('getUFC')
            ->willReturnCallback(
                function (?string $eTag) use ($flagsResourceResponse, $flagsRaw) {
                    // Return not modified if the etag sent is not null.
                    return $eTag == null ? $flagsResourceResponse : new APIResource(
                        $flagsRaw,
                        false,
                        "ETAG"
                    );
                }
            );

        $apiWrapper->expects($this->once())
            ->method('getBandits')
            ->willReturn(new APIResource($banditsRaw, true, null));

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());

        $loader = new ConfigurationLoader($apiWrapper, $configStore);
        $loader->fetchAndStoreConfigurations(null);

        $timestamp1 = $configStore->getMetadata("flagTimestamp");
        $storedEtag = $configStore->getMetadata("flagETag");
        $this->assertEquals("ETAG", $storedEtag);

        usleep(50 * 1000); // Sleep long enough for cache to expire.

        $loader->fetchAndStoreConfigurations("ETAG");

        $this->assertEquals("ETAG", $configStore->getMetadata("flagETag"));

        // The timestamp should not have changed; the config did not change, so the timestamp should not be updated.
        $this->assertEquals($timestamp1, $configStore->getMetadata("flagTimestamp"));
    }

    public function testLoadsOnGet(): void
    {
        // Arrange: Load some flag data to be returned by the APIRequestWrapper
        // Load mock response data
        $flagsRaw = file_get_contents(self::MOCK_RESPONSE_FILENAME);
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
            ->willReturn(new APIResource($banditsRaw, true, "ETAG"));

        $flag = $loader->getFlag(self::FLAG_KEY);

        // Assert: non-null flag, api called only once via Mock `expects` above.
        $this->assertNotNull($flag);
    }

    public function testOnlyLoadsBanditsWhereNeeded(): void
    {
        // Set up mock response data.
        $initialFlagsRaw = '{
            "flags": {
            },
            "banditReferences": {
                "cold_starting_bandit": {
                    "modelVersion": "cold start",
                    "flagVariations": [
                        {
                            "key": "cold_starting_bandit",
                            "flagKey": "cold_start_flag",
                            "allocationKey": "cold_start_allocation",
                            "variationKey": "cold_starting_bandit",
                            "variationValue": "cold_starting_bandit"
                        }
                    ]
                }
            }
        }';

        $warmFlagsRaw = '{
            "flags": {
            },
            "banditReferences": {
                "cold_starting_bandit": {
                    "modelVersion": "v1",
                    "flagVariations": [
                        {
                            "key": "cold_starting_bandit",
                            "flagKey": "cold_start_flag",
                            "allocationKey": "cold_start_allocation",
                            "variationKey": "cold_starting_bandit",
                            "variationValue": "cold_starting_bandit"
                        }
                    ]
                }
            }
        }';

        $coldBanditsRaw = '{
            "bandits": {
                "cold_starting_bandit" : {
                    "banditKey": "cold_starting_bandit",
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

        $warmBanditsRaw = '{
            "bandits": {
                "cold_starting_bandit" : {
                    "banditKey": "cold_starting_bandit",
                    "modelName": "falcon",
                    "updatedAt": "2023-09-13T04:52:06.462Z",
                    "modelVersion": "v1",
                    "modelData": {
                        "gamma": 1.0,
                        "defaultActionScore": 0.0,
                        "actionProbabilityFloor": 0.0,
                        "coefficients": {}
                    }
                }
            }
        }';


        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->disableOriginalConstructor()->getMock();

        $apiWrapper->expects($this->exactly(3))
            ->method('getUFC')
            ->willReturnOnConsecutiveCalls(
                new APIResource($initialFlagsRaw, true, "initial"),
                new APIResource($initialFlagsRaw, true, "initialButForced"),
                new APIResource($warmFlagsRaw, true, "warm"),
            );

        $apiWrapper->expects($this->exactly(2))
            ->method('getBandits')
            ->willReturnOnConsecutiveCalls(
                new APIResource($coldBanditsRaw, true, null),
                new APIResource($warmBanditsRaw, true, null),
            );

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());
        $loader = new ConfigurationLoader($apiWrapper, $configStore, optimizedBanditLoading: true);


        // First fetch has the bandit cold
        $loader->fetchAndStoreConfigurations(null);

        $bandit = $loader->getBandit('cold_starting_bandit');
        $this->assertNotNull($bandit);
        $this->assertInstanceOf(Bandit::class, $bandit);
        $this->assertEquals('cold_starting_bandit', $bandit->banditKey);
        $this->assertEquals('cold start', $bandit->modelVersion);


        // Trigger a reload, second fetch shows the bandit as still cold
        $loader->fetchAndStoreConfigurations('initial');

        $bandit = $loader->getBandit('cold_starting_bandit');
        $this->assertNotNull($bandit);
        $this->assertInstanceOf(Bandit::class, $bandit);
        $this->assertEquals('cold_starting_bandit', $bandit->banditKey);
        $this->assertEquals('cold start', $bandit->modelVersion);

        // Trigger a reload, third fetch has the bandit warm with v1
        $loader->fetchAndStoreConfigurations('initialButForced');

        $bandit = $loader->getBandit('cold_starting_bandit');
        $this->assertNotNull($bandit);
        $this->assertInstanceOf(Bandit::class, $bandit);
        $this->assertEquals('cold_starting_bandit', $bandit->banditKey);
        $this->assertEquals('v1', $bandit->modelVersion);
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
        $loader = new ConfigurationLoader($apiWrapper, new ConfigurationStore($cache), cacheAgeLimitMillis: 0);

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->exactly(2))
            ->method('getUFC')
            ->willReturn(new APIResource($flagsRaw, true, "ETAG"));
        $apiWrapper->expects($this->exactly(2))
            ->method('getBandits')
            ->willReturn(new APIResource($banditsRaw, true, "ETAG"));

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

        unset($flagsJson['banditReferences']); // Remove the Bandit Variations from the response
        $flagResponse = json_encode($flagsJson);


        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->disableOriginalConstructor()->getMock();
        $apiWrapper->expects($this->exactly(1))
            ->method('getUFC')
            ->willReturn(new APIResource($flagResponse, true, "ETAG"));
        $apiWrapper->expects($this->exactly(0))
            ->method('getBandits')
            ->willReturn(new APIResource('', true, "ETAG"));

        // Act: Load a flag, expecting the Config loader not to throw and to successfully return the flag.
        $cache = DefaultCacheFactory::create();
        $loader = new ConfigurationLoader($apiWrapper, new ConfigurationStore($cache));
        $flag = $loader->getFlag(self::FLAG_KEY);

        // Assert.
        $this->assertNotNull($flag);
    }
}
