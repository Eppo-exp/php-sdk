<?php

namespace Eppo;

abstract class PollingOptions
{
    public ?int $pollingIntervalMillis;
    public ?int $pollingJitterMillis;
}