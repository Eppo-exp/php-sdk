<?php

namespace Eppo\Tests\Cache;

use Eppo\Cache\CacheType;
use Eppo\Cache\DefaultCacheFactory;
use Eppo\Cache\NamespaceCache;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use voku\cache\Cache;

class NamespaceCacheTest extends TestCase
{
    private NamespaceCache $cache;
    private CacheInterface $baseCache;


    public function setUp(): void
    {
        $this->baseCache = DefaultCacheFactory::create();
        $this->cache = new NamespaceCache(CacheType::FLAG, $this->baseCache);
    }
    public function tearDown(): void
    {
        $this->baseCache->clear();
    }

    public function testSetAndGet()
    {
        $key = 'key1';
        $value = 'data';

        $key2 = 'key2';
        $value2 = ['foo' => 'bar', 'bar' => 'baz'];

        $this->cache->set($key, $value);

        $this->assertEquals($value, $this->cache->get($key));

        $this->cache->set($key2, $value2);

        $this->assertEquals($value2, $this->cache->get($key2));
        $this->assertEquals($value, $this->cache->get($key));
    }

    public function testGetWithMissingKey()
    {
        $key = 'missing_key';
        $default = 'default_value';

        $this->assertEquals($default, $this->cache->get($key, $default));
        $this->assertNull($this->cache->get($key));
    }

    public function testDelete()
    {
        $key = 'key';
        $value = 'data';
        $this->cache->set($key, $value);


        $this->assertNotNull($this->cache->get($key));
        $this->assertTrue($this->cache->delete($key));
        $this->assertNull($this->cache->get($key));
    }

    public function testClear()
    {
        $values = ['key1' => 'foo', 'key2' => 'bar'];

        $this->cache->setMultiple($values);

        $this->assertNotNull($this->cache->get('key1'));
        $this->assertNotNull($this->cache->get('key2'));
        $this->assertTrue($this->cache->clear());
        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
    }

    public function testSetAndGetMultiple()
    {
        $keys = ['key1', 'key2'];
        $values = ['foo', 'bar'];
        $array = ['key1' => 'foo', 'key2' => 'bar'];
        $this->cache->setMultiple($array);

        $result = $this->cache->getMultiple($keys);
        $this->assertEquals(array_combine($keys, $values), $result);
    }

    public function testSetAndGetMultipleDisjoint()
    {
        $getKeys = ['key2', 'key4'];
        $values = ['bar', null];
        $setData = ['key1' => 'foo', 'key2' => 'bar', 'key3' => 'baz'];
        $this->cache->setMultiple($setData);

        $result = $this->cache->getMultiple($getKeys);
        $this->assertEquals(array_combine($getKeys, $values), $result);
    }

    public function testDeleteMultiple(): void
    {
        $setData = ['key1' => 'foo', 'key2' => 'bar', 'key3' => 'baz'];
        $this->cache->setMultiple($setData);

        $this->assertTrue($this->cache->deleteMultiple(['key3', 'key1']));
        $result = $this->cache->getMultiple(array_keys($setData));

        $this->assertEquals(['key1' => null, 'key2' => 'bar', 'key3' => null], $result);
    }
    public function testHas(): void
    {
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->has('foo'));
        $this->assertFalse($this->cache->has('bar'));
    }

    public function testClearDoesNotAffectOtherCaches(): void
    {
        $setData = ['key1' => 'foo', 'key2' => 'bar', 'key3' => 'baz'];
        $this->cache->setMultiple($setData);

        $otherCache = new NamespaceCache(CacheType::META, $this->baseCache);
        $otherCache->set('key1', 'NOTFOO');

        $this->cache->clear();

        $this->assertNotNull($otherCache->get('key1'));
        $this->assertEquals('NOTFOO', $otherCache->get('key1'));
    }

    public function testSetDoesNotAffectOtherCaches(): void
    {
        $setData = ['key1' => 'foo', 'key2' => 'bar', 'key3' => 'baz'];
        $differentData = ['key1' => '012345', 'key2' => '67890', 'key3' => 1024];
        $this->cache->setMultiple($setData);

        $otherCache = new NamespaceCache(CacheType::META, $this->baseCache);

        $otherCache->setMultiple($differentData);

        $this->assertEquals($differentData, $otherCache->getMultiple(array_keys($differentData)));
        $this->assertEquals($setData, $this->cache->getMultiple(array_keys($setData)));
    }
}
