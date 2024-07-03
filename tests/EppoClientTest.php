<?php

namespace Eppo\Tests;

use Eppo\APIRequestWrapper;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\SDKData;
use Eppo\ConfigurationStore;
use Eppo\DTO\VariationType;
use Eppo\EppoClient;
use Eppo\Exception\EppoClientException;
use Eppo\Exception\EppoClientInitializationException;
use Eppo\Exception\HttpRequestException;
use Eppo\ConfigurationLoader;
use Eppo\IConfigurationStore;
use Eppo\Logger\LoggerInterface;
use Eppo\PollerInterface;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18Client;
use PHPUnit\Framework\TestCase;
use PsrMock\Psr17\RequestFactory;
use Sarahman\SimpleCache\FileSystemCache;
use Throwable;

class EppoClientTest extends TestCase
{

    const EXPERIMENT_NAME = 'numeric_flag';
    const TEST_DATA_PATH = __DIR__ . '/data/ufc/tests';

    public static function setUpBeforeClass(): void
    {
        try {
            MockWebServer::start();
        } catch (Exception $exception) {
            self::fail('Failed to start mocked web server: ' . $exception->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        MockWebServer::stop();
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
        $client = EppoClient::createTestClient($mockConfigRequester, $pollerMock, $mockLogger);

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
            ['', [], new Psr18Client(), new Psr17Factory()])->getMock();

        $apiRequestWrapper->expects($this->any())
            ->method('get')
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
            ['', [], new Psr18Client(), new Psr17Factory()])->getMock();

        $apiRequestWrapper->expects($this->any())
            ->method('get')
            ->willThrowException(new HttpRequestException());

        $configStore = $this->getMockBuilder(IConfigurationStore::class)->getMock();
        $configStore->expects($this->any())->method('getFlagCacheAgeSeconds')->willReturn(-1);
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $this->expectException(EppoClientInitializationException::class);
        $client = EppoClient::createTestClient(new ConfigurationLoader($apiRequestWrapper, $configStore), $pollerMock, $mockLogger, false);
    }
    public function testGracefulModeThrowsOnInit()
    {
        $pollerMock = $this->getPollerMock();

        $apiRequestWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()])->getMock();

        $apiRequestWrapper->expects($this->any())
            ->method('get')
            ->willThrowException(new HttpRequestException());

        $configStore = $this->getMockBuilder(IConfigurationStore::class)->getMock();
        $configStore->expects($this->any())->method('getFlagCacheAgeSeconds')->willReturn(-1);
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $this->expectException(EppoClientInitializationException::class);
        $client = EppoClient::createTestClient(new ConfigurationLoader($apiRequestWrapper, $configStore), $pollerMock, $mockLogger);
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
            EppoClient::init('dummy', 'http://localhost:4000', isGracefulMode: false);
        } catch (Exception $exception) {
            self::fail('Failed to initialize EppoClient: ' . $exception->getMessage());
        }

        // Load all the test cases.
        $testCases = $this->loadTestCases();
        $client = EppoClient::getInstance();

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
            ->method('get')
            ->willReturn('');

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
}
