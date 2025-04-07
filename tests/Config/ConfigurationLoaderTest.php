<?php

namespace Eppo\Tests\Config;

use Eppo\API\APIRequestWrapper;
use Eppo\API\APIResource;
use Eppo\Bandits\BanditReferenceIndexer;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigurationLoader;
use Eppo\Config\ConfigurationStore;
use Eppo\DTO\Bandit\Bandit;

use Eppo\DTO\Flag;
use PHPUnit\Framework\TestCase;

class ConfigurationLoaderTest extends TestCase
{
    private const FLAG_KEY = 'kill-switch';

    private const MOCK_RESPONSE_FILENAME = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'mockdata' .
    DIRECTORY_SEPARATOR . 'ufc-v1.json';

    private $mockAPI;
    private $mockStore;
    private ConfigurationLoader $loader;

    protected function setUp(): void
    {
        $this->mockAPI = $this->createMock(APIRequestWrapper::class);
        $this->mockStore = $this->createMock(ConfigurationStore::class);
        $this->loader = new ConfigurationLoader(
            $this->mockAPI,
            $this->mockStore,
            30000
        );
    }

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

        $banditsRaw = '{
            "bandits": {
                "cold_start_bandit": {
                    "banditKey": "cold_start_bandit",
                    "modelName": "falcon",
                    "modelVersion": "cold start",
                    "modelData": {
                        "gamma": 1.0,
                        "defaultActionScore": 0.0
                    }
                }
            }
        }';

        $apiWrapper = $this->createMock(APIRequestWrapper::class);
        $apiWrapper->expects($this->once())
            ->method('getUFC')
            ->willReturn($flagsResourceResponse);
        $apiWrapper->expects($this->once())
            ->method('getBandits')
            ->willReturn(new APIResource($banditsRaw, true, null));

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());
        $loader = new ConfigurationLoader($apiWrapper, $configStore);
        $loader->fetchAndStoreConfigurations(null);

        $config = $configStore->getConfiguration();
        $this->assertNotNull($config);
        
        $flag = $config->getFlag(self::FLAG_KEY);
        $this->assertInstanceOf(Flag::class, $flag);
        $this->assertEquals(self::FLAG_KEY, $flag->key);

        $bandit = $config->getBandit('cold_start_bandit');
        $this->assertNotNull($bandit);
        $this->assertEquals('cold_start_bandit', $bandit->banditKey);
    }

    public function testLoadsOnGet(): void
    {
        // Arrange: Load some flag data to be returned by the APIRequestWrapper
        // Load mock response data
        $flagsRaw = file_get_contents(self::MOCK_RESPONSE_FILENAME);
        $banditsRaw = '{"bandits": {}}';

        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->disableOriginalConstructor()->getMock();

        $cache = DefaultCacheFactory::create();
        $configStore = new ConfigurationStore($cache);
        // Act: Create a new FCL and retrieve a flag
        $loader = new ConfigurationLoader($apiWrapper, $configStore);

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->once())
            ->method('getUFC')
            ->willReturn(new APIResource($flagsRaw, true, "ETAG"));
        $apiWrapper->expects($this->once())
            ->method('getBandits')
            ->willReturn(new APIResource($banditsRaw, true, "ETAG"));

        $flag = $configStore->getConfiguration()->getFlag(self::FLAG_KEY);

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
        $configStore = new ConfigurationStore($cache);
        // Act: Create a new FCL with a 0sec ttl and retrieve a flag
        $loader = new ConfigurationLoader($apiWrapper, $configStore, cacheAgeLimitMillis: 0);

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->exactly(2))
            ->method('getUFC')
            ->willReturn(new APIResource($flagsRaw, true, "ETAG"));
        $apiWrapper->expects($this->exactly(2))
            ->method('getBandits')
            ->willReturn(new APIResource($banditsRaw, true, "ETAG"));

        $flag = $configStore->getConfiguration()->getFlag(self::FLAG_KEY);
        $flagAgain = $configStore->getConfiguration()->getFlag(self::FLAG_KEY);

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
        $configStore = new ConfigurationStore($cache);
        $loader = new ConfigurationLoader($apiWrapper, $configStore);
        $flag = $configStore->getConfiguration()->getFlag(self::FLAG_KEY);

        // Assert.
        $this->assertNotNull($flag);
    }

    public function testFetchAndStoreConfigurationsWithNoChanges(): void
    {
        $mockResponse = new HTTPResponse(
            isModified: false,
            body: '',
            ETag: 'etag123'
        );

        $this->mockAPI->expects($this->once())
            ->method('getUFC')
            ->willReturn($mockResponse);

        $this->mockStore->expects($this->never())
            ->method('setConfiguration');

        $this->loader->fetchAndStoreConfigurations('etag123');
    }

    public function testFetchAndStoreConfigurationsWithNewData(): void
    {
        $mockResponse = new HTTPResponse(
            isModified: true,
            body: json_encode([
                'flags' => [
                    ['key' => 'test_flag', 'enabled' => true]
                ],
                'banditReferences' => []
            ]),
            ETag: 'newEtag123'
        );

        $this->mockAPI->expects($this->once())
            ->method('getUFC')
            ->willReturn($mockResponse);

        $this->mockStore->expects($this->once())
            ->method('setConfiguration')
            ->with($this->callback(function (Configuration $config) {
                return $config->eTag === 'newEtag123' 
                    && !empty($config->flags)
                    && $config->flags[0]->key === 'test_flag';
            }));

        $this->loader->fetchAndStoreConfigurations('oldEtag');
    }

    public function testReloadConfigurationIfExpired(): void
    {
        $mockConfig = new Configuration(
            flags: [],
            bandits: [],
            banditReferenceIndexer: BanditReferenceIndexer::empty(),
            eTag: 'test-etag',
            fetchedAt: time() * 1000 - 31000 // 31 seconds ago
        );

        $this->mockStore->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($mockConfig);

        $this->mockAPI->expects($this->once())
            ->method('getUFC')
            ->willReturn(new HTTPResponse(true, '{"flags":[],"banditReferences":[]}', 'new-etag'));

        $this->loader->reloadConfigurationIfExpired();
    }

    public function testFetchBanditsIfNeededWithExistingBandits(): void
    {
        $mockConfig = new Configuration(
            flags: [],
            bandits: [
                new Bandit('test_bandit', 'v1', [], 'test')
            ],
            banditReferenceIndexer: BanditReferenceIndexer::empty(),
            eTag: 'test-etag',
            fetchedAt: time() * 1000
        );

        $this->mockStore->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($mockConfig);

        $indexer = BanditReferenceIndexer::from([
            /* add test bandit references with same version */
        ]);

        $result = $this->invokePrivateMethod($this->loader, 'fetchBanditsIfNeeded', [$indexer]);
        $this->assertEquals($mockConfig->bandits, $result);
    }

    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
