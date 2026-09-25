<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Actions\SetGroupSuperAdmin;
use App\Domain\Identity\Actions\SyncEntityRoles;
use App\Domain\Identity\Models\User;
use App\Filament\Admin\Resources\Users\Actions\UserLifecycleActions;
use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [UserLifecycleActions::group()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();
        $entityId = app(CurrentEntity::class)->id();

        $data['role_ids'] = $user->roles()->where('roles.team_id', $entityId)->pluck('roles.id')->all();
        $data['is_home'] = (bool) $user->entities()->whereKey($entityId)->first()?->pivot?->getAttribute('is_home');
        $data['is_group_super_admin'] = $user->isGroupSuperAdmin();

        return $data;
    }

    /**
     * @param  User  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $entity = app(CurrentEntity::class)->require();

        DB::transaction(function () use ($record, $data, $actor, $entity): void {
            $record->fill(Arr::only($data, ['name', 'email', 'designation', 'department', 'line_manager_id']))->save();

            app(SyncEntityRoles::class)->handle($record, $entity, array_values($data['role_ids'] ?? []), $actor);
            $record->entities()->updateExistingPivot($entity->getKey(), ['is_home' => (bool) ($data['is_home'] ?? false)]);

            if ($actor->isGroupSuperAdmin() && $actor->isNot($record) && array_key_exists('is_group_super_admin', $data)) {
                app(SetGroupSuperAdmin::class)->handle($record, (bool) $data['is_group_super_admin'], $actor);
            }
        });

        return $record;
    }
}
