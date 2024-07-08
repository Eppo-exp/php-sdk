<?php

namespace Eppo\Logger;

interface LoggerInterface
{
    /**
     * Method used by EppoClient to log assignments to the data warehouse.
     * This method will be passed all the assignment information, and, based on implementation,
     * try to log this information.
     */
    public function logAssignment(AssignmentEvent $assignmentEvent): void;

    /**
     * Method used by EppoClient to log bandit action selection to the data warehouse.
     *
     * Logging Bandit selection events is crucial to the data pipeline driving decisions.
     */
    public function logBanditAction(BanditActionEvent $banditActionEvent): void;
}
