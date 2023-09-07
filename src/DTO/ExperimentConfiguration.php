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
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $this->setEnabled($configuration['enabled']);
        $this->setSubjectShards($configuration['subjectShards']);

        $rules = [];
        foreach ($configuration['rules'] as $configRule) {
            $rule = new Rule();
            $rule->allocationKey = $configRule['allocationKey'];

            foreach ($configRule['conditions'] as $configCondition) {
                $condition = new Condition();
                $condition->value = $configCondition['value'];
                $condition->operator = $configCondition['operator'];
                $condition->attribute = $configCondition['attribute'];

                $rule->conditions[] = $condition;
            }

            $rules[] = $rule;
        }
        $this->setRules($rules);

        $allocations = [];
        foreach ($configuration['allocations'] as $configAllocationName => $configAllocation) {
            $allocation = new Allocation();
            $allocation->percentExposure = $configAllocation['percentExposure'];

            foreach ($configAllocation['variations'] as $configVariation) {
                $variation = new Variation();
                $variation->shardRange = new ShardRange();
                $variation->name = $configVariation['name'];
                $variation->value = $configVariation['value'];
                $variation->shardRange->start = $configVariation['shardRange']['start'];
                $variation->shardRange->end = $configVariation['shardRange']['end'];

                $allocation->variations[] = $variation;
            }

            $allocations[$configAllocationName] = $allocation;
        }
        $this->setAllocations($allocations);

        $this->setOverrides($configuration['overrides'] ?? []);
        $this->setTypedOverrides($configuration['typedOverrides'] ?? []);
    }

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
    public function getTypedOverrides(): array
    {
        return $this->overrides;
    }

    /**
     * @param array $overrides
     */
    public function setTypedOverrides(array $overrides): void
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
