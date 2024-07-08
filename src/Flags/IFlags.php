<?php

namespace Eppo\Flags;

use Eppo\DTO\Flag;
use Eppo\Exception\InvalidConfigurationException;

/**
 * A collection of Flags indexed by key.
 */
interface IFlags
{
    /**
     * Gets a flag from the collection if it exists.
     * @param string $key
     * @return ?Flag
     * @throws InvalidConfigurationException
     */
    public function getFlag(string $key): ?Flag;
}
