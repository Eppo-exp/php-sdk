<?php

namespace Eppo;

use Eppo\DTO\Flag;

/**
 * A collection of Flags indexed by key.
 */
interface IFlags
{
    /**
     * Gets a flag from the collection if it exists.
     * @param string $key
     * @return ?Flag
     */
    public function get(string $key): ?Flag;
}
