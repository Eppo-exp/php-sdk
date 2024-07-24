<?php

namespace Eppo\Exception;

use Throwable;

class BanditEvaluationException extends EppoException
{
    public function __construct(
        string $message = "",
        int $code = EppoException::BANDIT_EVALUATION_FAILED_NO_ACTIONS_PROVIDED,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
