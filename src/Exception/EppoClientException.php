<?php

namespace Eppo\Exception;

use Throwable;

class EppoClientException extends EppoException
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param Throwable|null $previous
     * @param int|null $code
     * @return self
     */
    public static function from(Throwable $previous = null, int $code = null): self
    {
        return new self($previous->getMessage(), $code ?? $previous->getCode(), $previous);
    }
}
