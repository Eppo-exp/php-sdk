<?php

namespace Eppo\DTO;

class Condition
{
    /** @var string */
    private $operator = '';

    /** @var string */
    private $attribute = '';

    /** @var array|bool|float|string */
    private $value = '';

    /**
     * @param string $operator
     * @param string $attribute
     * @param string|float|bool|array $value
     */
    public function __construct(string $operator, string $attribute,  $value)
    {
        $this->operator = $operator;
        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
