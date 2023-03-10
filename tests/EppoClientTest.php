<?php

namespace Eppo\Tests;

use Eppo\Config\SDKData;
use Eppo\ConfigurationStore;
use Eppo\EppoClient;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\ExperimentConfigurationRequester;
use Eppo\HttpClient;
use Eppo\Logger\LoggerInterface;
use Eppo\PollerInterface;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;
use Sarahman\SimpleCache\FileSystemCache;

class EppoClientTest extends TestCase
{
    /** @var string */
    const EXPERIMENT_NAME = 'mock-experiment';

    /** @var array */
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

    /** @var TestFilesHelper */
    private static $testFilesHelper;

    public static function setUpBeforeClass(): void
    {
        self::$testFilesHelper = new TestFilesHelper('sdk-test-data');
        self::$testFilesHelper->downloadTestFiles();

        try {
            MockWebServer::start();
        } catch (Exception $exception) {
            self::fail('Failed to start mocked web server: ' . $exception->getMessage());
        }

        try {
            EppoClient::init('dummy', 'http://localhost:4000');
        } catch (Exception $exception) {
            self::fail('Failed to initialize EppoClient: ' . $exception->getMessage());
        }
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
            } catch (Exception|GuzzleException|\Psr\SimpleCache\InvalidArgumentException $exception) {
                $this->fail('Test failed: ' . $exception->getMessage());
            }

            $this->assertEquals($expectedAssignments, $assignments);
        }
    }

    public function testAssignsSubjectFromOverridesWhenExperimentIsEnabled()
    {
        $mockedResponse = self::MOCK_EXPERIMENT_CONFIG;
        $mockedResponse['overrides'] = ['1b50f33aef8f681a13f623963da967ed' => 'variant-2'];

        $experimentConfigRequesterMock = $this->getExperimentConfigurationRequesterMock($mockedResponse);
        $pollerMock = $this->getPollerMock();

        $client = EppoClient::createTestClient($experimentConfigRequesterMock, $pollerMock);
        $assignment = $client->getAssignment('subject-10', self::EXPERIMENT_NAME);

        $this->assertEquals('variant-2', $assignment);
    }

    public function testAssignsSubjectFromOverridesWhenExperimentIsNotEnabled()
    {
        $mockedResponse = self::MOCK_EXPERIMENT_CONFIG;
        $mockedResponse['overrides'] = ['1b50f33aef8f681a13f623963da967ed' => 'variant-2'];

        $experimentConfigRequesterMock = $this->getExperimentConfigurationRequesterMock($mockedResponse);
        $pollerMock = $this->getPollerMock();

        $client = EppoClient::createTestClient($experimentConfigRequesterMock, $pollerMock);
        $assignment = $client->getAssignment('subject-10', self::EXPERIMENT_NAME);
        $this->assertEquals('variant-2', $assignment);
    }

    public function testReturnsNullWhenExperimentConfigIsAbsent()
    {
        $experimentConfigRequesterMock = $this->getExperimentConfigurationRequesterMock([]);
        $pollerMock = $this->getPollerMock();

        $client = EppoClient::createTestClient($experimentConfigRequesterMock, $pollerMock);
        $assignment = $client->getAssignment('subject-10', self::EXPERIMENT_NAME);
        $this->assertNull($assignment);
    }

    public function testOnlyReturnsVariationIfSubjectMatchesRules()
    {
        $mockedResponse = self::MOCK_EXPERIMENT_CONFIG;
        $mockedResponse['rules'] = [
            [
                'allocationKey' => 'allocation1',
                'conditions' => [
                    [
                        'operator' => 'GT',
                        'attribute' => 'appVersion',
                        'value' => 10
                    ]
                ]
            ]
        ];
        $mockedResponse['allocations'] = [
            'allocation1' => [
                'percentExposure' => 1,
                'variations' => [
                    [
                        'name' => 'control',
                        'value' => 'control',
                        'shardRange' => [
                            'start' => 0,
                            'end' => 50
                        ]
                    ],
                    [
                        'name' => 'treatment',
                        'value' => 'treatment',
                        'shardRange' => [
                            'start' => 50,
                            'end' => 100
                        ]
                    ]
                ]
            ]
        ];

        $experimentConfigRequesterMock = $this->getExperimentConfigurationRequesterMock($mockedResponse);
        $pollerMock = $this->getPollerMock();
        $client = EppoClient::createTestClient($experimentConfigRequesterMock, $pollerMock);

        $this->assertNull(
            $client->getAssignment('subject-10', self::EXPERIMENT_NAME, ['appVersion' => 9])
        );
        $this->assertNull(
            $client->getAssignment('subject-10', self::EXPERIMENT_NAME)
        );
        $this->assertEquals(
            $client->getAssignment('subject-10', self::EXPERIMENT_NAME, ['appVersion' => 11]),
            'control'
        );
    }

    public function testLogsVariationAssignment()
    {
        $pollerMock = $this->getPollerMock();
        $mockConfigRequester = $this->getExperimentConfigurationRequesterMock(self::MOCK_EXPERIMENT_CONFIG);
        $mockLogger = $this->getLoggerMock();

        $subjectAttributes = [['foo' => 3]];

        $client = EppoClient::createTestClient($mockConfigRequester, $pollerMock, $mockLogger);
        $assignment = $client->getAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);

        $this->assertEquals('control', $assignment);
    }

    public function testHandlesLoggingException()
    {
        $pollerMock = $this->getPollerMock();
        $mockConfigRequester = $this->getExperimentConfigurationRequesterMock(self::MOCK_EXPERIMENT_CONFIG);
        $mockLogger = $this->getLoggerMock();
        $mockLogger->expects($this->once())
            ->method('logAssignment')
            ->with('mock-experiment', 'control', 'subject-10')
            ->willThrowException(new Exception('logger error'));
        $subjectAttributes = [['foo' => 3]];

        $client = EppoClient::createTestClient($mockConfigRequester, $pollerMock, $mockLogger);
        $assignment = $client->getAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);

        $this->assertEquals('control', $assignment);
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

    /**
     * @param array $mockedResponse
     * @return ExperimentConfigurationRequester
     */
    private function getExperimentConfigurationRequesterMock(array $mockedResponse): ExperimentConfigurationRequester
    {
        $cache = new FileSystemCache();
        $sdkData = new SDKData();

        $httpClientMock = $this->getMockBuilder(HttpClient::class)->setConstructorArgs([
            '',
            'dummy',
            $sdkData
        ])->getMock();
        $httpClientMock->expects($this->any())
            ->method('get')
            ->willReturn('');

        $configStoreMock = $this->getMockBuilder(ConfigurationStore::class)->setConstructorArgs([$cache])->getMock();
        $configStoreMock->expects($this->any())
            ->method('getConfiguration')
            ->with(self::EXPERIMENT_NAME)
            ->willReturn($mockedResponse);

        return new ExperimentConfigurationRequester($httpClientMock, $configStoreMock);
    }

    private function getPollerMock()
    {
        return $this->getMockBuilder(PollerInterface::class)->getMock();
    }

    private function getLoggerMock()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $mockLogger->expects($this->once())->method('logAssignment')->with('mock-experiment', 'control', 'subject-10');

        return $mockLogger;
    }
}
