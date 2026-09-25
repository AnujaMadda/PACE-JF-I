<?php

namespace App\Domain\Core\Exceptions;

use RuntimeException;

class EntityMismatch extends RuntimeException
{
    public static function onCreate(string $model, int $given, int $current): self
    {
        return new self(sprintf('Refusing to create %s for entity %d while working in entity %d.', $model, $given, $current));
    }

    public static function onUpdate(string $model): self
    {
        return new self(sprintf('The entity of an existing %s cannot be changed.', $model));
    }
}
