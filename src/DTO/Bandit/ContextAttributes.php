<?php

namespace Eppo\DTO\Bandit;

/**
 * A set of attributes (@@see AttributeSet) for the context referenced by @see IContextAttributes::getKey().
 * @see IContextAttributes for more details.
 */
class ContextAttributes implements IContextAttributes
{
    /**
     * @param string $key The key used to identify this context. ex: Subject ID or Action Name.
     * @param AttributeSet $attributes
     */
    public function __construct(public readonly string $key, public readonly AttributeSet $attributes)
    {
    }

    public function getAttributes(): AttributeSet
    {
        return $this->attributes;
    }

    /**
     * @param string $key The key used to identify this context. ex: Subject ID or Action Name.
     * @param array $attributes
     * @return IContextAttributes
     */
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
