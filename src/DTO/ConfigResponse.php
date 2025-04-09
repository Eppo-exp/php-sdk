<?php

namespace Eppo\DTO;

use Eppo\Traits\StaticCreateSelf;
use Eppo\Traits\ToArray;

class ConfigResponse
{
    use StaticCreateSelf;
    use ToArray;

    public string $response;
    public ?string $eTag;
    public ?string $fetchedAt;
}
