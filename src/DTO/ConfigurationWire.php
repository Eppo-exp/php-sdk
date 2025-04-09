<?php

namespace Eppo\DTO;

use Eppo\Traits\StaticCreateSelf;
use Eppo\Traits\ToArray;

class ConfigurationWire
{
    use StaticCreateSelf;
    use ToArray;

    public int $version;
    public ?ConfigResponse $config;
    public ?ConfigResponse $bandits;
}
