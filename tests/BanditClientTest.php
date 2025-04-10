<?php

namespace Eppo\Tests;

use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\Configuration;
use Eppo\Config\ConfigurationLoader;
use Eppo\Config\ConfigurationStore;
use Eppo\DTO\Bandit\AttributeSet;
use Eppo\DTO\Bandit\BanditResult;
use Eppo\DTO\ConfigurationWire\ConfigResponse;
use Eppo\DTO\ConfigurationWire\ConfigurationWire;
use Eppo\EppoClient;
use Eppo\Exception\EppoClientException;
use Eppo\Exception\EppoException;
use Eppo\Logger\BanditActionEvent;
use Eppo\Logger\IBanditLogger;
use Eppo\PollerInterface;
use Eppo\Tests\Config\MockCache;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BanditClientTest extends TestCase
{
    private const TEST_DATA_PATH = __DIR__ . '/data/ufc/bandit-tests';
    private const CONFIG_DATA_PATH = __DIR__ . '/data/configuration-wire/';
    private const DEFAULT_FLAG_KEY = 'banner_bandit_flag';
    private const DEFAULT_SUBJECT_KEY = 'Alice';
    private const DEFAULT_SUBJECT_ATTRIBUTES = ['country' => 'USA', 'age' => 25];
    private const DEFAULT_ACTIONS = ['nike', 'adidas', 'reebok'];
    private const DEFAULT_VALUE = 'default';
    private const BANDIT_KEY = 'banner_bandit';

    private static ?EppoClient $client;
    private static MockWebServer $mockServer;

    public static function setUpBeforeClass(): void
    {
        try {
            self::$mockServer = MockWebServer::start(__DIR__ . '/data/ufc/bandit-flags-v1.json');
        } catch (Exception $exception) {
            self::fail('Failed to start mocked web server: ' . $exception->getMessage());
        }

        try {
            self::$client = EppoClient::init('dummy', self::$mockServer->serverAddress);
        } catch (Exception $exception) {
            self::fail('Failed to initialize EppoClient: ' . $exception->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$mockServer->stop();
        DefaultCacheFactory::clearCache();
    }

    public function testBanditModelDoesNotExist(): void
    {
        // Remove the bandit model from the configuration wire
        $configurationWire = $this->getBanditConfigurationWire();
        $bandits = json_decode($configurationWire->bandits->response, true)['bandits'];
        unset($bandits[self::BANDIT_KEY]);

        $client = $this->createTestClientWithModifiedBandits($bandits, false);

        $this->expectException(EppoClientException::class);
        $this->expectExceptionCode(EppoException::BANDIT_EVALUATION_FAILED_BANDIT_MODEL_NOT_PRESENT);

        $result = $client->getBanditAction(
            self::DEFAULT_FLAG_KEY,
            self::DEFAULT_SUBJECT_KEY,
            self::DEFAULT_SUBJECT_ATTRIBUTES,
            self::DEFAULT_ACTIONS,
            self::DEFAULT_VALUE
        );

        $this->assertEquals(self::BANDIT_KEY, $result->variation);
        $this->assertNull($result->action);
    }


    public function testBanditModelDoesNotExistGracefulNoThrows(): void
    {
        // Remove the bandit model from the configuration wire
        $configurationWire = $this->getBanditConfigurationWire();
        $bandits = json_decode($configurationWire->bandits->response, true)['bandits'];
        unset($bandits[self::BANDIT_KEY]);

        $client = $this->createTestClientWithModifiedBandits($bandits, isGracefulMode: true);

        $result = $client->getBanditAction(
            self::DEFAULT_FLAG_KEY,
            self::DEFAULT_SUBJECT_KEY,
            self::DEFAULT_SUBJECT_ATTRIBUTES,
            self::DEFAULT_ACTIONS,
            self::DEFAULT_VALUE
        );

        $this->assertEquals(self::BANDIT_KEY, $result->variation);
        $this->assertNull($result->action);
    }

    public function testBanditSelectionLogged(): void
    {
        $flagKey = self::DEFAULT_FLAG_KEY;
        $actions = self::DEFAULT_ACTIONS;
        $subjectKey = strtolower(self::DEFAULT_SUBJECT_KEY);
        $subject = self::DEFAULT_SUBJECT_ATTRIBUTES;
        $default = self::DEFAULT_VALUE;
        $banditKey = self::BANDIT_KEY;

        $expectedResult = new BanditResult(self::BANDIT_KEY, 'nike');

        $mockLogger = $this->getMockBuilder(IBanditLogger::class)->getMock();

        $mockLogger->expects($this->once())->method('logAssignment');

        $mockLogger->expects($this->once())->method('logBanditAction')
            ->with(
                $this->callback(function (BanditActionEvent $bee) use ($flagKey, $subjectKey, $banditKey) {
                    return $bee->banditKey == $banditKey &&
                        $bee->subjectKey == $subjectKey &&
                        $bee->flagKey == $flagKey &&
                        $bee->action == 'nike';
                })
            );

        $client = EppoClient::init(
            'dummy',
            self::$mockServer->serverAddress,
            assignmentLogger: $mockLogger,
        );

        $result = $client->getBanditAction($flagKey, $subjectKey, $subject, $actions, $default);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test all bandit test cases from the repository
     */
    public function testRepoTestCases(): void
    {
        // Load all the test cases.
        $testCases = $this->loadTestCases();
        $client = self::$client;

        $this->assertNotEmpty($testCases);
        foreach ($testCases as $testFile => $test) {
            $this->assertNotEmpty($test['subjects']);

            foreach ($test['subjects'] as $subject) {
                $actions = [];
                foreach ($subject['actions'] as $action) {
                    $key = $action['actionKey'];
                    $actions[$key] = new AttributeSet($action['numericAttributes'], $action['categoricalAttributes']);
                }

                $subjectAttributes = new AttributeSet(
                    $subject['subjectAttributes']['numericAttributes'],
                    $subject['subjectAttributes']['categoricalAttributes']
                );

                $result = $client->getBanditAction(
                    $test['flag'],
                    $subject['subjectKey'],
                    $subjectAttributes,
                    $actions,
                    $test['defaultValue']
                );

                $this->assertEquals(
                    $subject['assignment']['variation'],
                    $result->variation,
                    'Test failure for ' . $subject['subjectKey'] . ' in ' . $testFile
                );
                $this->assertEquals(
                    $subject['assignment']['action'],
                    $result->action,
                    'Test failure ' . $subject['subjectKey'] . ' in ' . $testFile
                );
            }
        }
    }


    /**
     * Create a test client with a modified bandit configuration
     *
     * @param array $bandits The modified bandits configuration to use
     * @param bool $isGracefulMode Whether to run in graceful mode
     * @return EppoClient The configured test client
     */
    private function createTestClientWithModifiedBandits(array $bandits, bool $isGracefulMode = false): EppoClient
    {
        $configurationWire = $this->getBanditConfigurationWire();

        $newConfig = ConfigurationWire::fromResponses(
            flags: $configurationWire->config,
            bandits: new ConfigResponse(
                response: json_encode(['bandits' => $bandits])
            )
        );

        $configuration = Configuration::fromConfigurationWire($newConfig);

        $mockCache = new MockCache();
        $configStore = new ConfigurationStore($mockCache);

        $configStore->setConfiguration($configuration);

        $configLoader = $this->getMockBuilder(ConfigurationLoader::class)->disableOriginalConstructor()->getMock();

        return EppoClient::createTestClient(
            $configStore,
            $configLoader,
            poller: $this->getPollerMock(),
            isGracefulMode: $isGracefulMode
        );
    }

    private function getPollerMock(): MockObject
    {
        return $this->getMockBuilder(PollerInterface::class)->getMock();
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

    private function getBanditConfigurationWire(): ConfigurationWire
    {
        $jsonData = file_get_contents(self::CONFIG_DATA_PATH . 'bandit-flags-v1.json');
        $this->assertNotFalse($jsonData, 'Failed to load test data file');

        $configData = json_decode($jsonData, true);
        $this->assertIsArray($configData, 'Failed to parse JSON data');

        return ConfigurationWire::create($configData);
    }
}
