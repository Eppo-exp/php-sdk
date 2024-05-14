<?php

namespace Eppo\DTO;


class Variation
{
    public function __construct(public string $key, public array|bool|float|string $value)
    {
    }
}
