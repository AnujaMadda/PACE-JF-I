<?php

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends the signed, time-limited link an invited user opens to set their password.
 */
class ActivationLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly int $minutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Activate your PACE account'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('An account has been created for you in PACE, the JF&I payment approval system.'))
            ->line(__('Use the button below to set your password. The link expires in :minutes minutes and can be used once.', ['minutes' => $this->minutes]))
            ->action(__('Set my password'), $this->url)
            ->line(__('If you did not expect this email, you can ignore it.'))
            ->salutation(__('PACE — Approvals at PACE'));
    }
}
