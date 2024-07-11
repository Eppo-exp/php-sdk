<?php

namespace Eppo\Config;

use Eppo\API\CachedResourceMeta;
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
    public function setFlagConfigurations(array $flags, ?BanditVariationIndexer $banditVariations = null): void;

    /**
     * Gets the `BanditVariationIndexer` for mapping from flag variations to bandits.
     * @return BanditVariationIndexer
     */
    public function getBanditVariations(): BanditVariationIndexer;

    /**
     * Gets the metadata from the last flag fetch.
     *
     * @return CachedResourceMeta|null Null if no value is available in the config store.
     */
    public function getFlagCacheMetadata(): ?CachedResourceMeta;
    public function setFlagCacheMetadata(CachedResourceMeta $metadata): void;
}
