<?php

namespace Eppo\Logger;

interface LoggerInterface
{
    /**
     * Method used by EppoClient to log assignments to the data warehouse.
     * This method will be passed all the assignment information, and, based on implementation,
     * try to log this information.
     */
    public function logAssignment(
        string $experiment,
        string $variation,
        string $subject,
        string $timestamp,
        array $subjectAttributes = [],
        ?string $allocation = null,
        ?string $featureFlag = null
    ): void;
}
