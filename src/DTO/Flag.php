<?php

namespace Eppo\DTO;

class Flag
{
    /**
     * @param string $key
     * @param bool $enabled
     * @param Allocation[] $allocations
     * @param VariationType $variationType
     * @param Variation[] $variations
     * @param int $totalShards
     */
    public function __construct(
        public string $key,
        public bool $enabled,
        public array $allocations,
        public VariationType $variationType,
        public array $variations,
        public int $totalShards
    ) {
    }

    public static function fromJson(array $json): Flag
    {
        $variationType = VariationType::from($json['variationType']);
        $variations = self::parseVariations($json['variations'], $variationType);
        $allocations = self::parseAllocations($json['allocations']);

        return new Flag(
            $json['key'],
            $json['enabled'],
            $allocations,
            $variationType,
            $variations,
            $json['totalShards']
        );
    }


    /**
     * @param array $variations
     * @param VariationType $variationType
     * @return Variation[]
     */
    private static function parseVariations(array $variations, VariationType $variationType): array
    {
        return array_map(function ($variationConfig) use ($variationType) {
            $typedValue = $variationType === VariationType::JSON ? json_decode(
                $variationConfig['value'],
                true
            ) : $variationConfig['value'];
            return new Variation($variationConfig['key'], $typedValue);
        },
            $variations);
    }

    /**
     * @param array $allocations
     * @return Allocation[]
     */
    private static function parseAllocations(array $allocations): array
    {
        return array_map(function ($allocationConfig) {
            $rules = array_key_exists('rules', $allocationConfig) ? array_map(function ($ruleConfig) {
                $conditions = array_map(function ($conditionConfig) {
                    return new Condition(
                        $conditionConfig['attribute'],
                        $conditionConfig['operator'],
                        $conditionConfig['value']
                    );
                }, $ruleConfig['conditions']);
                return new Rule($conditions);
            }, $allocationConfig['rules']) : null;

            $splits = array_map(function ($splitConfig) {
                $shards = array_map(function ($shardConfig) {
                    $ranges = array_map(function ($rangeConfig) {
                        return new ShardRange($rangeConfig['start'], $rangeConfig['end']);
                    }, $shardConfig['ranges']);

                    return new Shard(
                        $shardConfig['salt'],
                        $ranges
                    );
                }, $splitConfig['shards']);

                return new Split(
                    $splitConfig['variationKey'],
                    $shards,
                    array_key_exists('extraLogging', $splitConfig) ? $splitConfig['extraLogging'] : []
                );
            }, $allocationConfig['splits']);

            return new Allocation(
                $allocationConfig['key'],
                $rules,
                $splits,
                !(array_key_exists('doLog', $allocationConfig) && $allocationConfig['doLog'] === false),
                array_key_exists('startAt', $allocationConfig) ? strtotime($allocationConfig['startAt']) : null,
                array_key_exists('endAt', $allocationConfig) ? strtotime($allocationConfig['endAt']) : null
            );
        }, $allocations);
    }
}
