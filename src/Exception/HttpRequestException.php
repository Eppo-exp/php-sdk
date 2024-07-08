<?php

namespace Eppo\Exception;

use Throwable;

class HttpRequestException extends EppoException
{
    public bool $isRecoverable = false;

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
