<?php

namespace Eppo\DTO\Bandit;

/**
 * A set of attributes for a given context ($key))
 */
interface IContextAttributes
{
    public function getAttributes(): AttributeSet;

    public function getKey(): string;

    /**
     * Creates a set of attributes by sorting $attributes into numeric and non-numeric (categorical).
     * @param string $key
     * @param array $attributes
     * @return self
     */
    public static function fromArray(string $key, array $attributes): self;

    /**
     * @param string $key
     * @param array<string, int|float> $numericAttributes
     * @param array<string, string|bool|int|float> $categoricalAttributes
     * @return self
     */
    public static function fromAttributes(string $key, array $numericAttributes, array $categoricalAttributes): self;
}
