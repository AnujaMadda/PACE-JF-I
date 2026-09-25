<?php

namespace App\Domain\Identity\Enums;

enum LoginEventType: string
{
    case Success = 'login_success';
    case Failed = 'login_failed';
    case Locked = 'account_locked';
    case Logout = 'logout';
    case IdleTimeout = 'idle_timeout';
    case ForcedLogout = 'forced_logout';

    public function label(): string
    {
        return match ($this) {
            self::Success => __('Login succeeded'),
            self::Failed => __('Login failed'),
            self::Locked => __('Account locked'),
            self::Logout => __('Logged out'),
            self::IdleTimeout => __('Session timed out'),
            self::ForcedLogout => __('Forced logout'),
        };
    }
}
