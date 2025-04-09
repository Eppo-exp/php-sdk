<?php

namespace Eppo\DTO;

use Eppo\DTO\Bandit\Bandit;
use Eppo\Traits\StaticCreateSelf;
use Eppo\Traits\ToArray;

/**
 * Class BanditParametersResponse
 * @package Eppo\DTO
 *
 * @property array<string, Bandit> $bandits
 */
class BanditParametersResponse
{
    use StaticCreateSelf;
    use ToArray;

    public array $bandits;

    public function __construct(array $bandits = [])
    {
        $this->bandits = $bandits;
    }
}
