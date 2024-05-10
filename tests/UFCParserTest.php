<?php


use Eppo\Config\SDKData;
use Eppo\ConfigurationStore;
use Eppo\DTO\Flag;

use Eppo\DTO\VariationType;
use Eppo\ExperimentConfigurationRequester;
use Eppo\HttpClient;
use Eppo\UFCParser;
use PHPUnit\Framework\TestCase;
use Sarahman\SimpleCache\FileSystemCache;

class UFCParserTest extends TestCase
{
    /** @var string */
    const FLAG_KEY = 'kill-switch';

    const MOCK_DATA_FILENAME = __DIR__ . '/mockdata/ufc-v1.json';


    public static function setUpBeforeClass(): void
    {
//        try {
//            MockWebServer::start();
//        } catch (Exception $exception) {
//            self::fail('Failed to start mocked web server: ' . $exception->getMessage());
//        }
    }

    public static function tearDownAfterClass(): void
    {
//        MockWebServer::stop();
    }

    public function testParsesComplexFlagPayload(): void
    {
        $parser = new UFCParser();
        $ufcPayload = json_decode(file_get_contents(self::MOCK_DATA_FILENAME), true);
        $flags = $ufcPayload['flags'];
        $flag = $parser->parseFlag($flags[self::FLAG_KEY]);

        $this->assertInstanceOf(Flag::class, $flag);

        $this->assertEquals(self::FLAG_KEY, $flag->getKey());
        $this->assertTrue($flag->isEnabled());
        $this->assertEquals(VariationType::BOOLEAN, $flag->getVariationType());

        $this->assertCount(2, $flag->getVariations());

        /** @see `../mockdata/ufc-v1.json` */
        $firstVariation = $flag->getVariations()['on'];
        $this->assertEquals('on', $firstVariation->getKey());
        $val = $firstVariation->getValue();

        $this->assertTrue($firstVariation->getValue());

        $this->assertCount(3, $flag->getAllocations());

        /** @see `../mockdata/ufc-v1.json` */
        $secondAllocation = $flag->getAllocations()[1];
        $this->assertEquals('on-for-age-50+', $secondAllocation->getKey());
        $this->assertTrue($secondAllocation->getDoLog());

        $this->assertCount(1, $secondAllocation->getRules());

        $firstRule = $secondAllocation->getRules()[0];
        $this->assertCount(1, $firstRule->getConditions());

        $firstCondition = $firstRule->getConditions()[0];
        $this->assertEquals('age', $firstCondition->getAttribute());
        $this->assertEquals('GTE', $firstCondition->getOperator());
        $this->assertEquals(50, $firstCondition->getValue());

        $this->assertCount(1, $secondAllocation->getSplits());

        $firstSplit = $secondAllocation->getSplits()[0];
        $this->assertEquals('on', $firstSplit->getVariationKey());
        $this->assertEmpty($firstSplit->getExtraLogging());

        $this->assertCount(1, $firstSplit->getShards());
        $firstShard = $firstSplit->getShards()[0];
        $this->assertEquals('some-salt', $firstShard->getSalt());
        $this->assertCount(1, $firstShard->getRanges());

        $firstRange = $firstShard->getRanges()[0];
        $this->assertEquals(0, $firstRange->getStart());
        $this->assertEquals(10000, $firstRange->getEnd());
    }
}
