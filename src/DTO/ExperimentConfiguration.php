<?php

namespace Eppo\DTO;

class ExperimentConfiguration
{
    /** @var string */
    private $name = '';

    /** @var bool */
    private $enabled = false;

    /** @var int */
    private $subjectShards = 0;

    /** @var array */
    private $overrides = [];

    /** @var array */
    private $allocations = [];

    /** @var array */
    private $rules = [];

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * @return int
     */
    public function getSubjectShards(): int
    {
        return $this->subjectShards;
    }

    /**
     * @param int $subjectShards
     */
    public function setSubjectShards(int $subjectShards): void
    {
        $this->subjectShards = $subjectShards;
    }

    /**
     * @return array
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * @param array $overrides
     */
    public function setOverrides(array $overrides): void
    {
        $this->overrides = $overrides;
    }

    /**
     * @return array
     */
    public function getAllocations(): array
    {
        return $this->allocations;
    }

    /**
     * @param array $allocations
     */
    public function setAllocations(array $allocations): void
    {
        $this->allocations = $allocations;
    }

    /**
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @param array $rules
     */
    public function setRules(array $rules): void
    {
        $this->rules = $rules;
    }
}