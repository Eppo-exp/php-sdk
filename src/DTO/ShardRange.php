<?php

namespace Eppo\DTO;

class ShardRange
{
    public function __construct(public int $start, public int $end)
    {
    }
}
