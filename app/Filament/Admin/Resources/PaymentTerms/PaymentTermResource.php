<?php

namespace App\Filament\Admin\Resources\PaymentTerms;

use App\Domain\MasterData\Models\PaymentTerm;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\PaymentTerms\Pages\ManagePaymentTerms;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentTermResource extends MasterDataResource
{
    protected static ?string $model = PaymentTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 16;

    public static function importType(): ?string
    {
        return 'payment_terms';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            static::codeInput(),
            static::nameInput(),
            TextInput::make('days')->label(__('Days'))->integer()->minValue(0)->maxValue(365)->required()->default(30),
            static::descriptionInput(),
            static::activeToggle(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::standardTable($table, [
            ...static::codeNameColumns(),
            TextColumn::make('days')->label(__('Days'))->numeric()->sortable(),
        ], [], 'code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaymentTerms::route('/'),
        ];
    }
}
