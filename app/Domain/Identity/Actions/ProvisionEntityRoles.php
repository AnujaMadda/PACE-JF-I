<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Core\Models\Entity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes sure the permission catalogue exists and that an entity has the
 * default role set. Idempotent: existing roles keep whatever permissions an
 * admin has given them; only missing roles are created.
 */
class ProvisionEntityRoles
{
    public function handle(Entity $entity): void
    {
        $this->syncPermissions();

        /** @var array<string, list<string>> $defaults */
        $defaults = config('pace.default_roles', []);

        foreach ($defaults as $name => $permissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
                'team_id' => $entity->getKey(),
            ]);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Roll out newly added catalogue permissions to the default roles of every
     * existing entity, once (called from a data migration). Only the listed
     * permissions are granted, so anything an admin removed earlier stays removed.
     *
     * @param  list<string>  $newPermissions
     */
    public function grantNewDefaults(array $newPermissions): void
    {
        $this->syncPermissions();

        /** @var array<string, list<string>> $defaults */
        $defaults = config('pace.default_roles', []);

        foreach (Entity::query()->get() as $entity) {
            foreach ($defaults as $roleName => $permissions) {
                $grant = array_values(array_intersect($permissions, $newPermissions));

                if ($grant === []) {
                    continue;
                }

                $role = Role::query()->where('team_id', $entity->getKey())->where('name', $roleName)->first();
                $role?->givePermissionTo($grant);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function syncPermissions(): void
    {
        /** @var array<string, string> $catalogue */
        $catalogue = config('pace.permissions', []);

        foreach (array_keys($catalogue) as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
