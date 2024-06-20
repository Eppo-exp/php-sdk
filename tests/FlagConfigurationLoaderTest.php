<?php

namespace Eppo\Tests;

use Eppo\APIRequestWrapper;
use Eppo\DTO\Flag;
use Eppo\Flags\FlagConfigurationLoader;
use Eppo\Flags\IConfigurationStore;
use Eppo\UFCParser;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18Client;
use PHPUnit\Framework\TestCase;

class FlagConfigurationLoaderTest extends TestCase
{
    /** @var string */
    const FLAG_KEY = 'kill-switch';

    const MOCK_RESPONSE_FILENAME = __DIR__ . '/mockdata/ufc-v1.json';

    public function testLoadsConfiguration(): void
    {
        // Load mock response data
        $flagsRaw = file_get_contents(self::MOCK_RESPONSE_FILENAME);
        $flagsJson = json_decode($flagsRaw, true);
        $flags = array_map(fn ($flag) => (new UFCParser())->parseFlag($flag), $flagsJson['flags']);

        $apiWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs(
            ['', [], new Psr18Client(), new Psr17Factory()])->getMock();

        // Mocks verify interaction of loader <--> API requests and loader <--> config store
        $apiWrapper->expects($this->once())
            ->method('get')
            ->willReturn($flagsRaw);

        $configStore = $this->getMockBuilder(IConfigurationStore::class)->getMock();
        $configStore->expects($this->once())
            ->method('get')->with(self::FLAG_KEY)
            ->willReturn($flags[self::FLAG_KEY]);

        $configStore->expects($this->once())
            ->method('setFlags')->with($flags);

        $loader = new FlagConfigurationLoader($apiWrapper, $configStore);
        $loader->fetchAndStoreConfigurations();


        $flag = $loader->get(self::FLAG_KEY);
        $this->assertInstanceOf(Flag::class, $flag);
        $this->assertEquals(self::FLAG_KEY, $flag->key);
        $this->assertEquals($flags[self::FLAG_KEY], $flag);
    }
}
