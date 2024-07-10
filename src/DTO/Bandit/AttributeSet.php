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

    public readonly array $numericAttributes;

    /**
     * @param array<string, int|float> $numericAttributes
     * @param array<string, bool|float|int|string> $categoricalAttributes
     */
    public function __construct(
        array $numericAttributes = [],
        public readonly array $categoricalAttributes = []
    ) {
        // Drop any non numbers in the numeric attributes.
        $numeric = [];
        foreach ($numericAttributes as $key => $value) {
            if (self::isNumberType($value)) {
                $numeric[$key] = $value;
            }
        }
        $this->numericAttributes = $numeric;
    }

    public static function fromArray(array $attributes): self
    {
        $categoricalAttributes = [];
        $numericAttributes = [];
        foreach ($attributes as $key => $value) {
            if (self::isNumberType($value)) {
                $numericAttributes[$key] = $value;
            } elseif (self::isCategoricalType($value)) {
                $categoricalAttributes[$key] = $value;
            } else {
                syslog(LOG_WARNING, "[Eppo SDK] Unsupported attribute type: " . gettype($value));
            }
        }
        return new self($numericAttributes, $categoricalAttributes);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public static function isCategoricalType(mixed $value): bool
    {
        return in_array(
            gettype($value),
            self::CATEGORICAL_TYPES
        );
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public static function isNumberType(mixed $value): bool
    {
        return in_array(
            gettype($value),
            self::NUMERIC_TYPES
        );
    }
}
