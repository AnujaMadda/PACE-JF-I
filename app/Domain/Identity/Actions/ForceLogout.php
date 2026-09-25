<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends every session the user has (database session driver) and rotates the
 * remember token.
 */
class ForceLogout
{
    public function handle(User $user, ?User $by = null, string $reason = 'admin'): int
    {
        $ended = DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        activity('users')
            ->performedOn($user)
            ->causedBy($by)
            ->event('forced_logout')
            ->withProperties(['sessions_ended' => $ended, 'reason' => $reason])
            ->log('All sessions ended');

        return $ended;
    }
}
