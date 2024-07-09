<?php

namespace Eppo\Tests;

use Eppo\Config\ConfigurationLoader;
use Eppo\DTO\Bandit\AttributeSet;
use Eppo\EppoClient;
use Eppo\Exception\BanditEvaluationException;
use Eppo\Exception\EppoClientException;
use Eppo\Exception\EppoException;
use Eppo\Tests\WebServer\MockWebServer;
use Exception;
use PHPUnit\Framework\TestCase;

class BanditClientTest extends TestCase
{
    private const EXPERIMENT_NAME = 'numeric_flag';
    private const TEST_DATA_PATH = __DIR__ . '/data/ufc/bandit-tests';

    public static function setUpBeforeClass(): void
    {
        try {
            MockWebServer::start(__DIR__ . '/data/ufc/bandit-flags-v1.json');
        } catch (Exception $exception) {
            self::fail('Failed to start mocked web server: ' . $exception->getMessage());
        }

        try {
            EppoClient::init('dummy', 'http://localhost:4000',);
        } catch (Exception $exception) {
            self::fail('Failed to initialize EppoClient: ' . $exception->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        MockWebServer::stop();
    }

    public function testBanditWithEmptyActions(): void
    {
        $banditKey = 'bandit';
        $actions = [];
        $subjectKey = 'user123';
        $subject = ['country'=>'USA','age'=>25];
        $default = 'defaultVariation';


        $config = $this->getMockBuilder(ConfigurationLoader::class)->disableOriginalConstructor()->getMock();

        $config->expects($this->once())
            ->method('isBanditFlag')
            ->with($banditKey)
            ->willReturn(true);

        $client = EppoClient::createTestClient( $config);

        $this->expectException(EppoClientException::class);
        $this->expectExceptionCode(EppoException::BANDIT_EVALUATION_FAILED_NO_ACTIONS_PROVIDED);

        $client->getBanditAction($banditKey, $subjectKey, $subject, $actions, $default);
    }
    // Test notABandit
    // test banditDNE
    // test logged

    public function testRepoTestCases(): void
    {
        // Load all the test cases.
        $testCases = $this->loadTestCases();
        $client = EppoClient::getInstance();

        foreach ($testCases as $testFile => $test) {
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
}
