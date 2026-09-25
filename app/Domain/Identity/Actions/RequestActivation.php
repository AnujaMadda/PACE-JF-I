<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Settings\Settings;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * The Sign Up page. If the email belongs to an invited user in an allowed
 * domain, an activation link is sent. If self-registration is on, an unknown
 * email from an allowed domain creates a "pending admin approval" account.
 * The caller always shows the same neutral message.
 */
class RequestActivation
{
    public function __construct(
        private readonly Settings $settings,
        private readonly IssueActivationLink $issueActivationLink,
    ) {}

    public function handle(string $email, ?string $name = null): void
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            if ($user->status === UserStatus::Invited && $this->domainAllowedFor($user, $email)) {
                $this->issueActivationLink->handle($user);
            }

            return;
        }

        if ($this->settings->bool('auth.self_registration') && $this->domainAllowedAnywhere($email)) {
            $user = new User(['name' => $name !== null && trim($name) !== '' ? trim($name) : Str::before($email, '@'), 'email' => $email]);
            $user->forceFill(['status' => UserStatus::PendingApproval])->save();

            activity('users')
                ->performedOn($user)
                ->event('self_registered')
                ->log('Self-registration awaiting admin approval');
        }
    }

    private function domainAllowedFor(User $user, string $email): bool
    {
        $entities = $user->entities()->where('entities.is_active', true)->get();

        // Group users with no entity yet are checked against every active entity.
        if ($entities->isEmpty()) {
            return $this->domainAllowedAnywhere($email);
        }

        return $entities->contains(fn (Entity $entity) => $entity->allowsEmail($email));
    }

    private function domainAllowedAnywhere(string $email): bool
    {
        return Entity::query()->active()->get()->contains(fn (Entity $entity) => $entity->allowsEmail($email));
    }
}
