<?php

namespace Eppo\DTO;

use Eppo\Traits\StaticFromJson;
use Eppo\Traits\ToArray;

class FlagConfigResponse
{
    use StaticFromJson;
    use ToArray;

    public string $createdAt;
    public string $format;
    public array $environment;

    public array $flags;

    /**
     * @var array<string, BanditReference>
     */
    public array $banditReferences;

    public function __construct()
    {
        $this->banditReferences = [];
        $this->format = "SERVER";
    }
}
