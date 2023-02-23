<?php

namespace Eppo\Tests;

use Google\Cloud\Storage\StorageClient;

class TestFilesHelper
{
    const TEST_DATA_DIR = __DIR__ . '/data/';

    const ASSIGNMENT_TEST_DATA_DIR = self::TEST_DATA_DIR . 'assignment-v2/';


    private $storage;

    private $bucket;

    public function __construct($bucketName)
    {
        $this->storage = new StorageClient();
        $this->bucket = $this->storage->bucket($bucketName);

        if (!file_exists(self::TEST_DATA_DIR)) {
            mkdir(self::TEST_DATA_DIR, 0777, true);
            mkdir(self::ASSIGNMENT_TEST_DATA_DIR, 0777, true);
        }
    }

    public function downloadTestFiles()
    {
        $objects = $this->bucket->objects();

        foreach ($objects as $object) {
            $fileName = $object->name();
            $fileDestination = self::TEST_DATA_DIR . '/' . $fileName;
            $object->downloadToFile($fileDestination);
        }
    }
}
