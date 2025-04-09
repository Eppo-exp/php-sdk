<?php

namespace Eppo\DTO;

use Eppo\Traits\StaticCreateSelf;
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
    use StaticCreateSelf;
    use ToArray;

    public string $createdAt;
    public string $format;
    public array $environment;

    public array $flags;

    /**
     * @var array<string, BanditReference>
     */
    public array $banditReferences;
}
