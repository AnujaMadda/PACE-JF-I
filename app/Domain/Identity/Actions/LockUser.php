<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;

class LockUser
{
    public function __construct(private readonly ForceLogout $forceLogout) {}

    public function handle(User $user, User $by, ?string $reason = null): void
    {
        $user->forceFill(['locked_by_admin' => true])->save();

        activity('users')->performedOn($user)->causedBy($by)->event('locked')
            ->withProperties(array_filter(['reason' => $reason]))->log('Account locked by admin');

        $this->forceLogout->handle($user, $by, 'locked');
    }
}
