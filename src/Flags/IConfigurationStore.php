<?php

namespace Eppo\Flags;

use Eppo\DTO\Flag;

interface IConfigurationStore extends IFlags
{

    /**
     * Sets a flag configuration in the data store
     *
     * @param Flag $flag
     * @return void
     */
    public function setFlag(Flag $flag): void;

    /**
     * Sets multiple flags in the data store.
     *
     * @param Flag[] $flags
     * @return void
     */
    public function setFlags(array $flags): void;
}
