<?php

namespace App\Filament\Admin\Resources\Currencies;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\MasterData\Models\Currency;
use App\Filament\Admin\Resources\Currencies\Pages\ManageCurrencies;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The group-wide currency list. Group Super Admins maintain it; entity
 * admins switch currencies on or off for their entity. The base currency is
 * always on.
 */
class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('code')->label(__('ISO code'))->required()->regex('/^[A-Z]{3}$/')->unique(ignoreRecord: true)->disabledOn('edit'),
            TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
            TextInput::make('decimals')->label(__('Decimals'))->integer()->minValue(0)->maxValue(3)->default(2)->required(),
            Toggle::make('is_active')->label(__('Available'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $entity = app(CurrentEntity::class)->require();
        $enabled = fn (Currency $record): bool => $record->code === $entity->base_currency
            || $record->entities()->whereKey($entity->getKey())->exists();

        return $table
            ->columns([
                TextColumn::make('code')->label(__('Code'))->searchable()->sortable()->weight('medium'),
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('decimals')->label(__('Decimals')),
                IconColumn::make('enabled_here')->label(__('Used in :entity', ['entity' => $entity->code]))
                    ->state($enabled)->boolean(),
                IconColumn::make('is_active')->label(__('Available'))->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('enabled')
                    ->label(__('Used in :entity', ['entity' => $entity->code]))
                    ->queries(
                        true: fn (Builder $q) => $q->whereIn('code', array_keys(Currency::optionsFor($entity))),
                        false: fn (Builder $q) => $q->where('code', '!=', $entity->base_currency)->whereDoesntHave('entities', fn (Builder $e) => $e->whereKey($entity->getKey())),
                    ),
            ])
            ->defaultSort('code')
            ->recordActions([
                Action::make('toggleEnabled')
                    ->label(fn (Currency $record) => $enabled($record) ? __('Stop using') : __('Use in :entity', ['entity' => $entity->code]))
                    ->icon(fn (Currency $record) => $enabled($record) ? Heroicon::OutlinedMinusCircle : Heroicon::OutlinedPlusCircle)
                    ->color(fn (Currency $record) => $enabled($record) ? 'gray' : 'primary')
                    ->visible(fn (Currency $record) => $record->code !== $entity->base_currency && (bool) auth()->user()?->can('enable', $record))
                    ->action(function (Currency $record) use ($entity, $enabled): void {
                        $turnOn = ! $enabled($record);
                        $turnOn ? $record->entities()->syncWithoutDetaching([$entity->getKey()]) : $record->entities()->detach($entity->getKey());

                        activity('master_data')->causedBy(auth()->user())
                            ->event($turnOn ? 'currency_enabled' : 'currency_disabled')
                            ->withProperties(['entity_id' => $entity->getKey(), 'currency' => $record->code])
                            ->log(($turnOn ? 'Currency enabled: ' : 'Currency disabled: ').$record->code);
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCurrencies::route('/'),
        ];
    }
}
