<?php

namespace Eppo\DTO;

use Eppo\Traits\StaticCreateSelf;
use Eppo\Traits\ToArray;

/**
 * Class BanditParametersResponse
 * @package Eppo\DTO
 *
 * @property array<string, BanditParameters> $bandits
 */
class BanditParametersResponse
{
    use StaticCreateSelf;
    use ToArray;

    public array $bandits;

    public function __construct(array $bandits)
    {
        $this->bandits = $bandits;
    }
} 