<?php

namespace Eppo\DTO\Bandit;



class AttributeSet
{
    /**
     * @param array<string, float> $numericAttributes
     * @param array<string, string|float|bool|int> $categoricalAttributes
     */
    public function __construct(public array $numericAttributes = [], public array $categoricalAttributes = [])
    {
    }
    public static function fromArray(array $attributes): self {
        $categoricalAttributes = [];
        $numericAttributes = [];
        foreach ($attributes as $key => $value) {
            if (is_numeric($key)) {
                $numericAttributes[$key] = $value;
            } else {
                $categoricalAttributes[$key] = $value;
            }
        }
        return new self($numericAttributes, $categoricalAttributes);
    }
}