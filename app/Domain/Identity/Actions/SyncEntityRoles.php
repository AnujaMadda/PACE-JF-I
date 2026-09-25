<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Sets a user's roles inside one entity, grants entity access if missing,
 * and audits the before and after role lists. Only roles belonging to that
 * entity can be assigned.
 */
class SyncEntityRoles
{
    public function __construct(private readonly CurrentEntity $currentEntity) {}

    /**
     * @param  list<int|string>  $roleIds
     */
    public function handle(User $user, Entity $entity, array $roleIds, User $by): void
    {
        $this->currentEntity->run($entity, function (Entity $entity) use ($user, $roleIds, $by): void {
            $roles = Role::query()
                ->where('team_id', $entity->getKey())
                ->whereIn('id', $roleIds)
                ->get();

            $user->unsetRelation('roles');
            $before = $user->roles()->pluck('name')->sort()->values()->all();

            if (! $user->entities()->whereKey($entity->getKey())->exists()) {
                $user->entities()->attach($entity, ['is_home' => false, 'granted_by' => $by->getKey()]);
            }

            $user->syncRoles($roles);
            $user->unsetRelation('roles');

            $after = $roles->pluck('name')->sort()->values()->all();

            if ($before !== $after) {
                activity('users')
                    ->performedOn($user)
                    ->causedBy($by)
                    ->event('roles_changed')
                    ->withProperties(['entity_id' => $entity->getKey(), 'old' => $before, 'new' => $after])
                    ->log('Roles changed');
            }
        });
    }
}
