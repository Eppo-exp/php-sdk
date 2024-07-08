<?php

namespace Eppo\Tests;

use Eppo\APIRequestWrapper;
use Eppo\Config\ConfigurationLoader;
use Eppo\Config\ConfigurationStore;
use Eppo\Config\SDKData;
use Eppo\DTO\VariationType;
use Eppo\EppoClient;
use Eppo\Logger\LoggerInterface;
use Eppo\PollerInterface;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Http\Discovery\Psr18Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use PsrMock\Psr17\RequestFactory;
use Sarahman\SimpleCache\FileSystemCache;
use Throwable;

class BanditClientTest extends TestCase
{
    private const EXPERIMENT_NAME = 'numeric_flag';
    private const TEST_DATA_PATH = __DIR__ . '/data/ufc/tests';

    public static function setUpBeforeClass(): void
    {
        try {
            MockWebServer::start();
        } catch (Exception $exception) {
            self::fail('Failed to start mocked web server: ' . $exception->getMessage());
        }

        try {
            EppoClient::init('dummy', 'http://localhost:4000', isGracefulMode: false);
        } catch (Exception $exception) {
            self::fail('Failed to initialize EppoClient: ' . $exception->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        MockWebServer::stop();
    }

    public function skiptestAcceptsGroupedSubjectAttributes(): void
    {
        $configLoaderMock = $this->getConfigurationLoaderMock([]);
        $pollerMock = $this->getPollerMock();

        $client = EppoClient::createTestClient($configLoaderMock, $pollerMock);

        $flagKey = "my-bandit-flag";
        $subject = "user-123";
        $subjectContext = [
            "categoricalAttributes" => [
                "country" => "US",
                "areaCode" => 905
            ],
            "numericAttributes" => [
                "accountAge" => 0.5
            ]
        ];

        // A simple list of actions with no context attributes
        $actions = ["nike", "adidas", "reebok"];

        $result = $client->getBanditAction($flagKey, $subject, $subjectContext, $actions, "control");

        $this->assertEquals($result->variation, "control");
    }


    public function skiptestAcceptsUngroupedSubjectAttributes(): void
    {
        $configLoaderMock = $this->getConfigurationLoaderMock([]);
        $pollerMock = $this->getPollerMock();

        $client = EppoClient::createTestClient($configLoaderMock, $pollerMock);

        $flagKey = "my-bandit-flag";
        $subject = "user-123";
        $subjectContext = ["accountAge" => 0.5, "country" => "US", "areaCode" => 905];

        // A simple list of actions with no context attributes
        $actions = ["nike", "adidas", "reebok"];

        $result = $client->getBanditAction($flagKey, $subject, $subjectContext, $actions, "control");

        $this->assertEquals($result->variation, "control");
    }

    public function skiptestEvaluatesListOfActionsNoContext(): void
    {
        $configLoaderMock = $this->getConfigurationLoaderMock([]);
        $pollerMock = $this->getPollerMock();

        $client = EppoClient::createTestClient($configLoaderMock, $pollerMock);

        $flagKey = "my-bandit-flag";
        $subject = "user-123";
        $subjectContext = ["accountAge" => 0.5, "country" => "US"];

        // A simple list of actions with no context attributes
        $actions = ["nike", "adidas", "reebok"];

        $result = $client->getBanditAction($flagKey, $subject, $subjectContext, $actions, "control");

        $this->assertEquals($result->variation, "control");
    }

    public function skiptestEvaluatesListOfActionContexts(): void
    {
        $configLoaderMock = $this->getConfigurationLoaderMock([]);
        $pollerMock = $this->getPollerMock();

        $client = EppoClient::createTestClient($configLoaderMock, $pollerMock);

        $flagKey = "my-bandit-flag";
        $subject = "user-123";
        $subjectContext = ["accountAge" => 0.5, "country" => "US"];

        // Actions with ungrouped attributes
        $actions = [
            "nike" => [
                "brandAffinity" => 0.5,
                "loyaltyTier" => "silver"
            ],
            "adidas" => [
                "brandAffinity" => 0.5,
                "loyaltyTier" => "gold"
            ],
            "reebok" => [
                "brandAffinity" => 0.5,
                "loyaltyTier" => "bronze"
            ]
        ];

        $result = $client->getBanditAction($flagKey, $subject, $subjectContext, $actions, "control");

        $this->assertEquals($result->variation, "control");
    }

    public function skiptestEvaluatesListOfGroupedActionContexts(): void
    {
        $configLoaderMock = $this->getConfigurationLoaderMock([]);
        $pollerMock = $this->getPollerMock();

        $client = EppoClient::createTestClient($configLoaderMock, $pollerMock);

        $flagKey = "my-bandit-flag";
        $subject = "user-123";
        $subjectContext = ["accountAge" => 0.5, "country" => "US"];

        // Actions with grouped attributes
        $actions = [
            "nike" => [
                "brandAffinity" => 0.5,
                "loyaltyTier" => "silver"
            ],
            "adidas" => [
                "numericalAttributes" => [
                    "brandAffinity" => 0.5
                ],
                "categoricalAttributes" => [
                    "loyaltyTier" => "gold",
                    "packageQuantity" => 2
                ]
            ],
            "reebok" => [
                "brandAffinity" => 0.5,
                "loyaltyTier" => "bronze"
            ]
        ];

        $result = $client->getBanditAction($flagKey, $subject, $subjectContext, $actions, "control");

        $this->assertEquals($result->variation, "control");
    }


    /**
     * @throws GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws ClientExceptionInterface
     */
    public function skiptestRepoTestCases(): void
    {
        // Load all the test cases.
        $testCases = $this->loadTestCases();
        $client = EppoClient::getInstance();

        foreach ($testCases as $testFile => $test) {
            foreach ($test['subjects'] as $subject) {
                $result = $this->getTypedAssignment(
                    $client,
                    VariationType::from($test['variationType']),
                    $test['flag'],
                    $subject['subjectKey'],
                    $subject['subjectAttributes'],
                    $test['defaultValue']
                );
                $this->assertEquals($subject['assignment'], $result, "$testFile ${test['flag']}");
            }
        }
    }

    /**
     * @throws GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws Exception|ClientExceptionInterface
     */
    private function getTypedAssignment(
        EppoClient $client,
        VariationType $type,
        string $flag,
        string $subjectKey,
        array $subject,
        array|bool|float|int|string $defaultValue
    ): array|bool|float|int|string|null {
        return match ($type) {
            VariationType::STRING => $client->getStringAssignment($flag, $subjectKey, $subject, $defaultValue),
            VariationType::BOOLEAN => $client->getBooleanAssignment($flag, $subjectKey, $subject, $defaultValue),
            VariationType::NUMERIC => $client->getNumericAssignment($flag, $subjectKey, $subject, $defaultValue),
            VariationType::JSON => $client->getJSONAssignment($flag, $subjectKey, $subject, $defaultValue),
            VariationType::INTEGER => $client->getIntegerAssignment($flag, $subjectKey, $subject, $defaultValue),
            default => throw new \Exception('Unexpected match value'),
        };
    }

    private function getConfigurationLoaderMock(
        array $mockedResponse,
        ?Throwable $mockedThrowable = null
    ): ConfigurationLoader {
        $cache = new FileSystemCache();
        $sdkData = new SDKData();

        $sdkParams = [
            "sdkVersion" => $sdkData->getSdkVersion(),
            "sdkName" => $sdkData->getSdkName()
        ];


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
                ->method('getFlag')
                ->with(self::EXPERIMENT_NAME)
                ->willReturn($mockedResponse);
        }

        if ($mockedThrowable) {
            $configStoreMock->expects($this->any())
                ->method('getFlag')
                ->with(self::EXPERIMENT_NAME)
                ->willThrowException($mockedThrowable);
        }

        return new ConfigurationLoader($apiRequestWrapper, $configStoreMock);
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

    private function loadTestCases(): array
    {
        $files = scandir(self::TEST_DATA_PATH);
        $tests = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $tests[$file] = json_decode(file_get_contents(self::TEST_DATA_PATH . '/' . $file), true);
        }
        return $tests;
    }
}
