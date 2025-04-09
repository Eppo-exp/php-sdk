<?php

namespace Eppo\DTO\ConfigurationWire;

use Eppo\Traits\StaticCreateSelf;
use Eppo\Traits\ToArray;

class ConfigResponse
{
    use ToArray;
    use StaticCreateSelf;

    public function __construct(
        public ?string $response = null,
        public ?string $fetchedAt = null,
        public ?string $eTag = null
    ) {
    }
}
