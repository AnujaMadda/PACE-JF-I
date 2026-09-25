<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class SetGroupSuperAdmin
{
    public function handle(User $user, bool $grant, User $by): void
    {
        if (! $by->isGroupSuperAdmin()) {
            throw new AuthorizationException;
        }

        if ($user->is_group_super_admin === $grant) {
            return;
        }

        $user->forceFill(['is_group_super_admin' => $grant])->save();

        activity('users')
            ->performedOn($user)
            ->causedBy($by)
            ->event($grant ? 'super_admin_granted' : 'super_admin_revoked')
            ->log($grant ? 'Group Super Admin granted' : 'Group Super Admin revoked');
    }
}
