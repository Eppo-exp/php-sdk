<?php

namespace Eppo\DTO;

class Rule
{
    /**
     * @param Condition[] $conditions
     */
    public function __construct(public array $conditions)
    {
    }
}
