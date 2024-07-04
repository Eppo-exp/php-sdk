<?php

namespace Eppo\DTO\Bandit;

class ContextAttributes implements IContextAttributes
{
    public function __construct(public readonly string $key, public readonly AttributeSet $attributes)
    {
    }

    public function getAttributes(): AttributeSet
    {
        return $this->attributes;
    }

    public static function fromArray(string $key, array $attributes): IContextAttributes
    {
        return new self($key, AttributeSet::fromArray($attributes));
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
