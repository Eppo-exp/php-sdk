<?php

namespace Eppo\DTO;


class Variation
{
    /** @var string */
    private $key;

    /**
     * Properly typed value for this variation.
     * @var array|bool|float|string
     */
    private array|bool|float|string $value;

    public function __construct(string $key, $value, string $valueType)
    {
        $this->key = $key;
        $this->value = $valueType === VariationType::JSON ? json_decode($value) : $value;
    }

    public function getValue(): array|bool|float|string
    {
        return $this->value;
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
