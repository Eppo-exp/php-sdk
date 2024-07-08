<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Flag;
use Eppo\IFlags;

interface IConfigurationStore extends IFlags, IBandits
{
    /**
     * Sets configuration objects in the data store.
     *
     * Implementations of this method must also register the time in order to respond to the `getFlagCacheAgeSeconds` method.
     *
     * @param Flag[] $flags
     * @param Bandit[] $bandits
     * @param BanditVariationIndexer|null $banditVariations
     * @return void
     */
    public function setConfigurations(array $flags, array $bandits, BanditVariationIndexer $banditVariations = null): void;

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