<?php

namespace Eppo\Tests;

use Eppo\EppoClient;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
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

    private static $testFilesHelper;

    public static function setUpBeforeClass(): void
    {
        self::$testFilesHelper = new TestFilesHelper('sdk-test-data');
        self::$testFilesHelper->downloadTestFiles();

        MockWebServer::start();
        EppoClient::init('dummy', 'http://localhost:4000');
    }

    public static function tearDownAfterClass(): void
    {
        MockWebServer::stop();
    }

    public function testGetAssignmentVariationAssignmentSplits(): void
    {
        $assignmentsTestData = self::$testFilesHelper->readAssignmentTestData();

        foreach ($assignmentsTestData as $assignmentTestData) {
            $experiment = $assignmentTestData['experiment'];
            $subjects = array_key_exists('subjects', $assignmentTestData)
                ? $assignmentTestData['subjects']
                : null;
            $subjectsWithAttributes = array_key_exists('subjectsWithAttributes', $assignmentTestData)
                ? $assignmentTestData['subjectsWithAttributes']
                : null;

            $expectedAssignments = $assignmentTestData['expectedAssignments'];

            try {
                $assignments = !!$subjectsWithAttributes
                    ? $this->getAssignmentsWithSubjectAttributes($subjectsWithAttributes, $experiment)
                    : $this->getAssignments($subjects, $experiment);
            } catch (Exception $exception) {
                $this->fail('Test failed');
            }

            $this->assertEquals($expectedAssignments, $assignments);
        }
    }

    /**
     * @param array $subjects
     * @param string $experiment
     * @return array
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function getAssignments(array $subjects, string $experiment): array
    {
        $client = EppoClient::getInstance();
        $assignments = [];
        foreach ($subjects as $subjectKey) {
            $assignments[] = $client->getAssignment($subjectKey, $experiment);
        }

        return $assignments;
    }

    /**
     * @param array $subjectsWithAttributes
     * @param string $experiment
     * @return array
     * @throws GuzzleException
     * @throws HttpRequestException
     * @throws InvalidApiKeyException
     * @throws InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function getAssignmentsWithSubjectAttributes(array $subjectsWithAttributes, string $experiment): array
    {
        $client = EppoClient::getInstance();
        $assignments = [];
        foreach ($subjectsWithAttributes as $subject) {
            $assignment = $client->getAssignment($subject['subjectKey'], $experiment, $subject['subjectAttributes']);
            $assignments[] = $assignment;
        }
        return $assignments;
    }


}