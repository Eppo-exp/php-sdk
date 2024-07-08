<?php

namespace Eppo\Bandits;

use Eppo\DTO\Bandit\Bandit;
use Eppo\Exception\InvalidConfigurationException;

interface IBandits
{
    /**
     * Get a bandit by key or null if it does not exist.
     * @param string $banditKey
     * @return ?Bandit
     * @throws InvalidConfigurationException
     */
    public function getBandit(string $banditKey): ?Bandit;
}
