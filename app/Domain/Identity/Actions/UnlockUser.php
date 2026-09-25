<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

class UnlockUser
{
    public function handle(User $user, User $by): void
    {
        $user->forceFill(['locked_by_admin' => false, 'locked_until' => null, 'failed_login_attempts' => 0])->save();

        activity('users')->performedOn($user)->causedBy($by)->event('unlocked')->log('Account unlocked');
    }
}
