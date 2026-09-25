<?php

namespace App\Policies;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Models\User;

/**
 * Entities are managed by Group Super Admins only (granted through Gate::before).
 * Nobody deletes an entity: deactivate it instead.
 */
class EntityPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Entity $entity): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Entity $entity): bool
    {
        return false;
    }
}
