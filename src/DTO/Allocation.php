<?php

namespace Eppo\DTO;

class Allocation
{
    /** @var string */
    private $key;
    /** @var int|null */
    private $startAt;
    /** @var int|null */
    private $endAt;
    /** @var Rule[] */
    private $rules;
    /** @var Split[] */
    private $splits;
    /** @var boolean */
    private $doLog;

    /**
     * @param string $key
     * @param Rule[] $rules
     * @param Split[] $splits
     * @param bool $doLog
     * @param int|null $startAt
     * @param int|null $endAt
     */
    public function __construct(string $key, array $rules, array $splits, bool $doLog, ?int $startAt, ?int $endAt)
    {
        $this->key = $key;
        $this->startAt = $startAt;
        $this->endAt = $endAt;
        $this->rules = $rules;
        $this->splits = $splits;
        $this->doLog = $doLog;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return int
     */
    public function getStartAt(): int
    {
        return $this->startAt;
    }

    /**
     * @return int
     */
    public function getEndAt(): int
    {
        return $this->endAt;
    }

    /**
     * @return Rule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return Split[]
     */
    public function getSplits(): array
    {
        return $this->splits;
    }

    /**
     * @return bool
     */
    public function getDoLog(): bool
    {
        return $this->doLog;
    }
}
