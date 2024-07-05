<?php

namespace Eppo\Bandits;

/**
 * A simple key-value class to help store actions and their associated scores and weights.
 */
class ActionValue
{
    public function __construct(public readonly string $action, public readonly float $value)
    {
    }
}
