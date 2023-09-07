<?php

namespace Eppo\DTO;

class Variation
{
    /** @var string */
    public $name;

    /** @var string */
    public $value;

    /** @var mixed */
    public $typedValue;

    /** @var ShardRange */
    public $shardRange;
}
