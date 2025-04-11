<?php

namespace Eppo\DTO;

use Eppo\DTO\Bandit\Bandit;
use Eppo\Traits\StaticFromJson;
use Eppo\Traits\ToArray;

/**
 * Class BanditParametersResponse
 * @package Eppo\DTO
 *
 * @property array<string, Bandit> $bandits
 */
class BanditParametersResponse
{
    use StaticFromJson;
    use ToArray;

    public array $bandits;

    public function __construct(array $bandits = [])
    {
        $this->bandits = $bandits;
    }
}
