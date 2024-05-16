<?php

namespace Eppo\Tests;

use Eppo\APIRequestWrapper;
use Eppo\Config\SDKData;
use Eppo\ConfigurationStore;
use Eppo\DTO\Flag;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\FlagConfigurationLoader;
use Exception;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Sarahman\SimpleCache\FileSystemCache;
use Throwable;

class FlagConfigurationLoaderTest extends TestCase
{
    /** @var string */
    const FLAG_KEY = 'kill-switch';

    const MOCK_RESPONSE_FILENAME = __DIR__ . '/mockdata/ufc-v1.json';


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

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidApiKeyException
     * @throws HttpRequestException
     * @throws InvalidArgumentException
     */
    public function testLoadsConfiguration(): void
    {
        // Load mock response data
        $response = json_decode(file_get_contents(self::MOCK_RESPONSE_FILENAME), true);
        // Mock the webserver to return the hardcoded config above.
        $flagLoader = $this->getFlagLoaderForData($response['flags']);

        $flag = $flagLoader->getConfiguration(self::FLAG_KEY);
        $this->assertInstanceOf(Flag::class, $flag);
        $this->assertEquals(self::FLAG_KEY, $flag->key);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     */
    public function testPullsConfigurationFromStore(): void
    {
        // Load mock response data
        $response = json_decode(file_get_contents(self::MOCK_RESPONSE_FILENAME), true);

        $cache = new FileSystemCache();

        $httpClientMock = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()])->getMock();

        // Expect no calls made to API wrapper
        $httpClientMock->expects($this->never())
            ->method('get');

        $configStoreMock = $this->getMockBuilder(ConfigurationStore::class)->setConstructorArgs([$cache])->getMock();

        // Expect the config store to be pinged once
        $configStoreMock->expects($this->once())
            ->method('getConfiguration')
            ->with(self::FLAG_KEY)
            ->willReturn($response['flags'][self::FLAG_KEY]);

        // Expect setConfig to not be called
        $configStoreMock->expects($this->never())
            ->method('setConfigurations');

        $fcl =  new FlagConfigurationLoader($httpClientMock, $configStoreMock);

        $this->assertNotNull($fcl->getConfiguration(self::FLAG_KEY));
    }

    /**
     * @throws ClientExceptionInterface
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws HttpRequestException
     */
    public function testThrows(): void
    {
        // Test that FlagLoader throws exceptions when appropriate
        $ex =  new Exception('config requester error');
        $response = json_decode(file_get_contents(self::MOCK_RESPONSE_FILENAME), true);
        // Mock the webserver to return the hardcoded config above.
        $flagLoader = $this->getFlagLoaderForData($response['flags'], $ex);

        $this->expectException(Exception::class);

        $flag = $flagLoader->getConfiguration(self::FLAG_KEY);
    }

    /**
     * @param array $data
     * @param Throwable|null $mockedThrowable
     * @return FlagConfigurationLoader
     */
    private function getFlagLoaderForData(array $data, ?Throwable $mockedThrowable = null): FlagConfigurationLoader
    {
        $cache = new FileSystemCache();

        $httpClientMock = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()])->getMock();
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
