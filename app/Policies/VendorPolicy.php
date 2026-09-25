<?php

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Support\BankDetails;
use Illuminate\Database\Eloquent\Model;

/**
 * Vendors follow the master data rules, and payment roles (who may see bank
 * details) may also open a vendor to maintain its bank details. The form
 * locks the other fields for them.
 */
class VendorPolicy extends MasterDataPolicy
{
    public function update(User $user, Model $record): bool
    {
        return parent::update($user, $record)
            || ($user->can(BankDetails::PERMISSION) && parent::view($user, $record));
    }
}
