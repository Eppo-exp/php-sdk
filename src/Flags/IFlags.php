<?php

namespace Eppo\Flags;

use Eppo\Bandits\IBanditReferenceIndexer;
use Eppo\DTO\Flag;

/**
 * This interface represents an object which can provide UFC (Unified Flag Config) data.
 */
interface IFlags
{
    /**
     * Gets a flag from the collection if it exists.
     * @param string $key
     * @return ?Flag
     */
    public function getFlag(string $key): ?Flag;

    /**
     * Gets the `BanditVariationIndexer` for mapping from flag variations to bandits.
     * @return IBanditReferenceIndexer
     */
    public function getBanditReferenceIndex(): IBanditReferenceIndexer;
}
