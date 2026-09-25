<?php

namespace App\Filament\Admin\Resources\GlAccounts;

use App\Domain\MasterData\Enums\GlAccountType;
use App\Domain\MasterData\Models\GlAccount;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\GlAccounts\Pages\ManageGlAccounts;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GlAccountResource extends MasterDataResource
{
    protected static ?string $model = GlAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'GL accounts';

    public static function importType(): ?string
    {
        return 'gl_accounts';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            static::codeInput(),
            static::nameInput(),
            Select::make('type')->label(__('Type'))->options(GlAccountType::options())->required()
                ->helperText(__('The Capex request form only offers Capex accounts.')),
            static::descriptionInput(),
            static::activeToggle(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::standardTable($table, [
            ...static::codeNameColumns(),
            TextColumn::make('type')->label(__('Type'))->badge()->formatStateUsing(fn (GlAccountType $state) => $state->label())->color(fn (GlAccountType $state) => $state === GlAccountType::Capex ? 'primary' : 'gray')->sortable(),
        ], [SelectFilter::make('type')->label(__('Type'))->options(GlAccountType::options())], 'code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGlAccounts::route('/'),
        ];
    }
}
