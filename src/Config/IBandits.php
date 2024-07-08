<?php

namespace Eppo\Config;

use Eppo\DTO\Bandit\Bandit;

interface IBandits
{
    /**
     * Gets a Bandit by key
     *
     * @param string $banditKey
     * @return ?Bandit
     */
    public function getBandit(string $banditKey): ?Bandit;
}
