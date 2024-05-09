<?php


use Eppo\Config\SDKData;
use Eppo\ConfigurationStore;
use Eppo\DTO\Flag;

use Eppo\DTO\VariationType;
use Eppo\ExperimentConfigurationRequester;
use Eppo\HttpClient;
use Eppo\UFCParser;
use PHPUnit\Framework\TestCase;
use Sarahman\SimpleCache\FileSystemCache;

class UFCParserTest extends TestCase
{
    /** @var string */
    const FLAG_KEY = 'kill-switch';

    const MOCK_DATA_FILENAME = __DIR__ . '/mockdata/ufc-v1.json';


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

    public function testParsesComplexFlagPayload(): void
    {
        $parser = new UFCParser();
        $ufcPayload = json_decode(file_get_contents(self::MOCK_DATA_FILENAME), true);
        $flags = $ufcPayload['flags'];
        $flag = $parser->parseFlag($flags[self::FLAG_KEY]);

        $this->assertInstanceOf(Flag::class, $flag);

        $this->assertEquals(self::FLAG_KEY, $flag->getKey());
        $this->assertTrue($flag->isEnabled());
        $this->assertEquals(VariationType::BOOLEAN, $flag->getVariationType());

        $this->assertCount(2, $flag->getVariations());
        $this->assertCount(3, $flag->getAllocations());
    }

    /**
     * @param array $data
     * @return ExperimentConfigurationRequester
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
