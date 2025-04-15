<?php

namespace Eppo\DTO;

class BanditFlagVariation
{
    public function __construct(
        public readonly string $key,
        public readonly string $flagKey,
        public readonly string $allocationKey,
        public readonly string $variationKey,
        public readonly string $variationValue
    ) {
    }

    public static function fromArray($arr): BanditFlagVariation
    {
        return new self(
            $arr['key'],
            $arr['flagKey'],
            $arr['allocationKey'],
            $arr['variationKey'],
            $arr['variationValue']
        );
    }

    public function __serialize(): array
    {
        return [
            'key' => $this->key,
            'flagKey' => $this->flagKey,
            'allocationKey' => $this->allocationKey,
            'variationKey' => $this->variationKey,
            'variationValue' => $this->variationValue
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->key = $data['key'];
        $this->flagKey = $data['flagKey'];
        $this->allocationKey = $data['allocationKey'];
        $this->variationKey = $data['variationKey'];
        $this->variationValue = $data['variationValue'];
    }
}
