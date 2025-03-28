<?php

namespace Eppo\Exception;

use Throwable;

class EppoClientInitializationException extends EppoClientException
{
    public function __construct(
        string $message = "Client Initialization Failed",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
