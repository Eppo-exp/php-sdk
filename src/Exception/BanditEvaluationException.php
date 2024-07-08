<?php

namespace Eppo\Exception;

use Throwable;

class BanditEvaluationException extends EppoException
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
