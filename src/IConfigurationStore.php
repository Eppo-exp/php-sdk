<?php

namespace Eppo;

use Eppo\DTO\Flag;

interface IConfigurationStore extends IFlags
{
    /**
     * Sets multiple flags in the data store.
     *
     * @param Flag[] $flags
     * @return void
     */
    public function setFlags(array $flags): void;

    /**
     * Gets the age of the cache
     *
     * @return int The age of the cache in seconds. -1 if there has been no cache set.
     */
    public function getFlagCacheAge(): int;
}
