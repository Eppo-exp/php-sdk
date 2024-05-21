<?php

namespace Eppo\DTO;

class Condition
{
    public Operator $operator;

    public function __construct(public string $attribute, string $operator, public array|bool|float|string $value)
    {
        $this->operator = Operator::from($operator);
    }
}
