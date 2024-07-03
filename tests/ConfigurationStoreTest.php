<?php

namespace Eppo\Tests;

use Eppo\Cache\DefaultCacheFactory;
use Eppo\ConfigurationStore;
use Eppo\DTO\Flag;
use Eppo\DTO\VariationType;
use PHPUnit\Framework\TestCase;

class ConfigurationStoreTest extends TestCase
{
    public function testFlushesCacheOnReload(): void
    {
        $flag1 = new Flag("flag1", true, [], VariationType::STRING, [], 10_000);
        $flag2 = new Flag("flag2", true, [], VariationType::STRING, [], 10_000);
        $flag3 = new Flag("flag3", true, [], VariationType::STRING, [], 10_000);

        $firstFlags = [$flag1, $flag2];

        $secondFlags = [$flag1, $flag3];

        $configStore = new ConfigurationStore(DefaultCacheFactory::create());


        $configStore->setConfigurations($firstFlags);

        $this->assertHasFlag($flag1, "flag1", $configStore);
        $this->assertHasFlag($flag2, "flag2", $configStore);
        $this->assertHasFlag($flag3, "flag3", $configStore, hasFlag: false);

        $configStore->setConfigurations($secondFlags);

        $this->assertHasFlag($flag1, "flag1", $configStore);
        $this->assertHasFlag($flag2, "flag2", $configStore, hasFlag: false);
        $this->assertHasFlag($flag3, "flag3", $configStore);
    }

    private function assertHasFlag(
        Flag $expected,
        string $flagKey,
        ConfigurationStore $configStore,
        bool $hasFlag = true
    ): void {
        $actual = $configStore->getFlag($flagKey);
        if (!$hasFlag) {
            $this->assertNull($actual);
            return;
        }
        $this->assertNotNull($actual);
        $this->assertEquals($actual, $expected);
    }
}
