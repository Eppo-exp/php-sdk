<?php

namespace Eppo\Tests;

use Eppo\APIRequestWrapper;
use Eppo\Config\SDKData;
use Eppo\ConfigurationStore;
use Eppo\DTO\VariationType;
use Eppo\EppoClient;
use Eppo\Exception\HttpRequestException;
use Eppo\Exception\InvalidApiKeyException;
use Eppo\Exception\InvalidArgumentException;
use Eppo\ExperimentConfigurationRequester;
use Eppo\FlagConfigurationLoader;
use Eppo\Logger\LoggerInterface;
use Eppo\PollerInterface;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Http\Discovery\Psr18Client;
use Http\Mock\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use PsrMock\Psr17\RequestFactory;
use Sarahman\SimpleCache\FileSystemCache;
use Throwable;

class EppoClientTest extends TestCase
{

    const EXPERIMENT_NAME = 'numeric_flag';
    const TEST_DATA_PATH = __DIR__ . '/data/ufc/tests';

    public static function setUpBeforeClass(): void
    {
        try {
            MockWebServer::start();
        } catch (Exception $exception) {
            self::fail('Failed to start mocked web server: ' . $exception->getMessage());
        }

        try {
            EppoClient::init('dummy', 'http://localhost:4000',isGracefulMode: false);
        } catch (Exception $exception) {
            self::fail('Failed to initialize EppoClient: ' . $exception->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        MockWebServer::stop();
    }

    /*
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
    */
    public function testGracefulModeDoesNotThrow()
    {
        $pollerMock = $this->getPollerMock();
        $mockConfigRequester = $this->getFlagConfigurationLoaderMock([], new Exception('config loader error'));
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $subjectAttributes = [['foo' => 3]];
        $client = EppoClient::createTestClient($mockConfigRequester, $pollerMock, $mockLogger);

        $this->assertNull($client->getAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes));
        $this->assertNull($client->getStringAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes));
        $this->assertNull($client->getNumericAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes));
        $this->assertNull($client->getBooleanAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes));
        $this->assertNull($client->getJSONAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes));
    }


    /**
     * @throws InvalidApiKeyException
     * @throws GuzzleException
     * @throws HttpRequestException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function testNoGracefulModeThrows()
    {
        $pollerMock = $this->getPollerMock();
        $mockConfigRequester = $this->getFlagConfigurationLoaderMock([], new Exception('config loader error'));
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $subjectAttributes = [['foo' => 3]];
        $client = EppoClient::createTestClient($mockConfigRequester, $pollerMock, $mockLogger, false);

        $this->expectException(Exception::class);
        $client->getAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);
        $client->getStringAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);
        $client->getNumericAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);
        $client->getBooleanAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);
        $client->getJSONAssignment('subject-10', self::EXPERIMENT_NAME, $subjectAttributes);
    }


    /**
     * @throws GuzzleException
     * @throws HttpRequestException
     * @throws InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws InvalidApiKeyException
     */
    public function testRepoTestCases(): void
    {
        // Load all the test cases.
        $testCases = $this->loadTestCases();
        $client = EppoClient::getInstance();

        foreach ($testCases as $testFile => $test) {
            foreach ($test['subjects'] as $subject) {
                $result = $this->getTypedAssignment($client, VariationType::from($test['variationType']),
                    $test['flag'], $subject['subjectKey'], $subject['subjectAttributes'], $test['defaultValue']);
                $this->assertEquals($subject['assignment'], $result, "$testFile ${test['flag']}");
            }
        }
    }

    /**
     * @throws GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws Exception|\Psr\Http\Client\ClientExceptionInterface
     */
    private function getTypedAssignment(EppoClient $client, VariationType $type, string $flag, string $subjectKey, array $subject,
    array|bool|float|int|string $defaultValue): array|bool|float|int|string|null
    {
        return match ($type) {
            VariationType::STRING => $client->getStringAssignment($subjectKey, $flag, $subject, $defaultValue),
            VariationType::BOOLEAN => $client->getBooleanAssignment($subjectKey, $flag, $subject, $defaultValue),
            VariationType::NUMERIC => $client->getNumericAssignment($subjectKey, $flag, $subject, $defaultValue),
            VariationType::JSON => $client->getJSONAssignment($subjectKey, $flag, $subject, $defaultValue),
            VariationType::INTEGER =>  $client->getIntegerAssignment($subjectKey, $flag, $subject, $defaultValue)
        };
    }

    private function getFlagConfigurationLoaderMock(array $mockedResponse, ?Throwable $mockedThrowable = null): FlagConfigurationLoader
    {
        $cache = new FileSystemCache();
        $sdkData = new SDKData();

        $sdkParams = ["sdkVersion" => $sdkData->getSdkVersion(),
            "sdkName" => $sdkData->getSdkName()];


        $apiRequestWrapper = $this->getMockBuilder(APIRequestWrapper::class)->setConstructorArgs([
            '',
            $sdkParams,
            new Psr18Client(),
            new RequestFactory()
        ])->getMock();
        $apiRequestWrapper->expects($this->any())
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

        return new FlagConfigurationLoader($apiRequestWrapper, $configStoreMock);
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

    private function loadTestCases() : array
    {
        $files = scandir(self::TEST_DATA_PATH);
        $tests = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $tests[$file] = json_decode(file_get_contents(self::TEST_DATA_PATH . '/' . $file), true);
        }
        return $tests;
    }
}
