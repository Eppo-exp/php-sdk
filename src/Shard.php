<?php

namespace Eppo;

use Eppo\DTO\Range;

final class Shard
{
    /**
     * @param string $input
     * @param int $subjectShards
     *
     * @return int
     */
    public static function getShard(string $input, int $subjectShards): int
    {
        $hashOutput = hash('md5', $input);
        $intFromHash = hexdec(substr($hashOutput, 0, 8));
        return $intFromHash % $subjectShards;
    }

    /**
     * @param int $shard
     * @param Range $range
     *
     * @return bool
     */
    public static function isShardInRange(int $shard, Range $range): bool
    {
        return $shard >= $range->start && $shard < $range->end;
    }
}
