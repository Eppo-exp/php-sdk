<?php

namespace Eppo\Config;

use Eppo\Bandits\BanditVariationIndexer;
use Eppo\Bandits\IBandits;
use Eppo\DTO\Bandit\Bandit;
use Eppo\Bandits\IBanditVariationIndexer;
use Eppo\DTO\Flag;
use Eppo\Exception\InvalidConfigurationException;
use Eppo\Flags\IFlags;

interface IConfigurationStore extends IFlags, IBandits
{
    /**
     * Sets flag configuration in the data store.
     *
     * @param Flag[] $flags
     * @param IBanditVariationIndexer|null $banditVariations
     * @return void
     */
    public function setUnifiedFlagConfiguration(array $flags, ?IBanditVariationIndexer $banditVariations = null): void;

    /**
     * Sets the Bandit model configurations in the data store.
     * @param Bandit[] $bandits
     * @return void
     * @throws InvalidConfigurationException
     */
    public function setBandits(array $bandits): void;

    /**
     * Gets the metadata from the data store.
     */
    public function getMetadata(string $key): mixed;

    /**
     * Sets metadata in the data store.
     *
     * @param string $key
     * @param mixed $metadata
     * @return void
     */
    public function setMetadata(string $key, mixed $metadata): void;
}
