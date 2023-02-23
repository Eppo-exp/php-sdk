<?php

namespace Eppo\Tests;

use PHPUnit\Framework\TestCase;

use Google\Cloud\Storage\StorageClient;
use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;

class EppoClientTest extends TestCase
{
    /** @var MockWebServer */
    protected static $server;

    public static function setUpBeforeClass() : void {
        self::$server = new MockWebServer;
        self::$server->start();
    }

    public static function tearDownAfterClass() : void {
        // stopping the web server during tear down allows us to reuse the port for later tests
        self::$server->stop();
    }

    private function downloadTestDataFiles() {
        $storage = new StorageClient();
        $bucket = $storage->bucket('sdk-test-data');
        $objects = $bucket->objects(['prefix' => 'assignment/test-case']);
        foreach ($objects as $object) {
            $objectName = $object->name();
            $destinationPath = __DIR__ . '../test-cases/' . $objectName;
            $object->downloadToFile($destinationPath);
        }
    }
}