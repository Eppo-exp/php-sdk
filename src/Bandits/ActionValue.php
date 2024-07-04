<?php

namespace Eppo\Bandits;

class ActionValue
{
    public function __construct(public readonly string $action, public readonly float $value)
    {
    }
}
