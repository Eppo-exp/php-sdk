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

}

