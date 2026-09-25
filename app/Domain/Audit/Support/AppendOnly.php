<?php

namespace App\Domain\Audit\Support;

use App\Domain\Audit\Exceptions\AppendOnlyViolation;

/**
 * Makes an Eloquent model insert-only: existing rows can never be updated or
 * deleted through the application. The database grants in docs/SECURITY.md
 * enforce the same rule below the application.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn ($model) => throw AppendOnlyViolation::for($model, 'update'));
        static::deleting(fn ($model) => throw AppendOnlyViolation::for($model, 'delete'));
    }
}
