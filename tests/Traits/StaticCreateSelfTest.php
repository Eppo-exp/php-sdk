<?php

namespace Eppo\Tests\Traits;

use Eppo\DTO\ConfigurationWire\ConfigResponse;
use PHPUnit\Framework\TestCase;

class StaticCreateSelfTest extends TestCase
{
    public function testCreateCreatesObjectFromArray(): void
    {
        $data = [
            'response' => '{"key": "value"}',
            'eTag' => 'etag123',
            'fetchedAt' => '2023-10-01T12:00:00Z'
        ];

        // Leverage a class that uses the StaticCreateSelf trait.
        $configResponse = ConfigResponse::create($data);

        $this->assertInstanceOf(ConfigResponse::class, $configResponse);
        $this->assertEquals('{"key": "value"}', $configResponse->response);
        $this->assertEquals('etag123', $configResponse->eTag);
        $this->assertEquals('2023-10-01T12:00:00Z', $configResponse->fetchedAt);
    }
}
