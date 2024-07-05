<?php

namespace Eppo\DTO\Bandit;


class AttributeSet
{
    /**
     * @param array<string, float> $numericAttributes
     * @param array<string, string|float|bool|int> $categoricalAttributes
     */
    public function __construct(
        public readonly array $numericAttributes = [],
        public readonly array $categoricalAttributes = []
    ) {
    }

    public static function fromArray(array $attributes): self
    {
        $categoricalAttributes = [];
        $numericAttributes = [];
        foreach ($attributes as $key => $value) {
            if (is_numeric($value)) {
                $numericAttributes[$key] = $value;
            } else {
                $categoricalAttributes[$key] = $value;
            }
        }
        return new self($numericAttributes, $categoricalAttributes);
    }
}
