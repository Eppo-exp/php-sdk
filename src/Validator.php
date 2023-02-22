<?php

namespace Eppo;

use Eppo\Exception\InvalidArgumentException;

class Validator
{
    /**
     * @param string $value
     * @param string $errorMessage
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public static function validateNotBlank(string $value, string $errorMessage) {
        if (!$value || strlen($value) === 0) {
            throw new InvalidArgumentException($errorMessage);
        }
    }
}