<?php

namespace App\Filament\Admin\Resources\Entities\Pages;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Actions\ProvisionEntityRoles;
use App\Filament\Admin\Resources\Entities\EntityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEntity extends CreateRecord
{
    protected static string $resource = EntityResource::class;

    protected function afterCreate(): void
    {
        /** @var Entity $entity */
        $entity = $this->getRecord();

        app(ProvisionEntityRoles::class)->handle($entity);
    }
}
