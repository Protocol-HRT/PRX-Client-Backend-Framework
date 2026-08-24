<?php

namespace App\Actions\Exceptions;

use RuntimeException;

class ActionException extends RuntimeException
{
    public static function failed(string $message, int $code = 422): self
    {
        return new self($message, $code);
    }
}
