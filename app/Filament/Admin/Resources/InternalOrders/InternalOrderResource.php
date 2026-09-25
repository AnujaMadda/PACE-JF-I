<?php

namespace App\Filament\Admin\Resources\InternalOrders;

use App\Domain\MasterData\Models\InternalOrder;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\InternalOrders\Pages\ManageInternalOrders;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InternalOrderResource extends MasterDataResource
{
    protected static ?string $model = InternalOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 14;

    public static function importType(): ?string
    {
        return 'internal_orders';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            static::codeInput(),
            static::nameInput(),
            Select::make('cost_centre_id')->label(__('Cost centre'))->relationship('costCentre', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload(),
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
            TextColumn::make('costCentre.code')->label(__('Cost centre'))->placeholder('—'),
            TextColumn::make('effective_from')->label(__('From'))->date()->sortable()->toggleable(),
            TextColumn::make('effective_to')->label(__('To'))->date()->sortable()->toggleable(),
        ], [], 'code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInternalOrders::route('/'),
        ];
    }
}
