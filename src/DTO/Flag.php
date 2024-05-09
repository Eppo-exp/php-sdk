<?php
namespace Eppo\DTO;

class Flag
{
    /** @var string */
    private $key = '';

    /** @var bool */
    private $enabled = false;

    /** @var array */
    private $allocations = [];

    public function getVariationType(): string
    {
        return $this->variationType;
    }

    public function getVariations(): array
    {
        return $this->variations;
    }

    /**
     * @var string
     * One of `Eppo\VariationType`
     */
    private $variationType;

    /**
     * @var array
     * Array of `Variation`
     */
    private $variations = [];

    /** @var int */
    private $totalShards = 0;

    /**
     * @param string $key
     * @param bool $enabled
     * @param array $allocations
     * @param string $variationType
     * @param array $variations
     * @param int $totalShards
     */
    public function __construct(string $key, bool $enabled, array $allocations, string $variationType, array $variations, int $totalShards)
    {
        $this->key = $key;
        $this->enabled = $enabled;
        $this->allocations = $allocations;
        $this->variationType = $variationType;
        $this->variations = $variations;
        $this->totalShards = $totalShards;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return int
     */
    public function getTotalShards(): int
    {
        return $this->totalShards;
    }

    /**
     * @return array
     */
    public function getAllocations(): array
    {
        return $this->allocations;
    }
}
