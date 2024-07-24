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
    public static function validateNotBlank(string $value, string $errorMessage): void
    {
        if (!$value || strlen($value) === 0) {
            throw new InvalidArgumentException($errorMessage);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function validateNotEqual(string $input, string $reserved, string $errorMessage): void
    {
        if ($input === $reserved) {
            throw new InvalidArgumentException($errorMessage);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function validateNotEmpty(array $actionsWithContexts, string $string): void
    {
        if (empty($actionsWithContexts)) {
            throw new InvalidArgumentException($string);
        }
    }
}
