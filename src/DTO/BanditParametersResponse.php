<?php

namespace Eppo\DTO;

use Eppo\Traits\StaticFromArray;
use Eppo\Traits\ToArray;

class BanditParametersResponse
{
    use StaticFromArray;
    use ToArray;

    public array $bandits;

    public function __construct(array $bandits = [])
    {
        $this->bandits = $bandits;
    }
}
