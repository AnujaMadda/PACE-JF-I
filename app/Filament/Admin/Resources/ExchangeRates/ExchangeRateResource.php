<?php

namespace App\Filament\Admin\Resources\ExchangeRates;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\MasterData\Models\Currency;
use App\Domain\MasterData\Models\ExchangeRate;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\ExchangeRates\Pages\ManageExchangeRates;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * Effective-dated exchange rates for the current entity. Requests use the
 * latest rate on or before their date; add a new dated row when rates move.
 */
class ExchangeRateResource extends MasterDataResource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 21;

    protected static ?string $recordTitleAttribute = 'from_currency';

    public static function importType(): ?string
    {
        return 'exchange_rates';
    }

    public static function form(Schema $schema): Schema
    {
        $entity = app(CurrentEntity::class)->require();
        $currencies = fn () => Currency::query()->where('is_active', true)->orderBy('code')->pluck('code', 'code')->all();

        return $schema->columns(2)->components([
            Select::make('from_currency')->label(__('From'))->options($currencies)->searchable()->required()->default('USD'),
            Select::make('to_currency')->label(__('To'))->options($currencies)->searchable()->required()->default($entity->base_currency)->different('from_currency'),
            TextInput::make('rate')->label(__('Rate'))->required()->inputMode('decimal')
                ->rule('regex:/^\d{1,12}(\.\d{1,6})?$/')->rule('not_regex:/^0+(\.0+)?$/')
                ->helperText(__('1 unit of the From currency = this many units of the To currency.')),
            DatePicker::make('effective_from')->label(__('Effective from'))->required()->default(now())
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                    ->where('entity_id', $entity->getKey())
                    ->where('from_currency', $get('from_currency'))
                    ->where('to_currency', $get('to_currency'))),
            TextInput::make('source')->label(__('Source'))->maxLength(255)->placeholder('Central Bank of Kenya'),
            static::activeToggle(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::standardTable($table, [
            TextColumn::make('from_currency')->label(__('From'))->searchable()->sortable(),
            TextColumn::make('to_currency')->label(__('To'))->searchable()->sortable(),
            TextColumn::make('rate')->label(__('Rate'))->formatStateUsing(fn (ExchangeRate $record) => (string) $record->rate)->alignEnd(),
            TextColumn::make('effective_from')->label(__('Effective from'))->date()->sortable(),
            TextColumn::make('source')->label(__('Source'))->placeholder('—')->toggleable(),
        ], [
            SelectFilter::make('from_currency')->label(__('From'))->options(fn () => ExchangeRate::query()->distinct()->orderBy('from_currency')->pluck('from_currency', 'from_currency')->all()),
        ], 'effective_from')->defaultSort('effective_from', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExchangeRates::route('/'),
        ];
    }
}
