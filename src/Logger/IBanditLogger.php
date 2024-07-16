<?php

namespace Eppo\Logger;

interface IBanditLogger extends LoggerInterface
{
    /**
     * Method used by EppoClient to log bandit action selection to the data warehouse.
     *
     * Logging Bandit selection events is crucial to the data pipeline driving decisions.
     */
    public function logBanditAction(BanditActionEvent $banditActionEvent): void;
}
