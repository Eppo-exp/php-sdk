<?php

namespace Eppo\DTO;

use Eppo\Traits\StaticFromJson;
use Eppo\Traits\ToArray;

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
