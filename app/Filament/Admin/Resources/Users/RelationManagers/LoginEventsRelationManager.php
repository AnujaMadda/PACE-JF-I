<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Domain\Identity\Enums\LoginEventType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only sign-in history for one user (all entities: it is the user's own record).
 */
class LoginEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'loginEvents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Login history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('When'))->dateTime()->sortable(),
                TextColumn::make('event')->label(__('Event'))->badge()
                    ->formatStateUsing(fn (LoginEventType $state) => $state->label())
                    ->color(fn (LoginEventType $state) => match ($state) {
                        LoginEventType::Success => 'success',
                        LoginEventType::Failed, LoginEventType::Locked => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('reason')->label(__('Detail'))->placeholder('—'),
                TextColumn::make('entity.code')->label(__('Entity'))->placeholder('—'),
                TextColumn::make('ip_address')->label(__('IP address')),
                TextColumn::make('user_agent')->label(__('Browser'))->limit(40)->tooltip(fn ($state) => $state)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->options(collect(LoginEventType::cases())->mapWithKeys(fn (LoginEventType $e) => [$e->value => $e->label()])->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
