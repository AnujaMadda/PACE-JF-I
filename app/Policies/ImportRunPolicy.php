<?php

namespace App\Policies;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Import\ImportRun;

class ImportRunPolicy
{
    public function __construct(private readonly CurrentEntity $currentEntity) {}

    public function viewAny(User $user): bool
    {
        return $user->can('masterdata.import') || $user->can('users.import');
    }

    public function view(User $user, ImportRun $run): bool
    {
        return $run->entity_id === $this->currentEntity->id()
            && ($run->created_by === $user->getKey() || $user->can($run->definition()->importPermission()));
    }
}
