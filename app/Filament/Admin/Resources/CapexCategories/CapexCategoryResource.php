<?php

namespace App\Filament\Admin\Resources\CapexCategories;

use App\Domain\Core\Settings\Settings;
use App\Domain\MasterData\Models\CapexCategory;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\CapexCategories\Pages\ManageCapexCategories;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CapexCategoryResource extends MasterDataResource
{
    protected static ?string $model = CapexCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 18;

    protected static ?string $navigationLabel = 'Capex categories';

    public static function importType(): ?string
    {
        return 'capex_categories';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            static::codeInput(),
            static::nameInput(),
            TextInput::make('minimum_quotations')->label(__('Minimum quotations'))->integer()->minValue(0)->maxValue(10)
                ->placeholder(fn () => (string) app(Settings::class)->int('capex.minimum_quotations'))
                ->helperText(__('Leave blank to use the entity default.')),
            static::descriptionInput(),
            static::activeToggle(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::standardTable($table, [
            ...static::codeNameColumns(),
            TextColumn::make('minimum_quotations')->label(__('Min. quotations'))->placeholder(fn () => __('Default (:n)', ['n' => app(Settings::class)->int('capex.minimum_quotations')])),
        ], [], 'code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCapexCategories::route('/'),
        ];
    }
}
