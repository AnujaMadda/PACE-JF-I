<?php

namespace App\Policies;

use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;

/**
 * The audit log is read-only for everyone, including Group Super Admins
 * (the model itself refuses updates and deletes).
 */
class ActivityPolicy
{
    public function __construct(private readonly CurrentEntity $currentEntity) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('audit.view');
    }

    public function view(User $actor, Activity $activity): bool
    {
        return $actor->can('audit.view')
            && ($activity->entity_id === $this->currentEntity->id() || ($activity->entity_id === null && $actor->isGroupSuperAdmin()));
    }

    public function export(User $actor): bool
    {
        return $actor->can('audit.export');
    }
}
