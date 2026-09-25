<?php

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Models\Currency;

/**
 * The currency list is group-wide: only Group Super Admins add or edit
 * currencies (through Gate::before). Entity admins choose which ones their
 * entity uses ("enable").
 */
class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('masterdata.view');
    }

    public function view(User $user, Currency $currency): bool
    {
        return $user->can('masterdata.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Currency $currency): bool
    {
        return false;
    }

    public function enable(User $user, Currency $currency): bool
    {
        return $user->can('masterdata.manage');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return false;
    }
}
