<?php

namespace Eppo\DTO\ConfigurationWire;

use Eppo\Traits\StaticFromJson;
use Eppo\Traits\ToArray;

class ConfigResponse
{
    use ToArray;
    use StaticFromJson;

    public function __construct(
        public string $response = "",
        public ?string $fetchedAt = null,
        public ?string $eTag = null
    ) {
    }
}
