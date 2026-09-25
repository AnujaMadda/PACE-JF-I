<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Stores a new (already validated) password, keeps the history used for the
 * reuse check, and clears automatic lockouts. Admin locks stay in place.
 */
class SetPassword
{
    public function __construct(private readonly PasswordPolicy $policy) {}

    public function handle(User $user, #[\SensitiveParameter] string $password, string $reason): void
    {
        DB::transaction(function () use ($user, $password, $reason): void {
            if ($user->password !== null) {
                $user->passwordHistories()->create(['password' => $user->password]);
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();

            // The current password is checked separately, so N-1 older hashes cover "the last N".
            $keep = max(0, $this->policy->historyCount() - 1);
            $user->passwordHistories()
                ->whereNotIn('id', $user->passwordHistories()->latest('id')->limit($keep)->pluck('id'))
                ->delete();

            activity('auth')
                ->performedOn($user)
                ->causedBy(auth()->user() ?? $user)
                ->event('password_changed')
                ->withProperties(['reason' => $reason])
                ->log('Password changed');
        });
    }
}
