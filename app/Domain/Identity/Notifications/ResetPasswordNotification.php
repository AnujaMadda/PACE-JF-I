<?php

namespace App\Domain\Identity\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Laravel's reset notification, queued and with PACE wording.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('Reset your PACE password'))
            ->line(__('We received a request to reset the password for your PACE account.'))
            ->action(__('Reset password'), $url)
            ->line(__('This link expires in :count minutes.', ['count' => $minutes]))
            ->line(__('If you did not ask for this, no action is needed.'))
            ->salutation(__('PACE — Approvals at PACE'));
    }
}
