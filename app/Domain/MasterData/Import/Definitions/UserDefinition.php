<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Actions\InviteUser;
use App\Domain\Identity\Actions\SyncEntityRoles;
use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Import\ImportDefinition;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Bulk user set-up for the current entity. New emails become invited users;
 * existing people are given access and the listed roles here. Group Super
 * Admins in the file are left untouched.
 */
class UserDefinition extends ImportDefinition
{
    public function __construct(
        private readonly InviteUser $inviteUser,
        private readonly SyncEntityRoles $syncEntityRoles,
    ) {}

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return __('Users');
    }

    public function importPermission(): string
    {
        return 'users.import';
    }

    public function exportPermission(): string
    {
        return 'users.view';
    }

    public function rowKey(array $row): ?string
    {
        return isset($row['email']) ? strtolower((string) $row['email']) : null;
    }

    protected function columns(): array
    {
        return [
            Column::make('name')->rules(['required', 'string', 'max:255'])->example('Grace Wanjiru'),
            Column::make('email')->rules(['required', 'email', 'max:255'])->example('grace.wanjiru@jfi.lk'),
            Column::make('designation')->rules(['nullable', 'string', 'max:255'])->example('Maintenance Engineer'),
            Column::make('department')->rules(['nullable', 'string', 'max:255'])->example('Engineering'),
            Column::make('roles')->rules(['nullable', 'string'])->example('Requester', 'Role names in this entity, separated by commas'),
            Column::make('home_entity', 'bool')->rules(['nullable', 'boolean'])->example('yes', 'yes if this entity is the person\'s operating entity'),
        ];
    }

    public function extraRules(array $row, ImportContext $context): array
    {
        $entity = $context->entity;

        return [
            'email' => [function (string $attribute, mixed $value, \Closure $fail) use ($entity): void {
                if (! $entity->allowsEmail((string) $value)) {
                    $fail(__('The email domain is not allowed for :entity.', ['entity' => $entity->name]));
                }

                if (User::query()->where('email', Str::lower((string) $value))->where('is_group_super_admin', true)->exists()) {
                    $fail(__('Group Super Admins cannot be changed by import.'));
                }
            }],
            'roles' => [function (string $attribute, mixed $value, \Closure $fail) use ($entity): void {
                $unknown = array_diff($this->roleNames($value), Role::query()->where('team_id', $entity->getKey())->pluck('name')->all());

                if ($unknown !== []) {
                    $fail(__('Unknown role(s) in :entity: :roles.', ['entity' => $entity->code, 'roles' => implode(', ', $unknown)]));
                }
            }],
        ];
    }

    public function persist(array $row, ImportContext $context): string
    {
        $roleIds = Role::query()->where('team_id', $context->entity->getKey())
            ->whereIn('name', $this->roleNames($row['roles']))->pluck('id')->all();
        $details = ['name' => $row['name'], 'designation' => $row['designation'], 'department' => $row['department']];
        $user = User::query()->where('email', Str::lower((string) $row['email']))->first();

        if ($user === null) {
            $this->inviteUser->handle(
                [...$details, 'email' => (string) $row['email']],
                $context->entity,
                $roleIds,
                $context->user,
                (bool) ($row['home_entity'] ?? true),
                (bool) ($context->options['send_activation_links'] ?? false),
            );

            return 'created';
        }

        $user->fill(array_filter($details, fn ($v) => $v !== null))->save();
        $this->syncEntityRoles->handle($user, $context->entity, $roleIds, $context->user);

        if ($row['home_entity'] !== null) {
            $user->entities()->updateExistingPivot($context->entity->getKey(), ['is_home' => (bool) $row['home_entity']]);
        }

        return 'updated';
    }

    public function exportRows(User $user): iterable
    {
        $entity = app(CurrentEntity::class)->require();

        $users = User::query()
            ->whereHas('entities', fn ($q) => $q->whereKey($entity->getKey()))
            ->with(['entities' => fn ($q) => $q->whereKey($entity->getKey()), 'roles' => fn ($q) => $q->where('roles.team_id', $entity->getKey())])
            ->orderBy('name')
            ->get();

        foreach ($users as $person) {
            yield [
                'name' => $person->name,
                'email' => $person->email,
                'designation' => $person->designation,
                'department' => $person->department,
                'roles' => $person->roles->pluck('name')->sort()->implode(', '),
                'home_entity' => (bool) data_get($person->entities->first(), 'pivot.is_home'),
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function roleNames(mixed $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}
