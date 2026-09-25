<?php

namespace App\Domain\Audit\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AppendOnlyViolation extends LogicException
{
    public static function for(Model $model, string $operation): self
    {
        return new self(sprintf('%s records are append-only; %s is not allowed.', class_basename($model), $operation));
    }
}
