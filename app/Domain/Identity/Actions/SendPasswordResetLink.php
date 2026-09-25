<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Sends a reset link to an active user. Used by "Forgot password" and by
 * admins (who never see or set passwords). Unknown or inactive emails are
 * ignored silently so the caller can show a neutral message.
 */
class SendPasswordResetLink
{
    public function handle(string $email, ?User $by = null): void
    {
        $user = User::query()->where('email', Str::lower(trim($email)))->first();

        if ($user === null || ! $user->isActive() || $user->auth_provider !== 'local') {
            return;
        }

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        activity('auth')
            ->performedOn($user)
            ->causedBy($by)
            ->event('password_reset_requested')
            ->withProperties(['by_admin' => $by !== null, 'status' => $status])
            ->log('Password reset link requested');
    }
}
