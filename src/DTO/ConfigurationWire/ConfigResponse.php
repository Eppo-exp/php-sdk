<?php

namespace Eppo\DTO\ConfigurationWire;

use Eppo\Traits\StaticFromArray;
use Eppo\Traits\ToArray;

class ConfigResponse
{
    use ToArray;
    use StaticFromArray;

    public function __construct(
        public string $response = "",
        public ?string $fetchedAt = null,
        public ?string $eTag = null
    ) {
    }
}
