<?php
namespace Eppo\DTO\Bandit;


use Eppo\DTO\IDeserializable;

class BanditVariation implements IDeserializable {
    public string $key;
    public string $flagKey;
    public string $variationKey;
    public string $variationValue;

    public function __construct(string $key, string $flagKey, string $variationKey, string $variationValue) {
        $this->key = $key;
        $this->flagKey = $flagKey;
        $this->variationKey = $variationKey;
        $this->variationValue = $variationValue;
    }

    public static function fromJson($json): BanditVariation
    {
        return new self($json['key'], $json['flagKey'], $json['variationKey'], $json['variationValue']);
    }
}
