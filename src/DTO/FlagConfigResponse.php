<?php

namespace Eppo\DTO;

use Eppo\Traits\StaticFromJson;
use Eppo\Traits\ToArray;

/**
 * Class FlagConfigResponse
 * @package Eppo\DTO
 *
 * @property string $createdAt ISO formatted string
 * @property string $format
 * @property array $environment
 * @property array<string, Flag> $flags
 * @property array<string, BanditReference> $banditReferences
 */
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
