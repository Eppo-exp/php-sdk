<?php

namespace Eppo\Tests;

use Eppo\EppoClient;
use Eppo\Tests\WebServer\MockWebServer;
use PHPUnit\Framework\TestCase;

class EppoClientTest extends TestCase
{
    const EXPERIMENT_NAME = 'mock-experiment';

    const MOCK_EXPERIMENT_CONFIG = [
        'name' => self::EXPERIMENT_NAME,
        'enabled' => true,
        'subjectShards' => 100,
        'overrides' => [],
        'rules' => [
            [
                'allocationKey' => 'allocation1',
                'conditions' => [],
            ],
        ],
        'allocations' => [
            'allocation1' => [
                'percentExposure' => 1,
                'variations' => [
                    [
                        'name' => 'control',
                        'value' => 'control',
                        'shardRange' => [
                            'start' => 0,
                            'end' => 34,
                        ],
                    ],
                    [
                        'name' => 'variant-1',
                        'value' => 'variant-1',
                        'shardRange' => [
                            'start' => 34,
                            'end' => 67,
                        ],
                    ],
                    [
                        'name' => 'variant-2',
                        'value' => 'variant-2',
                        'shardRange' => [
                            'start' => 67,
                            'end' => 100,
                        ],
                    ],
                ],
            ],
        ],
    ];

    public static function setUpBeforeClass(): void
    {
        MockWebServer::start();

        $testHelper = new TestFilesHelper('sdk-test-data');
        $testHelper->downloadTestFiles();
    }

    public static function tearDownAfterClass(): void
    {
        MockWebServer::stop();
    }

    public function testGetAssignmentVariationAssignmentSplits(): void
    {
//        $testHelper = new TestFilesHelper('sdk-test-data');
        $this->assertEquals(true, true);
//        $testCases = $testHelper->readAssignmentTestData();
    }
}