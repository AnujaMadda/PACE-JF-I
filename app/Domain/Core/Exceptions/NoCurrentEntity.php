<?php

namespace App\Domain\Core\Exceptions;

use RuntimeException;

class NoCurrentEntity extends RuntimeException
{
    public function __construct(string $message = 'No current entity is set. Wrap the work in CurrentEntity::run().')
    {
        parent::__construct($message);
    }
}
