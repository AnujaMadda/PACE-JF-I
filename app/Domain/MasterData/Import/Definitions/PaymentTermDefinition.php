<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\PaymentTerm;
use Illuminate\Database\Eloquent\Model;

class PaymentTermDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'payment_terms';
    }

    public function label(): string
    {
        return __('Payment terms');
    }

    protected function model(): string
    {
        return PaymentTerm::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('NET30', 'Net 30 days'),
            Column::make('days', 'int')->rules(['required', 'integer', 'min:0', 'max:365'])->example('30'),
            $this->description(),
            $this->active(),
        ];
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        return ['days' => (int) $row['days']];
    }

    protected function exportValues(Model $record): array
    {
        /** @var PaymentTerm $record */
        return ['days' => $record->days];
    }
}
