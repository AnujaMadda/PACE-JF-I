<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $entity = app(CurrentEntity::class)->require();

        return $schema
            ->components([
                Section::make(__('Details'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label(__('Full name'))->required()->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Company email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($entity): void {
                                if (! $entity->allowsEmail((string) $value)) {
                                    $fail(__('Use an email on an allowed domain for :entity (:domains).', [
                                        'entity' => $entity->name,
                                        'domains' => implode(', ', $entity->allowed_email_domains),
                                    ]));
                                }
                            }),
                        TextInput::make('designation')->label(__('Designation'))->maxLength(255),
                        TextInput::make('department')
                            ->label(__('Department'))
                            ->maxLength(255)
                            ->helperText(__('Becomes a pick-list when department master data is added.')),
                        Select::make('line_manager_id')
                            ->label(__('Line manager'))
                            ->relationship(
                                'lineManager',
                                'name',
                                fn (Builder $query, ?User $record) => $query
                                    ->whereHas('entities', fn (Builder $e) => $e->whereKey($entity->getKey()))
                                    ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey())),
                            )
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make(__('Access in :entity', ['entity' => $entity->name]))
                    ->description(__('Roles apply only in this entity. Switch entity to manage access elsewhere.'))
                    ->schema([
                        CheckboxList::make('role_ids')
                            ->label(__('Roles'))
                            ->options(fn () => Role::query()
                                ->where('team_id', $entity->getKey())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->columns(3)
                            ->bulkToggleable(),
                        Toggle::make('is_home')
                            ->label(__(':entity is this person\'s home (operating) entity', ['entity' => $entity->code]))
                            ->helperText(__('Requesters belong to their operating entity. Leave off for head-office users granted access.'))
                            ->default(true),
                    ]),

                Section::make(__('Group access'))
                    ->visible(fn (): bool => (bool) auth()->user()?->isGroupSuperAdmin())
                    ->schema([
                        Toggle::make('is_group_super_admin')
                            ->label(__('Group Super Admin'))
                            ->helperText(__('Full administration across every entity. Grant sparingly.')),
                    ]),

                Section::make(__('Invitation'))
                    ->visibleOn('create')
                    ->schema([
                        Toggle::make('send_activation_link')
                            ->label(__('Email the activation link now'))
                            ->helperText(__('Otherwise the person activates from the Sign Up page when ready.'))
                            ->default(true),
                    ]),
            ]);
    }
}
