<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Filament\Admin\Resources\Users\Actions\UserLifecycleActions;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        $entityId = app(CurrentEntity::class)->id();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'entities:id,code',
                'roles' => fn ($q) => $q->where('roles.team_id', $entityId),
            ]))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable()
                    ->description(fn (User $record) => $record->designation),
                TextColumn::make('email')->label(__('Email'))->searchable()->sortable()->copyable(),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (UserStatus $state) => $state->label())
                    ->color(fn (UserStatus $state) => $state->color()),
                IconColumn::make('locked')->label(__('Locked'))
                    ->state(fn (User $record) => $record->isLocked())
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')->trueColor('danger')
                    ->falseIcon('heroicon-o-minus')->falseColor('gray'),
                TextColumn::make('roles.name')->label(__('Roles here'))->badge()->separator(','),
                TextColumn::make('entities.code')->label(__('Entities'))->badge()->color('gray'),
                IconColumn::make('is_group_super_admin')->label(__('Group admin'))->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_login_at')->label(__('Last sign-in'))->dateTime()->sortable()->placeholder(__('Never')),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(UserStatus::options()),
                TernaryFilter::make('locked')
                    ->label(__('Locked'))
                    ->queries(
                        true: fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('locked_by_admin', true)->orWhere('locked_until', '>', now())),
                        false: fn (Builder $q) => $q->where('locked_by_admin', false)->where(fn (Builder $w) => $w->whereNull('locked_until')->orWhere('locked_until', '<=', now())),
                    ),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                UserLifecycleActions::group(),
            ]);
    }
}
