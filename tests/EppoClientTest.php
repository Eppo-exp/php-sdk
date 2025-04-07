<?php

namespace Eppo\Tests;

use Eppo\API\APIRequestWrapper;
use Eppo\API\APIResource;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigurationLoader;
use Eppo\Config\ConfigurationStore;
use Eppo\Config\IConfigurationStore;
use Eppo\Config\SDKData;
use Eppo\DTO\VariationType;
use Eppo\EppoClient;
use Eppo\Exception\EppoClientException;
use Eppo\Exception\EppoClientInitializationException;
use Eppo\Exception\HttpRequestException;
use Eppo\Logger\LoggerInterface;
use Eppo\PollerInterface;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use GuzzleHttp\Psr7\Utils;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use PsrMock\Psr17\RequestFactory;
use PsrMock\Psr7\Response;
use Throwable;
use Eppo\PollingOptions;

class EppoClientTest extends TestCase
{
    private const EXPERIMENT_NAME = 'numeric_flag';
    private const TEST_DATA_PATH = __DIR__ . '/data/ufc/tests';

    private static MockWebServer $mockServer;

    public static function setUpBeforeClass(): void
    {
        try {
            self::$mockServer = MockWebServer::start();
        } catch (Exception $exception) {
            self::fail('Failed to start mocked web server: ' . $exception->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$mockServer->stop();
        DefaultCacheFactory::clearCache();
    }

    public function setUp(): void
    {
        DefaultCacheFactory::clearCache();
    }

    public function testGracefulModeDoesNotThrow()
    {
        $pollerMock = $this->getPollerMock();
        $mockConfigRequester = $this->getFlagConfigurationLoaderMock([], new Exception('config loader error'));
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $subjectAttributes = [['foo' => 3]];
        $client = EppoClient::createTestClient($mockConfigRequester, $pollerMock, $mockLogger, true);

        $defaultObj = json_decode('{}', true);

        $this->assertEquals(
            'default',
            $client->getStringAssignment(self::EXPERIMENT_NAME, 'subject-10', $subjectAttributes, 'default')
        );
        $this->assertEquals(
            100,
            $client->getNumericAssignment(self::EXPERIMENT_NAME, 'subject-10', $subjectAttributes, 100)
        );
        $this->assertFalse(
            $client->getBooleanAssignment(self::EXPERIMENT_NAME, 'subject-10', $subjectAttributes, false)
        );
        $this->assertEquals(
            $defaultObj,
            $client->getJSONAssignment(self::EXPERIMENT_NAME, 'subject-10', $subjectAttributes, $defaultObj)
        );
    }

    public function testNoGracefulModeThrowsOnGetAssignment()
    {
        $pollerMock = $this->getPollerMock();

        $apiRequestWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()]
        )->getMock();

        $apiRequestWrapper->expects($this->any())
            ->method('getUFC')
            ->willThrowException(new HttpRequestException());

        $configStore = $this->getMockBuilder(IConfigurationStore::class)->getMock();
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $this->expectException(EppoClientException::class);

        $flags = $this->getMockBuilder(ConfigurationLoader::class)->disableOriginalConstructor()->getMock();

        $flags->expects($this->once())
            ->method('getFlag')
            ->with(self::EXPERIMENT_NAME)
            ->willThrowException(new Exception());
        $client = EppoClient::createTestClient($flags, $pollerMock, $mockLogger, false);
        $result = $client->getStringAssignment(self::EXPERIMENT_NAME, 'subject-10', [], "default");
    }

    public function testNoGracefulModeThrowsOnInit()
    {
        $pollerMock = $this->getPollerMock();

        $apiRequestWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()]
        )->getMock();

        $apiRequestWrapper->expects($this->any())
            ->method('getUFC')
            ->willThrowException(new HttpRequestException());

        $configStore = $this->getMockBuilder(IConfigurationStore::class)->getMock();
        $configStore->expects($this->any())->method('getMetadata')->willReturn(null);
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $this->expectException(EppoClientInitializationException::class);
        $client = EppoClient::createTestClient(
            new ConfigurationLoader($apiRequestWrapper, $configStore),
            $pollerMock,
            $mockLogger,
            false
        );
    }

    public function testGracefulModeThrowsOnInit()
    {
        $pollerMock = $this->getPollerMock();

        $apiRequestWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()]
        )->getMock();

        $apiRequestWrapper->expects($this->any())
            ->method('getUFC')
            ->willThrowException(new HttpRequestException());

        $configStore = $this->getMockBuilder(IConfigurationStore::class)->getMock();
        $configStore->expects($this->any())->method('getMetadata')->willReturn(null);
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $this->expectException(EppoClientInitializationException::class);
        $client = EppoClient::createTestClient(
            new ConfigurationLoader($apiRequestWrapper, $configStore),
            $pollerMock,
            $mockLogger,
            true
        );
    }

    public function testReturnsDefaultWhenExperimentConfigIsAbsent()
    {
        $configLoaderMock = $this->getFlagConfigurationLoaderMock([]);
        $pollerMock = $this->getPollerMock();

        $client = EppoClient::createTestClient($configLoaderMock, $pollerMock);
        $this->assertEquals(
            'DEFAULT',
            $client->getStringAssignment(self::EXPERIMENT_NAME, 'subject-10', [], 'DEFAULT')
        );
        $this->assertEquals(
            100,
            $client->getIntegerAssignment(self::EXPERIMENT_NAME, 'subject-10', [], 100)
        );
    }

    public function testRepoTestCases(): void
    {
        try {
            $client = EppoClient::init(
                'dummy',
                self::$mockServer->serverAddress,
                isGracefulMode: false,
                throwOnFailedInit: true
            );
        } catch (Exception $exception) {
            self::fail('Failed to initialize EppoClient: ' . $exception->getMessage());
        }

        // Load all the test cases.
        $testCases = $this->loadTestCases();

        foreach ($testCases as $testFile => $test) {
            foreach ($test['subjects'] as $subject) {
                $result = $this->getTypedAssignment(
                    $client,
                    VariationType::from($test['variationType']),
                    $test['flag'],
                    $subject['subjectKey'],
                    $subject['subjectAttributes'],
                    $test['defaultValue']
                );
                $this->assertEquals($subject['assignment'], $result, "$testFile ${test['flag']}");
            }
        }
    }

    /**
     * @throws EppoClientException
     */
    private function getTypedAssignment(
        EppoClient $client,
        VariationType $type,
        string $flag,
        string $subjectKey,
        array $subject,
        array|bool|float|int|string $defaultValue
    ): array|bool|float|int|string|null {
        return match ($type) {
            VariationType::STRING => $client->getStringAssignment($flag, $subjectKey, $subject, $defaultValue),
            VariationType::BOOLEAN => $client->getBooleanAssignment($flag, $subjectKey, $subject, $defaultValue),
            VariationType::NUMERIC => $client->getNumericAssignment($flag, $subjectKey, $subject, $defaultValue),
            VariationType::JSON => $client->getJSONAssignment($flag, $subjectKey, $subject, $defaultValue),
            VariationType::INTEGER => $client->getIntegerAssignment($flag, $subjectKey, $subject, $defaultValue),
            default => throw new Exception('Unexpected match value'),
        };
    }

    private function getFlagConfigurationLoaderMock(
        array $mockedResponse,
        ?Throwable $mockedThrowable = null
    ): ConfigurationLoader {
        $cache = (new DefaultCacheFactory())->create();
        $sdkData = new SDKData();

        $sdkParams = [
            "sdkVersion" => $sdkData->getSdkVersion(),
            "sdkName" => $sdkData->getSdkName()
        ];


        $apiRequestWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs([
            '',
            $sdkParams,
            new Psr18Client(),
            new RequestFactory()
        ])->getMock();
        $apiRequestWrapper->expects($this->any())
            ->method('getUFC')
            ->willReturn(new APIResource('', true, null));

        $configStoreMock = $this->getMockBuilder(ConfigurationStore::class)->setConstructorArgs([$cache])->getMock();

        if ($mockedResponse) {
            $configStoreMock->expects($this->any())
                ->method('getFlag')
                ->with(self::EXPERIMENT_NAME)
                ->willReturn($mockedResponse);
        }

        if ($mockedThrowable) {
            $configStoreMock->expects($this->any())
                ->method('getFlag')
                ->with(self::EXPERIMENT_NAME)
                ->willThrowException($mockedThrowable);
        }

        return new ConfigurationLoader($apiRequestWrapper, $configStoreMock);
    }

    private function getPollerMock()
    {
        return $this->getMockBuilder(PollerInterface::class)->getMock();
    }

    private function getLoggerMock()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $mockLogger->expects($this->once())->method('logAssignment')->with(
            'mock-experiment-allocation1',
            'control',
            'subject-10',
            $this->greaterThan(0),
            $this->anything(),
            'allocation1',
            'mock-experiment'
        );

        return $mockLogger;
    }

    private function loadTestCases(): array
    {
        $files = scandir(self::TEST_DATA_PATH);
        $tests = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $tests[$file] = json_decode(file_get_contents(self::TEST_DATA_PATH . '/' . $file), true);
        }
        return $tests;
    }

    /**
     * @throws EppoClientInitializationException
     * @throws EppoClientException
     */
    public function testInitWithPollingOptions(): void
    {
        $apiKey = 'dummy-api-key';

        $pollingOptions = new PollingOptions(
            cacheAgeLimitMillis: 50,
            pollingIntervalMillis: 10000,
            pollingJitterMillis: 2000
        );

        $response = new Response(stream: Utils::streamFor(file_get_contents(__DIR__ . '/data/ufc/flags-v1.json')));
        $secondResponse = new Response(stream: Utils::streamFor(
            file_get_contents(__DIR__ . '/data/ufc/bandit-flags-v1.json')
        ));

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->atLeast(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls($response, $secondResponse, $secondResponse);

        $client = EppoClient::init(
            $apiKey,
            "fake address",
            httpClient: $httpClient,
            isGracefulMode: false,
            pollingOptions: $pollingOptions,
            throwOnFailedInit: true
        );

        $this->assertEquals(
            3.1415926,
            $client->getNumericAssignment(self::EXPERIMENT_NAME, 'subject-10', [], 0)
        );
        // Wait a little bit for the cache to age out and the mock server to spin up.
        usleep(75 * 1000);

        $this->assertEquals(
            0,
            $client->getNumericAssignment(self::EXPERIMENT_NAME, 'subject-10', [], 0)
        );
    }
}
