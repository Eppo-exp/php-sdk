<?php

namespace Eppo\Tests;

use Eppo\Config\SDKData;
use Eppo\ConfigurationStore;
use Eppo\DTO\Flag;
use Eppo\DTO\VariationType;
use Eppo\EppoClient;
use Eppo\Exception\InvalidArgumentException;
use Eppo\ExperimentConfigurationRequester;
use Eppo\FlagConfigurationLoader;
use Eppo\HttpClient;
use Eppo\Logger\LoggerInterface;
use Eppo\PollerInterface;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use PHPUnit\Framework\TestCase;
use Sarahman\SimpleCache\FileSystemCache;
use Throwable;

class FlagConfigurationLoaderTest extends TestCase
{
    /** @var string */
    const FLAG_KEY = 'kill-switch';

    const MOCK_RESPONSE_FILENAME =  __DIR__ . '/mockdata/ufc-v1.json';


    public static function setUpBeforeClass(): void
    {
//        try {
//            MockWebServer::start();
//        } catch (Exception $exception) {
//            self::fail('Failed to start mocked web server: ' . $exception->getMessage());
//        }
    }

    public static function tearDownAfterClass(): void
    {
//        MockWebServer::stop();
    }

    public function testLoadsConfiguration(): void
    {
        // Load mock response data
        $response = json_decode(file_get_contents(self::MOCK_RESPONSE_FILENAME), true);
        // Mock the webserver to return the hardcoded config above.
        $flagLoader = $this->getFlagLoaderForData($response['flags']);

        $flag = $flagLoader->getConfiguration(self::FLAG_KEY);
        $this->assertInstanceOf(Flag::class, $flag);
        $this->assertEquals(self::FLAG_KEY, $flag->getKey());

    }

    public function testPullsConfigurationFromStore(): void
    {
        // Test that FlagLoader gets the config from the configStore and doesn't call fetchAndStore
    }

    public function testFetchesToRefreshStore(): void
    {
        // Test that FlagLoader calls loadAndStore
    }
    /**
     * @param array $data
     * @return FlagConfigurationLoader
     */
    private function getFlagLoaderForData(array $data, ?Throwable $mockedThrowable = null): FlagConfigurationLoader
    {
        $cache = new FileSystemCache();
        $sdkData = new SDKData();

        $httpClientMock = $this->getMockBuilder(HttpClient::class)->setConstructorArgs([
            '',
            'dummy',
            $sdkData
        ])->getMock();
        $httpClientMock->expects($this->any())
            ->method('get')
            ->willReturn('');

        $configStoreMock = $this->getMockBuilder(ConfigurationStore::class)->setConstructorArgs([$cache])->getMock();

        if ($data) {
            $configStoreMock->expects($this->any())
                ->method('getConfiguration')
                ->with(self::FLAG_KEY)
                ->willReturn($data[self::FLAG_KEY]);
        }

        if ($mockedThrowable) {
            $configStoreMock->expects($this->any())
                ->method('getConfiguration')
                ->with(self::FLAG_KEY)
                ->willThrowException($mockedThrowable);
        }

        return new FlagConfigurationLoader($httpClientMock, $configStoreMock);
    }

}
