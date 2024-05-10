<?php


use Eppo\DTO\Flag;
use Eppo\DTO\VariationType;
use Eppo\UFCParser;
use PHPUnit\Framework\TestCase;

class UFCParserTest extends TestCase
{
    /** @var string */
    const FLAG_KEY = 'kill-switch';

    const MOCK_DATA_FILENAME = __DIR__ . '/mockdata/ufc-v1.json';

    public function testParsesComplexFlagPayload(): void
    {
        $parser = new UFCParser();
        $ufcPayload = json_decode(file_get_contents(self::MOCK_DATA_FILENAME), true);
        $flags = $ufcPayload['flags'];
        $flag = $parser->parseFlag($flags[self::FLAG_KEY]);

        $this->assertInstanceOf(Flag::class, $flag);

        $this->assertEquals(self::FLAG_KEY, $flag->key);
        $this->assertTrue($flag->enabled);
        $this->assertEquals(VariationType::BOOLEAN, $flag->variationType);

        $this->assertCount(2, $flag->variations);

        /** @see `../mockdata/ufc-v1.json` */
        $firstVariation = $flag->variations['on'];
        $this->assertEquals('on', $firstVariation->key);
        $this->assertTrue($firstVariation->value);

        $this->assertCount(3, $flag->allocations);

        /** @see `../mockdata/ufc-v1.json` */
        $secondAllocation = $flag->allocations[1];
        $this->assertEquals('on-for-age-50+', $secondAllocation->key);
        $this->assertTrue($secondAllocation->doLog);

        $this->assertCount(1, $secondAllocation->rules);

        $firstRule = $secondAllocation->rules[0];
        $this->assertCount(1, $firstRule->conditions);

        $firstCondition = $firstRule->conditions[0];
        $this->assertEquals('age', $firstCondition->attribute);
        $this->assertEquals('GTE', $firstCondition->operator);
        $this->assertEquals(50, $firstCondition->value);

        $this->assertCount(1, $secondAllocation->splits);

        $firstSplit = $secondAllocation->splits[0];
        $this->assertEquals('on', $firstSplit->variationKey);
        $this->assertEmpty($firstSplit->extraLogging);

        $this->assertCount(1, $firstSplit->shards);
        $firstShard = $firstSplit->shards[0];
        $this->assertEquals('some-salt', $firstShard->salt);
        $this->assertCount(1, $firstShard->ranges);

        $firstRange = $firstShard->ranges[0];
        $this->assertEquals(0, $firstRange->start);
        $this->assertEquals(10000, $firstRange->end);
    }
}
