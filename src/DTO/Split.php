<?php

namespace Eppo\DTO;

class Split
{
    public function getVariationKey(): string
    {
        return $this->variationKey;
    }

    public function getShards(): array
    {
        return $this->shards;
    }

    public function getExtraLogging(): array
    {
        return $this->extraLogging;
    }
    /**
     * @var string
     */
    private string $variationKey;
    /**
     * @var Shard[]
     */
    private array $shards;
    /**
     * @var array
     */
    private array $extraLogging;

    /**
     * @param $variationKey
     * @param $shards
     */
    public function __construct($variationKey, $shards, $extraLogging)
    {
        $this->variationKey = $variationKey;
        $this->shards = $shards;
        $this->extraLogging = $extraLogging;
    }

}