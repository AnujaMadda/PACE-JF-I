<?php

namespace App\Policies;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\LoginEvent;
use App\Domain\Identity\Models\User;

class LoginEventPolicy
{
    public function __construct(private readonly CurrentEntity $currentEntity) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('audit.view');
    }

    public function view(User $actor, LoginEvent $event): bool
    {
        return $actor->can('audit.view') && $event->entity_id === $this->currentEntity->id();
    }
}
