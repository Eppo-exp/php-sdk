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
     * @param float|bool|array|string $value
     */
    public function __construct(string $attribute, string $operator, float|bool|array|string $value)
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
