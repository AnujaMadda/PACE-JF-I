<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;

/**
 * Brings a deactivated user back. Users who never set a password return to
 * "Invited" and need a new activation link.
 */
class ReactivateUser
{
    public function handle(User $user, User $by): void
    {
        $user->forceFill([
            'status' => $user->password === null ? UserStatus::Invited : UserStatus::Active,
            'deactivated_at' => null,
        ])->save();

        activity('users')->performedOn($user)->causedBy($by)->event('reactivated')->log('Account reactivated');
    }
}
