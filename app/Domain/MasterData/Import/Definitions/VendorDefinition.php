<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\Currency;
use App\Domain\MasterData\Models\PaymentTerm;
use App\Domain\MasterData\Models\Vendor;
use App\Domain\MasterData\Support\BankDetails;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Vendors. Bank columns are only offered to people with
 * vendors.view_bank_details; for everyone else they are neither exported nor
 * read on import.
 */
class VendorDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'vendors';
    }

    public function label(): string
    {
        return __('Vendors');
    }

    protected function model(): string
    {
        return Vendor::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('V-00017', 'Nairobi Industrial Supplies Ltd'),
            Column::make('tax_number')->rules(['nullable', 'string', 'max:64'])->example('P051234567X', 'KRA PIN or other tax id'),
            Column::make('email')->rules(['nullable', 'email', 'max:255'])->example('accounts@example.co.ke'),
            Column::make('phone')->rules(['nullable', 'string', 'max:64'])->example('+254 20 123 4567'),
            Column::make('address')->rules(['nullable', 'string', 'max:1000']),
            Column::make('currency')->rules(['nullable', 'string', 'size:3'])->example('KES'),
            Column::make('payment_term_code')->rules(['nullable', 'string'])->example('NET30'),
            Column::make('bank_name')->rules(['nullable', 'string', 'max:255'])->example('Equity Bank Kenya'),
            Column::make('bank_branch')->rules(['nullable', 'string', 'max:255'])->example('Industrial Area'),
            Column::make('bank_account_name')->rules(['nullable', 'string', 'max:255']),
            Column::make('bank_account_number')->rules(['nullable', 'string', 'max:64'])->example('0123456789012'),
            Column::make('bank_swift_code')->rules(['nullable', 'string', 'max:16'])->example('EQBLKENA'),
            Column::make('bank_iban')->rules(['nullable', 'string', 'max:64']),
            $this->description(),
            $this->active(),
        ];
    }

    public function columnsFor(User $user): array
    {
        if (BankDetails::canView($user)) {
            return $this->columns();
        }

        return array_values(array_filter($this->columns(), fn (Column $c) => ! in_array($c->key, BankDetails::FIELDS, true)));
    }

    public function extraRules(array $row, ImportContext $context): array
    {
        return [
            'currency' => [Rule::in(array_keys(Currency::optionsFor($context->entity)))],
            'payment_term_code' => [$context->existsRule(PaymentTerm::class, __('Payment term'))],
        ];
    }

    public function normalise(array $raw, User $user): array
    {
        $row = parent::normalise($raw, $user);
        $row['currency'] = $row['currency'] !== null ? strtoupper((string) $row['currency']) : null;

        return $row;
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        $attributes = [
            'tax_number' => $row['tax_number'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'currency_code' => $row['currency'],
            'payment_term_id' => $context->idFor(PaymentTerm::class, $row['payment_term_code']),
        ];

        if (BankDetails::canView($context->user)) {
            foreach (BankDetails::FIELDS as $field) {
                $attributes[$field] = $row[$field] ?? null;
            }
        }

        return $attributes;
    }

    protected function exportWith(): array
    {
        return ['paymentTerm'];
    }

    protected function exportValues(Model $record): array
    {
        /** @var Vendor $record */
        $values = [
            'tax_number' => $record->tax_number,
            'email' => $record->email,
            'phone' => $record->phone,
            'address' => $record->address,
            'currency' => $record->currency_code,
            'payment_term_code' => $record->paymentTerm?->code,
        ];

        foreach (BankDetails::FIELDS as $field) {
            $values[$field] = $record->getAttribute($field);
        }

        return $values;
    }
}
