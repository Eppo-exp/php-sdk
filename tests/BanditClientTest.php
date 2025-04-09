<?php

namespace Eppo\Tests;

use Eppo\Cache\DefaultCacheFactory;
use Eppo\Config\ConfigurationLoader;
use Eppo\DTO\Allocation;
use Eppo\DTO\Bandit\AttributeSet;
use Eppo\DTO\Bandit\BanditResult;
use Eppo\DTO\Flag;
use Eppo\DTO\Shard;
use Eppo\DTO\ShardRange;
use Eppo\DTO\Split;
use Eppo\DTO\Variation;
use Eppo\DTO\VariationType;
use Eppo\EppoClient;
use Eppo\Exception\EppoClientException;
use Eppo\Exception\EppoException;
use Eppo\Logger\BanditActionEvent;
use Eppo\Logger\IBanditLogger;
use Eppo\PollerInterface;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use PHPUnit\Framework\TestCase;

class BanditClientTest extends TestCase
{
    private const TEST_DATA_PATH = __DIR__ . '/data/ufc/bandit-tests';

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
        $flagKey = 'bandit';
        $actions = ['foo', 'bar', 'baz'];
        $subjectKey = 'user123';
        $subject = ['country' => 'USA', 'age' => 25];
        $default = 'defaultVariation';


        $config = $this->getMockBuilder(ConfigurationLoader::class)->disableOriginalConstructor()->getMock();

        $config->expects($this->once())
            ->method('getBanditByVariation')
            ->with($flagKey, $default)
            ->willReturn('DNEBanditKey');

        $client = EppoClient::createTestClient($config, poller: $this->getPollerMock());

        $this->expectException(EppoClientException::class);
        $this->expectExceptionCode(EppoException::BANDIT_EVALUATION_FAILED_BANDIT_MODEL_NOT_PRESENT);

        $client->getBanditAction($flagKey, $subjectKey, $subject, $actions, $default);
    }

    public function testBanditModelDoesNotExistGracefulNoThrows(): void
    {
        $flagKey = 'bandit';
        $actions = ['foo', 'bar', 'baz'];
        $subjectKey = 'user123';
        $subject = ['country' => 'USA', 'age' => 25];
        $default = 'defaultVariation';
        $banditKeyVariation = 'banditKey';

        $config = $this->getMockBuilder(ConfigurationLoader::class)->disableOriginalConstructor()->getMock();

        $config->expects($this->once())
            ->method('getBanditByVariation')
            ->with($flagKey, $default)
            ->willReturn($banditKeyVariation);

        // This is what we're actually testing here.
        $config->expects($this->once())
            ->method('getBandit')
            ->with($banditKeyVariation)
            ->willReturn(null);

        $client = EppoClient::createTestClient($config, poller: $this->getPollerMock(), isGracefulMode: true);


        $result = $client->getBanditAction($flagKey, $subjectKey, $subject, $actions, $default);
        $this->assertEquals($default, $result->variation);
        $this->assertNull($result->action);
    }

    public function testBanditSelectionLogged(): void
    {
        $flagKey = 'banner_bandit_flag';
        $actions = ['nike', 'adidas', 'reebok'];
        $subjectKey = 'alice';
        $subject = ['country' => 'USA', 'age' => 25];
        $default = 'default';
        $banditKey = 'banner_bandit';

        $expectedResult = new BanditResult('banner_bandit', 'nike');

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
                    "Test failure for {$subject['subjectKey']} in $testFile"
                );
                $this->assertEquals(
                    $subject['assignment']['action'],
                    $result->action,
                    "Test failure {$subject['subjectKey']} in $testFile"
                );
            }
        }
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

    private function getPollerMock()
    {
        return $this->getMockBuilder(PollerInterface::class)->getMock();
    }

    private function makeFlagThatMapsAllTo(string $flagKey, string $variationValue)
    {
        $totalShards = 10_000;
        $allocations = [
            new Allocation(
                'defaultAllocation',
                [],
                [new Split($variationValue, [new Shard("na", [new ShardRange(0, $totalShards)])], [])],
                false
            )
        ];
        $variations = [
            $variationValue => new Variation($variationValue, $variationValue)
        ];
        return new Flag($flagKey, true, $allocations, VariationType::STRING, $variations, $totalShards);
    }
}
