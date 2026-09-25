<?php

namespace App\Filament\Admin\Resources\BudgetCodes;

use App\Domain\MasterData\Models\BudgetCode;
use App\Domain\MasterData\Models\GlAccount;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\BudgetCodes\Pages\ManageBudgetCodes;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BudgetCodeResource extends MasterDataResource
{
    protected static ?string $model = BudgetCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 17;

    public static function importType(): ?string
    {
        return 'budget_codes';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            static::codeInput(),
            static::nameInput(),
            Select::make('gl_account_id')->label(__('GL account'))->relationship('glAccount', 'name', fn (Builder $query) => $query->where('is_active', true))->getOptionLabelFromRecordUsing(fn (GlAccount $record) => $record->label)->searchable(['code', 'name'])->preload(),
            static::descriptionInput(),
            static::activeToggle(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::standardTable($table, [
            ...static::codeNameColumns(),
            TextColumn::make('glAccount.code')->label(__('GL account'))->placeholder('—'),
        ], [], 'code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBudgetCodes::route('/'),
        ];
    }
}
