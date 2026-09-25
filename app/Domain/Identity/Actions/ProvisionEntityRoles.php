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

    public function syncPermissions(): void
    {
        /** @var array<string, string> $catalogue */
        $catalogue = config('pace.permissions', []);

        foreach (array_keys($catalogue) as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
