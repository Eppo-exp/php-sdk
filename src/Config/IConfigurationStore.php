<?php

namespace Eppo\Config;

use Eppo\Bandits\IBanditVariationIndexer;
use Eppo\DTO\Flag;
use Eppo\IFlags;


interface IConfigurationStore extends IFlags
{
    /**
     * Sets multiple flags in the data store.
     *
     * @param Flag[] $flags
     * @param IBanditVariationIndexer|null $banditVariations
     * @return void
     */
    public function setUnifiedFlagConfiguration(array $flags, ?IBanditVariationIndexer $banditVariations = null): void;

    /**
     * Gets the metadata from the data store.
     */
    public function getMetadata(string $key): ?string;

    /**
     * Sets metadata in the data store.
     *
     * @param string $key
     * @param mixed $metadata
     * @return void
     */
    public function setMetadata(string $key, mixed $metadata): void;
}
