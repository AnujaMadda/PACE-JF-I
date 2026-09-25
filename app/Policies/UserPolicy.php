<?php

namespace App\Policies;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;

/**
 * Entity admins manage users who have access to the current entity. Group
 * Super Admins pass every check through Gate::before. Users are never
 * deleted: they are deactivated.
 */
class UserPolicy
{
    public function __construct(private readonly CurrentEntity $currentEntity) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.view') && $this->inCurrentEntity($target);
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.manage')
            && $this->inCurrentEntity($target)
            && ! $target->isGroupSuperAdmin();
    }

    /**
     * Lock, deactivate, force logout or revoke access. Never on yourself.
     */
    public function restrict(User $actor, User $target): bool
    {
        return $actor->isNot($target) && $this->update($actor, $target);
    }

    /**
     * Global status changes (deactivate / reactivate) for a user who also
     * works in other entities belong to a Group Super Admin; an entity admin
     * revokes access to their own entity instead.
     */
    public function changeStatus(User $actor, User $target): bool
    {
        return $this->restrict($actor, $target)
            && $target->entities()->where('entities.id', '!=', $this->currentEntity->id())->doesntExist();
    }

    public function delete(User $actor, User $target): bool
    {
        return false;
    }

    public function inCurrentEntity(User $target): bool
    {
        $entity = $this->currentEntity->get();

        if ($entity === null) {
            return false;
        }

        if ($target->status === UserStatus::PendingApproval && $target->entities()->doesntExist()) {
            return $entity->allowsEmail($target->email);
        }

        return $target->entities()->whereKey($entity->getKey())->exists();
    }
}
