<?php

namespace App\Policies;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One policy for every per-entity master data model. Records are never
 * deleted (deactivate instead), so there is no delete ability.
 */
class MasterDataPolicy
{
    public function __construct(private readonly CurrentEntity $currentEntity) {}

    public function viewAny(User $user): bool
    {
        return $user->can('masterdata.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('masterdata.view') && $this->inCurrentEntity($record);
    }

    public function create(User $user): bool
    {
        return $user->can('masterdata.manage');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('masterdata.manage') && $this->inCurrentEntity($record);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function inCurrentEntity(Model $record): bool
    {
        return (int) $record->getAttribute('entity_id') === $this->currentEntity->id();
    }
}
