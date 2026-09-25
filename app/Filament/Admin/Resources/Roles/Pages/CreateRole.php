<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Domain\Core\Support\CurrentEntity;
use App\Filament\Admin\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * The entity always comes from the session, never from the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = app(CurrentEntity::class)->require()->getKey();
        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterCreate(): void
    {
        $role = $this->getRecord();

        activity('roles')
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->event('role_created')
            ->withProperties(['entity_id' => $role->getAttribute('team_id')])
            ->log('Role created: '.$role->getAttribute('name'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
