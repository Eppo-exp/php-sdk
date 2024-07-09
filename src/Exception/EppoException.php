<?php

namespace Eppo\Exception;

use Exception;

/**
 * Top-level class to ensure we're only catching Eppo exceptions in graceful mode.
 */
abstract class EppoException extends Exception
{
    public const BANDIT_EVALUATION_FAILED_NO_ACTIONS_PROVIDED = 21;
}
