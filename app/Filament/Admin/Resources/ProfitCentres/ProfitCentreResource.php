<?php

namespace App\Filament\Admin\Resources\ProfitCentres;

use App\Domain\MasterData\Models\ProfitCentre;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\ProfitCentres\Pages\ManageProfitCentres;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProfitCentreResource extends MasterDataResource
{
    protected static ?string $model = ProfitCentre::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?int $navigationSort = 12;

    public static function importType(): ?string
    {
        return 'profit_centres';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            static::codeInput(),
            static::nameInput(),
            DatePicker::make('effective_from')->label(__('Effective from')),
            DatePicker::make('effective_to')->label(__('Effective to'))->afterOrEqual('effective_from'),
            static::descriptionInput(),
            static::activeToggle(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::standardTable($table, [
            ...static::codeNameColumns(),
            TextColumn::make('effective_from')->label(__('From'))->date()->sortable()->toggleable(),
            TextColumn::make('effective_to')->label(__('To'))->date()->sortable()->toggleable(),
        ], [], 'code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProfitCentres::route('/'),
        ];
    }
}
