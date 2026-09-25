<?php

namespace App\Filament\Admin\Resources\Entities;

use App\Domain\Core\Models\Entity;
use App\Filament\Admin\Resources\Entities\Pages\CreateEntity;
use App\Filament\Admin\Resources\Entities\Pages\EditEntity;
use App\Filament\Admin\Resources\Entities\Pages\ListEntities;
use App\Filament\Admin\Resources\Entities\Schemas\EntityForm;
use App\Filament\Admin\Resources\Entities\Tables\EntitiesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Operating entities. Group Super Admins only (EntityPolicy + Gate::before).
 */
class EntityResource extends Resource
{
    protected static ?string $model = Entity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Organisation';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EntityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EntitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntities::route('/'),
            'create' => CreateEntity::route('/create'),
            'edit' => EditEntity::route('/{record}/edit'),
        ];
    }
}
