<?php


namespace Eppo\Exception;

use Exception;
use Throwable;

class EppoClientInitializationException extends Exception
{

    public function __construct(string $message = "", Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}