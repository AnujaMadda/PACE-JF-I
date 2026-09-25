<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Actions\SetGroupSuperAdmin;
use App\Domain\Identity\Models\User;
use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('User invited.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        $user = app(InviteUser::class)->handle(
            Arr::only($data, ['name', 'email', 'designation', 'department', 'line_manager_id']),
            app(CurrentEntity::class)->require(),
            array_values($data['role_ids'] ?? []),
            $actor,
            (bool) ($data['is_home'] ?? true),
            (bool) ($data['send_activation_link'] ?? true),
        );

        if ($actor->isGroupSuperAdmin() && ! empty($data['is_group_super_admin'])) {
            app(SetGroupSuperAdmin::class)->handle($user, true, $actor);
        }

        return $user;
    }
}
