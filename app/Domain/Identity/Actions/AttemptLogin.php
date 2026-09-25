<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Settings\Settings;
use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Email + password + entity login. Returns true on success. Every failure is
 * reported to the caller the same way so the response never reveals whether
 * the email exists, the password was right, or the account is locked; the
 * real reason is kept in login_events.
 */
class AttemptLogin
{
    /** A valid bcrypt hash used to keep timing similar when the email is unknown. */
    private const DUMMY_HASH = '$2y$12$9QgDfrSxzGhZSBI61av3z.m6h65sV3iNmo0hl/7AS4tCKzRf2Uzmq';

    public function __construct(
        private readonly Settings $settings,
        private readonly RecordLoginEvent $recordLoginEvent,
    ) {}

    public function handle(Request $request, string $email, #[\SensitiveParameter] string $password, int $entityId): bool
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();
        $entity = Entity::query()->active()->find($entityId);

        if ($user === null) {
            Hash::check($password, self::DUMMY_HASH);

            return $this->fail($request, $email, null, $entity, 'unknown_email');
        }

        if ($user->isLocked()) {
            return $this->fail($request, $email, $user, $entity, 'locked');
        }

        if ($user->password === null || ! Hash::check($password, $user->password)) {
            $this->registerFailedAttempt($request, $user, $entity);

            return $this->fail($request, $email, $user, $entity, 'bad_password');
        }

        if (! $user->isActive()) {
            return $this->fail($request, $email, $user, $entity, 'status_'.$user->status->value);
        }

        if ($entity === null || ! $user->canAccessEntity($entity)) {
            return $this->fail($request, $email, $user, $entity, 'no_entity_access');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('entity_id', $entity->getKey());

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->saveQuietly();

        $this->recordLoginEvent->handle($request, LoginEventType::Success, $email, $user, $entity->getKey());

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event(LoginEventType::Success->value)
            ->withProperties(['entity_id' => $entity->getKey()])
            ->log('Signed in');

        return true;
    }

    private function registerFailedAttempt(Request $request, User $user, ?Entity $entity): void
    {
        $max = $this->settings->int('security.lockout_max_attempts');
        $minutes = $this->settings->int('security.lockout_minutes');

        // A lockout that has already expired starts a fresh count.
        $attempts = ($user->locked_until !== null ? 0 : $user->failed_login_attempts) + 1;

        if ($attempts < $max) {
            $user->forceFill(['failed_login_attempts' => $attempts, 'locked_until' => null])->saveQuietly();

            return;
        }

        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => now()->addMinutes($minutes)])->saveQuietly();

        $this->recordLoginEvent->handle($request, LoginEventType::Locked, $user->email, $user, $entity?->getKey(), "after_{$max}_failures");

        activity('auth')
            ->performedOn($user)
            ->event(LoginEventType::Locked->value)
            ->withProperties(['minutes' => $minutes, 'attempts' => $max, 'entity_id' => $entity?->getKey()])
            ->log('Account locked after repeated failed sign-in attempts');
    }

    private function fail(Request $request, string $email, ?User $user, ?Entity $entity, string $reason): bool
    {
        $this->recordLoginEvent->handle($request, LoginEventType::Failed, $email, $user, $entity?->getKey(), $reason);

        return false;
    }
}
