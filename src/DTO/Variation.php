<?php

namespace Eppo\DTO;

class Variation
{
    /** @var string */
    public $name;

    /** @var string */
    public $value;

    /** @var string|float|int|bool|null */
    public $typedValue;

    /** @var ShardRange */
    public $shardRange;
}
