<?php

namespace Eppo;

/**
 * Configuration options for the SDKs polling feature.
 */
abstract class PollingOptions
{
    /**
     * @var int|null Age limit for cached configuration (when polling is not used).
     */
    public ?int $cacheAgeLimitMillis = null;

    /**
     * @var int|null Base interval used for polling for new configuration from the API serer.
     *
     * To use background polling, you must call
     * <a href="https://docs.geteppo.com/sdks/server-sdks/php/initialization/#background-polling">startPolling</a>
     */
    public ?int $pollingIntervalMillis;

    /**
     * @var int|null The maximum amount of random time to adjust each polling interval by.
     */
    public ?int $pollingJitterMillis;
}
