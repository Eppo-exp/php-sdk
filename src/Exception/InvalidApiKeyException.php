<?php

namespace Eppo\Exception;

use Throwable;

class InvalidApiKeyException extends EppoException
{
    public function __construct(
        string $message = "[Eppo SDK]: Invalid API Key",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
