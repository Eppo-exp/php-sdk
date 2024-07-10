<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\DTO\Flag;
use Eppo\IFlags;

interface IConfigurationStore extends IFlags
{
    /**
     * Sets multiple flags in the data store.
     *
     * @param Flag[] $flags
     * @param BanditVariationIndexer|null $banditVariations
     * @return void
     */
    public function setConfigurations(array $flags, BanditVariationIndexer $banditVariations = null): void;

    /**
     * Gets the `BanditVariationIndexer` for mapping from flag variations to bandits.
     * @return BanditVariationIndexer
     */
    public function getBanditVariations(): BanditVariationIndexer;

    /**
     * Gets the age of the cache
     *
     * @return int The age of the cache in seconds. -1 if there has been no cache set.
     */
    public function getFlagCacheAgeSeconds(): int;
}
