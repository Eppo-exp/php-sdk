<?php

namespace Eppo\Config;

use Eppo\DTO\Bandit\Bandit;

interface IBandits
{

    /**
     * Gets the Bandit models.
     * @param string $banditKey
     * @return Bandit[]
     */
    public function getBandit(string $banditKey): array;
}