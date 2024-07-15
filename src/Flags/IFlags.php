<?php

namespace Eppo\Flags;

use Eppo\Bandits\IBanditVariationIndexer;
use Eppo\DTO\Flag;
use Eppo\Exception\InvalidConfigurationException;

/**
 * This interface represents an object which can provide UFC (Unified Flag Config) data.
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

    /**
     * Gets the `BanditVariationIndexer` for mapping from flag variations to bandits.
     * @return IBanditVariationIndexer
     */
    public function getBanditVariations(): IBanditVariationIndexer;
}
