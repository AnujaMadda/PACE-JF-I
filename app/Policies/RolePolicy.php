<?php

namespace App\Policies;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function __construct(private readonly CurrentEntity $currentEntity) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.manage');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->can('roles.manage') && $this->inCurrentEntity($role);
    }

    public function create(User $actor): bool
    {
        return $actor->can('roles.manage');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can('roles.manage') && $this->inCurrentEntity($role);
    }

    /**
     * Only unused roles can be removed. Phase 4 also blocks roles used by workflow steps.
     */
    public function delete(User $actor, Role $role): bool
    {
        return $this->update($actor, $role) && $role->users()->doesntExist();
    }

    private function inCurrentEntity(Role $role): bool
    {
        return (int) $role->getAttribute('team_id') === $this->currentEntity->id();
    }
}
