<?php

namespace Eppo\DTO;


class Shard
{
    /**
     * @param string $salt
     * @param ShardRange[] $ranges
     */
    public function __construct(
        public string $salt,
        public array $ranges)
    {
    }

}