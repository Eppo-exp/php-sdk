<?php

namespace Eppo\DTO\Bandit;

class ContextAttributes implements IContextAttributes
{
    public function __construct(public readonly string $key, public readonly AttributeSet $attributes)
    {
    }

    /**+
     * @param string $key
     * @param array<string, ?object>|AttributeSet $attributes
     * @return IContextAttributes
     */
    public static function fromFlexibleInput(string $key, array|AttributeSet $attributes): IContextAttributes
    {
        return $attributes instanceof AttributeSet ?
            new ContextAttributes(
                $key,
                $attributes
            ) :
            ContextAttributes::fromArray($key, $attributes);
    }

    public function getAttributes(): AttributeSet
    {
        return $this->attributes;
    }

    public static function fromArray(string $key, array $attributes): IContextAttributes
    {
        return new self($key, AttributeSet::fromArray($attributes));
    }

    /**
     * @param array<string>|array<string, array<string, ?object>>|array<string, AttributeSet> $contexts
     * @return array<string, IContextAttributes>
     */
    public static function arrayFromFlexibleInput(array $contexts): array
    {
        $assembledContexts = [];
        foreach ($contexts as $key => $value) {
            if (is_string($value)) {
                // List of action strings with no attributes
                $assembledContexts[$value] = self::fromArray($value, []);
            } elseif ($value instanceof AttributeSet) {
                $assembledContexts[$key] = new self($key, $value);
            } else {
                $assembledContexts[$key] = self::fromArray($key, $value);
            }
        }
        return $assembledContexts;
    }

    public static function fromAttributes(
        string $key,
        array $numericAttributes,
        array $categoricalAttributes
    ): IContextAttributes {
        return new self($key, new AttributeSet($numericAttributes, $categoricalAttributes));
    }

    public function getKey(): string
    {
        return $this->key;
    }
}
