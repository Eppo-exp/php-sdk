<?php

namespace Eppo\Exception;

use Exception;
use Throwable;

class InvalidConfigurationException extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
