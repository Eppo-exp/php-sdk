<?php

namespace Eppo\Tests;

use Eppo\Config\SDKData;
use PHPUnit\Framework\TestCase;

final class SDKDataTest extends TestCase
{
    public function testGetVersion()
    {
        $data = new SDKData();
        $this->assertStringStartsWith('dev-', $data->getSdkVersion());
    }

    public function testGetSdkName()
    {
        $data = new SDKData();
        $this->assertEquals('eppo/php-sdk', $data->getSdkName());
    }
}
