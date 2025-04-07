<?php

namespace Eppo\Tests\Config;

use Eppo\DTO\Configuration;
use Eppo\Config\ConfigurationStore;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class ConfigurationStoreTest extends TestCase
{
    private $mockCache;
    private ConfigurationStore $store;

    protected function setUp(): void
    {
        $this->mockCache = $this->createMock(CacheInterface::class);
        $this->store = new ConfigurationStore($this->mockCache);
    }

    public function testGetConfigurationReturnsNullWhenEmpty(): void
    {
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->assertNull($this->store->getConfiguration());
    }

    public function testGetConfigurationReturnsCachedConfiguration(): void
    {
        $mockConfig = $this->createMock(Configuration::class);
        
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn($mockConfig);

        $this->assertSame($mockConfig, $this->store->getConfiguration());
    }

    public function testSetConfigurationStoresInCache(): void
    {
        $mockConfig = $this->createMock(Configuration::class);
        
        $this->mockCache->expects($this->once())
            ->method('set')
            ->with('eppo_configuration', $mockConfig);

        $this->store->setConfiguration($mockConfig);
    }

    public function testGetConfigurationHandlesCacheException(): void
    {
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willThrowException(new \Psr\SimpleCache\InvalidArgumentException());

        $this->assertNull($this->store->getConfiguration());
    }
}
