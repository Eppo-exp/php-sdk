<?php

namespace Eppo\DTO\Bandit;

/**
 * A set of attributes (@@see AttributeSet) for the context referenced by @see IContextAttributes::getKey().
 *
 * The _Context_ is intended to be either a *subject* or an *action*, thus the key returned by `getKey()` refers to the
 * `subjectKey` and `actionKey`, respectively.
 *
 * Often, the `AttributeSet` for a subject or action needs to be indexed or keyed in an array. This class makes it easier
 * to manipulate a group of `AttributeSet`s while keeping track of their identifiers next to the attributes, instead of
 * in an external array/map.
 */
interface IContextAttributes
{
    public function getAttributes(): AttributeSet;

    public function getKey(): string;

    /**
     * Creates an instance by sorting $attributes into numeric and non-numeric (categorical) based on type.
     *
     * @param string $key The key used to identify this context. ex: Subject ID or Action Name.
     * @param array $attributes
     * @return self
     */
    public static function fromArray(string $key, array $attributes): self;

    /**
     * Creates an instance using explicit groupings of numeric and categorical attributes.
     *
     * @param string $key The key used to identify this context. ex: Subject ID or Action Name.
     * @param array<string, int|float> $numericAttributes
     * @param array<string, string|bool|int|float> $categoricalAttributes
     * @return self
     */
    public static function fromAttributes(string $key, array $numericAttributes, array $categoricalAttributes): self;
}
