<?php

namespace Eppo;

use Eppo\DTO\ShardRange;

final class Shard
{
    public static function getShard(string $input, int $subjectShards): int
    {
        $hashOutput = hash('md5', $input);
        $intFromHash = hexdec(substr($hashOutput, 0, 8));
        return $intFromHash % $subjectShards;
    }

    public static function isShardInRange(int $shard, ShardRange $range): bool
    {
        return $shard >= $range->start && $shard < $range->end;
    }
}
