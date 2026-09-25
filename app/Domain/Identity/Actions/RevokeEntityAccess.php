<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeEntityAccess
{
    public function __construct(private readonly SyncEntityRoles $syncEntityRoles) {}

    public function handle(User $user, Entity $entity, User $by): void
    {
        DB::transaction(function () use ($user, $entity, $by): void {
            $this->syncEntityRoles->handle($user, $entity, [], $by);
            $user->entities()->detach($entity);

            activity('users')
                ->performedOn($user)
                ->causedBy($by)
                ->event('entity_access_revoked')
                ->withProperties(['entity_id' => $entity->getKey()])
                ->log("Access to {$entity->code} revoked");
        });
    }
}
