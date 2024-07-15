<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Bandits\IBandits;
use Eppo\DTO\Bandit\Bandit;
use Eppo\DTO\Flag;
use Eppo\Exception\InvalidConfigurationException;
use Eppo\Flags\IFlags;

interface IConfigurationStore extends IFlags, IBandits
{
    /**
     * Sets configuration objects in the data store.
     *
     * Implementations of this method should also store the time in order to respond to `getFlagCacheAgeSeconds` calls.
     *
     * @param Flag[] $flags
     * @param BanditVariationIndexer|null $banditVariations
     * @return void
     * @throws InvalidConfigurationException
     */
    public function setConfigurations(
        array $flags,
        BanditVariationIndexer $banditVariations = null
    ): void;

    /**
     * Sets the Bandit model configurations in the data store.
     * @param Bandit[] $bandits
     * @return void
     */
    public function setBanditModels(array $bandits): void;

    /**
     * Gets the `BanditVariationIndexer` for mapping from flag variations to bandits.
     * @return BanditVariationIndexer
     * @throws InvalidConfigurationException
     */
    public function getBanditVariations(): BanditVariationIndexer;

    /**
     * Gets the age of the cache
     *
     * @return int The age of the cache in seconds. -1 if there has been no cache set.
     */
    public function getFlagCacheAgeSeconds(): int;
}
