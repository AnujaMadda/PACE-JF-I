<?php

namespace App\Filament\Admin\Resources\Vendors;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Models\Currency;
use App\Domain\MasterData\Models\Vendor;
use App\Domain\MasterData\Support\BankDetails;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\Vendors\Pages\ManageVendors;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Vendors. Bank details are only sent to the browser for people with
 * vendors.view_bank_details (payment roles); everyone else sees them masked.
 */
class VendorResource extends MasterDataResource
{
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 15;

    public static function importType(): ?string
    {
        return 'vendors';
    }

    /** Payment roles without masterdata.manage may change bank details only. */
    protected static function canEditDetails(): bool
    {
        return (bool) auth()->user()?->can('masterdata.manage');
    }

    protected static function canSeeBank(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return BankDetails::canView($user);
    }

    public static function form(Schema $schema): Schema
    {
        $entity = app(CurrentEntity::class)->require();

        $locked = fn (): bool => ! static::canEditDetails();

        return $schema->columns(2)->components([
            static::codeInput('V-00001')->disabled($locked),
            static::nameInput()->disabled($locked),
            TextInput::make('tax_number')->label(__('Tax number (KRA PIN)'))->maxLength(64)->disabled($locked),
            TextInput::make('email')->label(__('Email'))->email()->maxLength(255)->disabled($locked),
            TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(64)->disabled($locked),
            Select::make('currency_code')->label(__('Currency'))->options(fn () => Currency::optionsFor($entity))->searchable()->default($entity->base_currency)->disabled($locked),
            Select::make('payment_term_id')->label(__('Payment terms'))
                ->relationship('paymentTerm', 'name', fn (Builder $query) => $query->where('is_active', true))->preload()->disabled($locked),
            Textarea::make('address')->label(__('Address'))->rows(2)->maxLength(1000)->columnSpanFull()->disabled($locked),

            Section::make(__('Bank details'))
                ->description(fn () => static::canSeeBank()
                    ? __('Encrypted when stored. Changes are recorded in the audit log.')
                    : __('Only payment roles can see or change bank details.'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('bank_name')->label(__('Bank'))->maxLength(255)->visible(fn () => static::canSeeBank()),
                    TextInput::make('bank_branch')->label(__('Branch'))->maxLength(255)->visible(fn () => static::canSeeBank()),
                    TextInput::make('bank_account_name')->label(__('Account name'))->maxLength(255)->visible(fn () => static::canSeeBank()),
                    TextInput::make('bank_account_number')->label(__('Account number'))->maxLength(64)->visible(fn () => static::canSeeBank()),
                    TextInput::make('bank_swift_code')->label(__('SWIFT / BIC'))->maxLength(16)->visible(fn () => static::canSeeBank()),
                    TextInput::make('bank_iban')->label(__('IBAN'))->maxLength(64)->visible(fn () => static::canSeeBank()),
                    TextEntry::make('masked_account')
                        ->label(__('Account number'))
                        ->state(fn (?Vendor $record) => $record ? $record->maskedAccountNumber() : '—')
                        ->hidden(fn () => static::canSeeBank()),
                ]),

            static::descriptionInput()->disabled($locked),
            static::activeToggle()->disabled($locked),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table = static::standardTable($table, [
            ...static::codeNameColumns(),
            TextColumn::make('tax_number')->label(__('Tax number'))->searchable()->toggleable(),
            TextColumn::make('currency_code')->label(__('Currency')),
            TextColumn::make('paymentTerm.name')->label(__('Terms'))->placeholder('—'),
            TextColumn::make('bank_account_masked')->label(__('Bank account'))
                ->state(fn (Vendor $record) => $record->maskedAccountNumber()),
        ]);

        // Bank fields are $hidden on the model, so fill them explicitly — and only for payment roles.
        return $table->recordActions([
            EditAction::make()
                ->fillForm(fn (Vendor $record) => static::formData($record))
                ->using(fn (Model $record, array $data) => static::updateRecord($record, $data)),
            static::toggleActiveAction(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formData(Vendor $record): array
    {
        $data = $record->attributesToArray();

        if (static::canSeeBank()) {
            foreach (BankDetails::FIELDS as $field) {
                $data[$field] = $record->getAttribute($field);
            }
        }

        return $data;
    }

    public static function createRecord(array $data): Model
    {
        return parent::createRecord(static::withoutBankUnlessAllowed($data));
    }

    public static function updateRecord(Model $record, array $data): Model
    {
        return parent::updateRecord($record, static::withoutBankUnlessAllowed($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function withoutBankUnlessAllowed(array $data): array
    {
        if (! static::canSeeBank()) {
            $data = array_diff_key($data, array_flip(BankDetails::FIELDS));
        }

        // Disabled fields are not submitted, but never trust that alone: payment-only users change bank details only.
        return static::canEditDetails() ? $data : array_intersect_key($data, array_flip(BankDetails::FIELDS));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVendors::route('/'),
        ];
    }
}
