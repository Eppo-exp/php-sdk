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

    /**+
     * @param array<string, ?object>|AttributeSet $attributes
     * @return AttributeSet
     */
    public static function fromFlexibleInput(array|AttributeSet $attributes): AttributeSet
    {
        return $attributes instanceof AttributeSet ?
            $attributes:
            AttributeSet::fromArray($attributes);
    }



    /**
     * @param array<string>|array<string, AttributeSet|array<string, array<string, ?object>>> $contexts
     * @return array<string, AttributeSet>
     */
    public static function arrayFromFlexibleInput(array $contexts): array
    {
        $assembledContexts = [];
        foreach ($contexts as $key => $value) {
            if (is_string($value)) {
                // List of action strings with no attributes
                $assembledContexts[$value] = new self([],[]);
            } elseif ($value instanceof AttributeSet) {
                $assembledContexts[$key] = $value;
            } else {
                $assembledContexts[$key] = self::fromArray($value);
            }
        }
        return $assembledContexts;
    }
}
