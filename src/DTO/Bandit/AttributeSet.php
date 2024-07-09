<?php

namespace Eppo\DTO\Bandit;

class AttributeSet
{
    /**
     * Types to automatically classify as Categorical
     */
    private const CATEGORICAL_TYPES = ["boolean", "string"];

    /**
     * Types to automatically classify as Numeric
     * `float` and `double` are the sane datatype in php.
     * In PHP, for historical reasons, `gettype` returns `double`.
     * `bool` and `int` are type aliases for `boolean` and `integer`, respectively.
     */
    private const NUMERIC_TYPES = ["double", "integer"];


    /**
     * @param array<string, float> $numericAttributes
     * @param array<string, bool|float|int|string> $categoricalAttributes
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
            if (
                in_array(
                    gettype($value),
                    self::NUMERIC_TYPES
                )
            ) {
                $numericAttributes[$key] = $value;
            } elseif (
                in_array(
                    gettype($value),
                    self::CATEGORICAL_TYPES
                )
            ) {
                $categoricalAttributes[$key] = $value;
            } else {
                syslog(LOG_WARNING, "[Eppo SDK] Unsupported attribute type: " . gettype($value));
            }
        }
        return new self($numericAttributes, $categoricalAttributes);
    }

    public function toArray(): array
    {
        return [...$this->numericAttributes, ...$this->categoricalAttributes];
    }
}
