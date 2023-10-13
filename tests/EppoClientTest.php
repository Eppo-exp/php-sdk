<?php

namespace Eppo\Tests;

use Eppo\Config\SDKData;
use Eppo\ConfigurationStore;
use Eppo\EppoClient;
use Eppo\Exception\InvalidArgumentException;
use Eppo\ExperimentConfigurationRequester;
use Eppo\HttpClient;
use Eppo\Logger\LoggerInterface;
use Eppo\PollerInterface;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use PHPUnit\Framework\TestCase;
use Sarahman\SimpleCache\FileSystemCache;
use Throwable;

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
        'typedOverrides' => [],
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
                        'typedValue' => 'control',
                        'shardRange' => [
                            'start' => 0,
                            'end' => 34,
                        ],
                    ],
                    [
                        'name' => 'variant-1',
                        'value' => 'variant-1',
                        'typedValue' => 'variant-1',
                        'shardRange' => [
                            'start' => 34,
                            'end' => 67,
                        ],
                    ],
                    [
                        'name' => 'variant-2',
                        'value' => 'variant-2',
                        'typedValue' => 'variant-2',
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
        $client = EppoClient::getInstance();
        $assignmentsTestData = self::$testFilesHelper->readAssignmentTestData();

        foreach ($assignmentsTestData as $assignmentTestData) {
            $experiment = $assignmentTestData['experiment'];

            // Some test case have only subject keys, others have keys and attributes. Either way we'll, put them into a
            // single array with the subject keys to attributes (if any) 
            $subjectsWithAttributes = $assignmentTestData['subjectsWithAttributes'] ?? [];
            foreach ($assignmentTestData["subjects"] ?? [] as $subjectKey) {
                $subjectsWithAttributes[] = ["subjectKey" => $subjectKey, "subjectAttributes" => []];
            }

            // Use the hint from the test to determine what typed assignment function we should use
            $testValueType = $assignmentTestData["valueType"];

            // For each subject, retrieve the typed assignment
            $assignments = array_map(function ($subjectWithAttributes) use ($client, $testValueType, $experiment) {

                $subjectKey = $subjectWithAttributes["subjectKey"];
                $subjectAttributes = $subjectWithAttributes["subjectAttributes"];

                switch ($testValueType) {
                    case EppoClient::VARIANT_TYPE_STRING:
                        return $client->getStringAssignment($subjectKey, $experiment, $subjectAttributes);
                    case EppoClient::VARIANT_TYPE_NUMERIC:
                        return $client->getNumericAssignment($subjectKey, $experiment, $subjectAttributes);
                    case EppoClient::VARIANT_TYPE_BOOLEAN:
                        return $client->getBooleanAssignment($subjectKey, $experiment, $subjectAttributes);
                    case EppoClient::VARIANT_TYPE_JSON:
                        return $client->getJSONStringAssignment($subjectKey, $experiment, $subjectAttributes);
                    default:
                        throw new InvalidArgumentException("Unexpected test value type $testValueType");
                }
            }, $subjectsWithAttributes);

            $expectedAssignments = $assignmentTestData['expectedAssignments'];

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
                        'typedValue' => 'control',
                        'shardRange' => [
                            'start' => 0,
                            'end' => 50
                        ]
                    ],
                    [
                        'name' => 'treatment',
                        'value' => 'treatment',
                        'typedValue' => 'treatment',
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
            ->with('mock-experiment-allocation1', 'control', 'subject-10')
            ->willThrowException(new Exception('logger error'));
        $subjectAttributes = [['foo' => 3]];

        $client = EppoClient::createTestClient($mockConfigRequester, $pollerMock, $mockLogger);
        $assignment = $client->getAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);

        $this->assertEquals('control', $assignment);
    }

    public function testGracefulModeDoesNotThrow()
    {
        $pollerMock = $this->getPollerMock();
        $mockConfigRequester = $this->getExperimentConfigurationRequesterMock(self::MOCK_EXPERIMENT_CONFIG, new Exception('config requester error'));
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $subjectAttributes = [['foo' => 3]];
        $client = EppoClient::createTestClient($mockConfigRequester, $pollerMock, $mockLogger, true);

        $assignment = $client->getAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);
        $this->assertNull($assignment);
    }

    public function testNoGracefulModeThrows()
    {
        $pollerMock = $this->getPollerMock();
        $mockConfigRequester = $this->getExperimentConfigurationRequesterMock(self::MOCK_EXPERIMENT_CONFIG, new Exception('config requester error'));
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $subjectAttributes = [['foo' => 3]];
        $client = EppoClient::createTestClient($mockConfigRequester, $pollerMock, $mockLogger, false);

        $this->expectException(Exception::class);
        $client->getAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);
    }

    /**
     * @param array $mockedResponse
     * @return ExperimentConfigurationRequester
     */
    private function getExperimentConfigurationRequesterMock(array $mockedResponse, ?Throwable $mockedThrowable = null): ExperimentConfigurationRequester
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

        if ($mockedResponse) {
            $configStoreMock->expects($this->any())
                ->method('getConfiguration')
                ->with(self::EXPERIMENT_NAME)
                ->willReturn($mockedResponse);
        }

        if ($mockedThrowable) {
            $configStoreMock->expects($this->any())
                ->method('getConfiguration')
                ->with(self::EXPERIMENT_NAME)
                ->willThrowException($mockedThrowable);
        }

        return new ExperimentConfigurationRequester($httpClientMock, $configStoreMock);
    }

    private function getPollerMock()
    {
        return $this->getMockBuilder(PollerInterface::class)->getMock();
    }

    private function getLoggerMock()
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $mockLogger->expects($this->once())->method('logAssignment')->with(
            'mock-experiment-allocation1',
            'control',
            'subject-10',
            $this->greaterThan(0),
            $this->anything(),
            'allocation1',
            'mock-experiment'
        );

        return $mockLogger;
    }
}
