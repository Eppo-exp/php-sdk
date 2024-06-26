<?php


namespace Eppo\Exception;


use Exception;
use Throwable;

class EppoClientException extends Exception
{
    public function __construct(string $message = "", int $code =0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function From(Throwable $previous = null, int $code = 0): self
    {
        return new self($previous->getMessage(), $code,  $previous);
    }
}
