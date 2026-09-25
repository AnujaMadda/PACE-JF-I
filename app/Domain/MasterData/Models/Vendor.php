<?php

namespace App\Domain\MasterData\Models;

use App\Domain\MasterData\Concerns\IsMasterData;
use App\Domain\MasterData\Support\BankDetails;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A supplier. Bank details are encrypted at rest, hidden from serialisation,
 * kept out of the attribute audit and shown masked to anyone without
 * vendors.view_bank_details. Changes to them are audited with masked values.
 *
 * @property int $id
 * @property int $entity_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property string|null $tax_number
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $currency_code
 * @property int|null $payment_term_id
 * @property string|null $bank_name
 * @property string|null $bank_branch
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number
 * @property string|null $bank_swift_code
 * @property string|null $bank_iban
 */
#[Fillable([
    'code', 'name', 'description', 'is_active', 'tax_number', 'email', 'phone', 'address',
    'currency_code', 'payment_term_id',
    'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'bank_swift_code', 'bank_iban',
])]
#[Hidden(BankDetails::FIELDS)]
#[UseFactory(VendorFactory::class)]
class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory, IsMasterData {
        IsMasterData::getActivitylogOptions as masterDataLogOptions;
    }

    protected function casts(): array
    {
        return array_fill_keys(BankDetails::FIELDS, 'encrypted');
    }

    protected static function booted(): void
    {
        static::updated(function (Vendor $vendor): void {
            $changed = array_values(array_intersect(BankDetails::FIELDS, array_keys($vendor->getChanges())));

            if ($changed === []) {
                return;
            }

            activity('master_data')
                ->performedOn($vendor)
                ->causedBy(auth()->user())
                ->event('bank_details_changed')
                ->withProperties([
                    'fields' => $changed,
                    'old' => collect($changed)->mapWithKeys(fn (string $f) => [$f => BankDetails::mask($vendor->getOriginal($f))])->all(),
                    'new' => collect($changed)->mapWithKeys(fn (string $f) => [$f => BankDetails::mask($vendor->getAttribute($f))])->all(),
                ])
                ->log('Vendor bank details changed');
        });
    }

    /**
     * @return BelongsTo<PaymentTerm, $this>
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function maskedAccountNumber(): string
    {
        return BankDetails::mask($this->bank_account_number);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return $this->masterDataLogOptions()->logExcept(BankDetails::FIELDS);
    }
}
