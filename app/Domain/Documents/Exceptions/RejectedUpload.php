<?php

namespace App\Domain\Documents\Exceptions;

use Illuminate\Validation\ValidationException;

class RejectedUpload
{
    public static function because(string $message, string $field = 'file'): ValidationException
    {
        return ValidationException::withMessages([$field => $message]);
    }
}
