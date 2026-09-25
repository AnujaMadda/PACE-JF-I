<?php

namespace App\Filament\Admin\Resources\LoginEvents;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\LoginEvent;
use App\Filament\Admin\Resources\LoginEvents\Pages\ManageLoginEvents;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Sign-in history for the current entity (read-only).
 */
class LoginEventResource extends Resource
{
    protected static ?string $model = LoginEvent::class;

    protected static ?string $slug = 'login-history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Login history';

    protected static ?string $pluralModelLabel = 'login history';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('user')
            ->where('entity_id', app(CurrentEntity::class)->id() ?? 0);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('When'))->dateTime('d M Y, H:i:s')->sortable(),
                TextColumn::make('email')->label(__('Email'))->searchable(),
                TextColumn::make('user.name')->label(__('User'))->placeholder('—'),
                TextColumn::make('event')->label(__('Event'))->badge()
                    ->formatStateUsing(fn (LoginEventType $state) => $state->label())
                    ->color(fn (LoginEventType $state) => match ($state) {
                        LoginEventType::Success => 'success',
                        LoginEventType::Failed, LoginEventType::Locked => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('reason')->label(__('Detail'))->placeholder('—'),
                TextColumn::make('ip_address')->label(__('IP address'))->searchable(),
            ])
            ->filters([
                SelectFilter::make('event')->options(collect(LoginEventType::cases())->mapWithKeys(fn (LoginEventType $e) => [$e->value => $e->label()])->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLoginEvents::route('/'),
        ];
    }
}
