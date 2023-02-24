<?php

namespace Eppo\Logger;

interface LoggerInterface
{
    /**
     * Method used by EppoClient to log assignments.
     * Will pass to this method all the assignment information, and, based on implementation,
     * try to log this information.
     *
     * @param string $experiment
     * @param string $variation
     * @param string $subject
     * @param string $timestamp
     * @param array $subjectAttributes
     *
     * @return void
     */
    public function logAssignment(
        string $experiment,
        string $variation,
        string $subject,
        string $timestamp,
        array $subjectAttributes = []
    ): void;
}
