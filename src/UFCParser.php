<?php

namespace Eppo;

use Eppo\DTO\Allocation;
use Eppo\DTO\Condition;
use Eppo\DTO\Flag;
use Eppo\DTO\Rule;
use Eppo\DTO\Shard;
use Eppo\DTO\Range;
use Eppo\DTO\Split;
use Eppo\DTO\Variation;
use Eppo\DTO\VariationType;

class UFCParser
{
    /**
     * Parses UFCv1 formatted data into a Flag
     * @param array $configuration
     * @return Flag
     */
    public function parseFlag(array $configuration): Flag
    {
        $variations = self::parseVariations($configuration);
        $allocations = self::parseAllocations($configuration['allocations']);

        return new Flag($configuration['key'],
            $configuration['enabled'],
            $allocations,
            VariationType::from($configuration['variationType']),
            $variations,
            $configuration['totalShards']);
    }


    /**
     * @param $configuration
     * @return Variation[]
     */
    private static function parseVariations($configuration): array
    {
        return array_map(function ($variationConfig) use ($configuration) {
            return new Variation(
                $variationConfig['key'],
                $variationConfig['value'],
                VariationType::from($configuration['variationType'])
            );
        },
            $configuration['variations']);
    }

    /**
     * @param $allocations1
     * @return Allocation[]
     */
    private static function parseAllocations($allocations1): array
    {
        $allocations = array_map(function ($allocationConfig) {
            $rules = array_map(function ($ruleConfig) {
                $conditions = array_map(function ($conditionConfig) {
                    return new Condition(
                        $conditionConfig['attribute'],
                        $conditionConfig['operator'],
                        $conditionConfig['value']
                    );
                }, $ruleConfig['conditions']);
                return new Rule($conditions);
            }, $allocationConfig['rules']);

            $splits = array_map(function ($splitConfig) {

                $shards = array_map(function ($shardConfig) {

                    $ranges = array_map(function ($rangeConfig) {
                        return new Range($rangeConfig['start'], $rangeConfig['end']);
                    }, $shardConfig['ranges']);

                    return new Shard(
                        $shardConfig['salt'],
                        $ranges
                    );
                }, $splitConfig['shards']);

                return new Split(
                    $splitConfig['variationKey'],
                    $shards,
                    array_key_exists('extraLogging', $splitConfig) ? $splitConfig['extraLogging'] : []);
            }, $allocationConfig['splits']);

            return new Allocation(
                $allocationConfig['key'],
                $rules,
                $splits,
                (bool)$allocationConfig['doLog'],
                array_key_exists('startAt', $allocationConfig) ? strtotime($allocationConfig['startAt']) : null,
                array_key_exists('endAt', $allocationConfig) ? strtotime($allocationConfig['endAt']) : null
            );
        }, $allocations1);
        return $allocations;
    }
}