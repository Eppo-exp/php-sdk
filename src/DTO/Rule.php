<?php

namespace Eppo\DTO;

class Rule
{

    /** @var array */
    private $conditions = [];

    /**
     * @param array $conditions
     */
    public function __construct(array $conditions)
    {
        $this->conditions = $conditions;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }
}
