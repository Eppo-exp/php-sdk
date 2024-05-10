<?php

namespace Eppo\DTO;

class Condition
{
    public function __construct(public string $attribute, public string $operator, public array|bool|float|string $value)
    {
    }
}
