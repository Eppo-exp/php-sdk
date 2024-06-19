<?php

namespace Eppo\DTO;

class Allocation
{
    public bool $doLog;

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
        public ?array $rules,
        public array $splits,
        ?bool $doLog = true,
        public ?int $startAt = null,
        public ?int $endAt = null)
    {
        $this->doLog = $doLog === null ? true : $doLog;
    }
}
