<?php

namespace Eppo\DTO;

class Allocation
{
    /**
     * @param string $key
     * @param Rule[] $rules
     * @param Split[] $splits
     * @param bool $doLog
     * @param int|null $startAt
     * @param int|null $endAt
     */
    public function __construct(
        public string $key,
        public array $rules,
        public array $splits,
        public bool $doLog,
        public ?int $startAt,
        public ?int $endAt)
    {
    }
}
