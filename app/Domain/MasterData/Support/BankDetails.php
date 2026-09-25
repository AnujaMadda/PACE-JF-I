<?php

namespace App\Domain\MasterData\Support;

use App\Domain\Identity\Models\User;

final class BankDetails
{
    public const FIELDS = [
        'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'bank_swift_code', 'bank_iban',
    ];

    public const PERMISSION = 'vendors.view_bank_details';

    /**
     * "****1234": keeps the last four characters so people can confirm the account.
     */
    public static function mask(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        return mb_strlen($value) <= 4 ? '****' : '****'.mb_substr($value, -4);
    }

    public static function canView(?User $user): bool
    {
        return $user !== null && $user->can(self::PERMISSION);
    }
}
