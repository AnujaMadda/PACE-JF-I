<?php

namespace App\Filament\Admin\Resources\Departments;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\MasterData\Models\Department;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\Departments\Pages\ManageDepartments;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepartmentResource extends MasterDataResource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 10;

    public static function importType(): ?string
    {
        return 'departments';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            static::codeInput(),
            static::nameInput(),
            Select::make('head_user_id')
                ->label(__('Head of Department'))
                ->relationship('head', 'name', fn (Builder $query) => $query->whereHas('entities', fn (Builder $e) => $e->whereKey(app(CurrentEntity::class)->id())))
                ->searchable()
                ->preload(),
            static::descriptionInput(),
            static::activeToggle(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::standardTable($table, [
            ...static::codeNameColumns(),
            TextColumn::make('head.name')->label(__('Head of Department'))->placeholder('—'),
            TextColumn::make('cost_centres_count')->label(__('Cost centres'))->counts('costCentres'),
        ], [], 'code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDepartments::route('/'),
        ];
    }
}
