<?php

namespace App\Domain\Identity\Enums;

enum UserStatus: string
{
    case Invited = 'invited';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::Invited => __('Invited'),
            self::PendingApproval => __('Pending admin approval'),
            self::Active => __('Active'),
            self::Deactivated => __('Deactivated'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Invited => 'info',
            self::PendingApproval => 'warning',
            self::Active => 'success',
            self::Deactivated => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
