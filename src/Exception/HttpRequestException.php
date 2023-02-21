<?php

namespace Eppo\Exception;

use Exception;
use Throwable;

class HttpRequestException extends Exception
{
    /** @var bool */
    public $isRecoverable = false;

    /**
     * @param string $message
     * @param int $code
     * @param bool $isRecoverable
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, $isRecoverable = false, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->isRecoverable = $isRecoverable;
    }
}