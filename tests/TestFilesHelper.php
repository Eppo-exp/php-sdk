<?php

namespace Eppo\Tests;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;

class TestFilesHelper
{
    /** @var string */
    const TEST_DATA_DIR = __DIR__ . '/data/';

    /** @var string */
    const MOCK_RAC_RESPONSE_FILE = 'rac-experiments-v2.json';

    /** @var string */
    const ASSIGNMENT_TEST_DATA_DIR = self::TEST_DATA_DIR . 'assignment-v2/';

    /** @var bool */
    private $filesAlreadyExist = false;

    /** @var StorageClient */
    private $storage;

    /** @var Bucket */
    private $bucket;

    public function __construct($bucketName)
    {
        $this->storage = new StorageClient(['suppressKeyFileNotice' => true]);
        $this->bucket = $this->storage->bucket($bucketName);

        if (!file_exists(self::TEST_DATA_DIR)) {
            mkdir(self::TEST_DATA_DIR, 0777, true);
            mkdir(self::ASSIGNMENT_TEST_DATA_DIR, 0777, true);
        } else {
            $this->filesAlreadyExist = true;
        }
    }

    /**
     * @return void
     */
    public function downloadTestFiles()
    {
        if ($this->filesAlreadyExist) {
            return;
        }

        $objects = $this->bucket->objects();

        foreach ($objects as $object) {
            $fileName = $object->name();
            $fileDestination = self::TEST_DATA_DIR . '/' . $fileName;
            $object->downloadToFile($fileDestination);
        }
    }

    /**
     * @return array
     */
    public function readAssignmentTestData(): array
    {
        $testCaseData = [];

        $files = scandir(self::ASSIGNMENT_TEST_DATA_DIR);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $testCase = json_decode(file_get_contents(self::ASSIGNMENT_TEST_DATA_DIR . $file), true);
            $testCaseData[] = $testCase;
        }

        return $testCaseData;
    }
}
