<?php

namespace Eppo\DTO;


class Shard
{
    /**
     * @param string $salt
     * @param Range[] $ranges
     */
    public function __construct(
        public string $salt,
        public array $ranges)
    {
    }

}