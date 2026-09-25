<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Core\Settings\Settings;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\ActivationLinkNotification;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Emails an invited user a signed, time-limited link to set their password.
 * Issuing a new link invalidates earlier ones (the link carries the issue time).
 */
class IssueActivationLink
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(User $user, ?User $by = null): void
    {
        if ($user->status !== UserStatus::Invited) {
            throw new InvalidArgumentException('Activation links can only be sent to invited users.');
        }

        $minutes = $this->settings->int('security.activation_link_minutes');
        $issuedAt = now();

        $user->forceFill(['invitation_sent_at' => $issuedAt])->saveQuietly();

        $url = URL::temporarySignedRoute('activation.show', $issuedAt->copy()->addMinutes($minutes), [
            'user' => $user->getKey(),
            'issued' => $issuedAt->getTimestamp(),
        ]);

        $user->notify(new ActivationLinkNotification($url, $minutes));

        activity('users')
            ->performedOn($user)
            ->causedBy($by)
            ->event('activation_link_sent')
            ->log('Activation link sent');
    }

    /**
     * Whether a link issued at the given time is the latest one for this user.
     */
    public static function isCurrent(User $user, int $issued): bool
    {
        return $user->status === UserStatus::Invited
            && $user->invitation_sent_at !== null
            && $user->invitation_sent_at->getTimestamp() === $issued;
    }
}
