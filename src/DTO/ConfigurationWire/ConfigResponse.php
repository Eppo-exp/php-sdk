<?php

namespace Eppo\DTO\ConfigurationWire;

use Eppo\Traits\StaticCreateSelf;
use Eppo\Traits\ToArray;

class ConfigResponse
{
    use ToArray;
    use StaticCreateSelf;

    public string $fetchedAt;

    public function __construct(
        public string $response = "",
        ?string $fetchedAt = null,
        public ?string $eTag = null
    ) {
        $this->fetchedAt = $fetchedAt ?? date('c');
    }
}
