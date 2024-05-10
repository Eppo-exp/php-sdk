<?php

namespace Eppo\DTO;


class Variation
{
    public array|bool|float|string $value;

    public function __construct(public string $key, bool|float|string $value, VariationType $valueType)
    {
        $this->value = $valueType === VariationType::JSON ? json_decode($value) : $value;
    }
}
