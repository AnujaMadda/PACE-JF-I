<?php

namespace App\Filament\Admin\Resources\CostCentres;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\MasterData\Models\CostCentre;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\CostCentres\Pages\ManageCostCentres;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CostCentreResource extends MasterDataResource
{
    protected static ?string $model = CostCentre::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?int $navigationSort = 11;

    public static function importType(): ?string
    {
        return 'cost_centres';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            static::codeInput(),
            static::nameInput(),
            Select::make('department_id')->label(__('Department'))->relationship('department', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload(),
            Select::make('owner_user_id')
                ->label(__('Owner'))
                ->relationship('owner', 'name', fn (Builder $query) => $query->whereHas('entities', fn (Builder $e) => $e->whereKey(app(CurrentEntity::class)->id())))
                ->searchable()
                ->preload(),
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
            TextColumn::make('department.name')->label(__('Department'))->placeholder('—')->sortable(),
            TextColumn::make('owner.name')->label(__('Owner'))->placeholder('—'),
            TextColumn::make('effective_from')->label(__('From'))->date()->sortable()->toggleable(),
            TextColumn::make('effective_to')->label(__('To'))->date()->sortable()->toggleable(),
        ], [SelectFilter::make('department_id')->label(__('Department'))->relationship('department', 'name')], 'code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCostCentres::route('/'),
        ];
    }
}
