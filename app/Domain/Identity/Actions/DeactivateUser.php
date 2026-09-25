<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;

class DeactivateUser
{
    public function __construct(private readonly ForceLogout $forceLogout) {}

    public function handle(User $user, User $by, ?string $reason = null): void
    {
        $user->forceFill(['status' => UserStatus::Deactivated, 'deactivated_at' => now()])->save();

        activity('users')->performedOn($user)->causedBy($by)->event('deactivated')
            ->withProperties(array_filter(['reason' => $reason]))->log('Account deactivated');

        $this->forceLogout->handle($user, $by, 'deactivated');
    }
}
