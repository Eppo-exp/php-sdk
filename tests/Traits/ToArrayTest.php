<?php

namespace Eppo\Tests\Traits;

use Eppo\DTO\ConfigResponse;
use PHPUnit\Framework\TestCase;

class ToArrayTest extends TestCase
{
    public function testToArrayConvertsObjectToArray(): void
    {
        // Leverage a class that uses the ToArray trait.
        $configResponse = new ConfigResponse();
        $configResponse->response = '{"key": "value"}';
        $configResponse->eTag = 'etag123';
        $configResponse->fetchedAt = '2023-10-01T12:00:00Z';

        $array = $configResponse->toArray();

        $expected = [
            'response' => '{"key": "value"}',
            'eTag' => 'etag123',
            'fetchedAt' => '2023-10-01T12:00:00Z'
        ];

        $this->assertEquals($expected, $array);
    }
}
