<?php

namespace Database\Seeders;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * One demo user per role in Kenya, for LOCAL DEVELOPMENT ONLY.
 * Credentials are listed in README.md. Refuses to run in production.
 */
class DemoUsersSeeder extends Seeder
{
    /** email local part => [name, designation, role or null, home entity?] */
    private const USERS = [
        'superadmin' => ['Group Super Admin', 'Group IT', null, false],
        'ke.admin' => ['Kenya Entity Admin', 'IT Administrator', 'Entity Admin', true],
        'ke.requester' => ['Kenya Requester', 'Production Engineer', 'Requester', true],
        'ke.approver' => ['Kenya Approver', 'Plant Manager', 'Approver', true],
        'ke.budget' => ['Kenya Budget Approver', 'Finance Manager', 'Budget Approver', true],
        'ke.validator' => ['Kenya Validator', 'Accountant', 'Validator', true],
        'ke.validation' => ['Kenya Validation Approver', 'Chief Accountant', 'Validation Approver', true],
        'ke.coordinator' => ['Kenya Coordinator', 'Procurement Coordinator', 'Coordinator', true],
        'ke.purchasing' => ['Kenya Purchasing', 'Purchasing Officer', 'Purchasing', true],
        'ke.authoriser' => ['Kenya Additional Authoriser', 'Managing Director', 'Additional Authoriser', true],
        'ke.payments' => ['Kenya Payment Team', 'Payments Officer', 'Payment Team', true],
        'ke.payments.manager' => ['Kenya Payment Team Manager', 'Treasury Manager', 'Payment Team Manager', true],
        'ke.auditor' => ['Kenya Auditor', 'Internal Auditor', 'Viewer / Auditor', true],
        'ho.finance' => ['Head Office Finance', 'Group Finance Controller', 'Approver', false],
    ];

    public function run(CurrentEntity $currentEntity): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoUsersSeeder must never run in production.');
        }

        $kenya = Entity::query()->where('code', 'KE')->firstOrFail();
        $password = Hash::make((string) config('pace.demo_password'));

        $currentEntity->run($kenya, function (Entity $kenya) use ($password): void {
            foreach (self::USERS as $local => [$name, $designation, $roleName, $home]) {
                $user = User::query()->firstOrNew(['email' => "{$local}@jfi.lk"]);
                $user->fill(['name' => $name, 'designation' => $designation]);
                $user->forceFill([
                    'status' => UserStatus::Active,
                    'password' => $password,
                    'password_changed_at' => now(),
                    'activated_at' => now(),
                    'is_group_super_admin' => $local === 'superadmin',
                ])->save();

                $user->entities()->syncWithoutDetaching([$kenya->getKey() => ['is_home' => $home]]);

                if ($roleName !== null) {
                    $user->syncRoles([Role::findByName($roleName, 'web')]);
                }
            }
        });
    }
}
