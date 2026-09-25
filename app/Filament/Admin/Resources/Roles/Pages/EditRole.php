<?php

namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Filament\Admin\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @var list<string> */
    private array $permissionsBefore = [];

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['team_id'], $data['guard_name']);

        /** @var Role $role */
        $role = $this->getRecord();
        $this->permissionsBefore = $role->permissions()->pluck('name')->sort()->values()->all();

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Role $role */
        $role = $this->getRecord()->refresh();
        $after = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($after !== $this->permissionsBefore) {
            activity('roles')
                ->performedOn($role)
                ->causedBy(auth()->user())
                ->event('permissions_changed')
                ->withProperties(['entity_id' => $role->getAttribute('team_id'), 'old' => $this->permissionsBefore, 'new' => $after])
                ->log("Permissions changed for {$role->name}");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
