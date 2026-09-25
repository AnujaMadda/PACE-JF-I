<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\CostCentre;
use App\Domain\MasterData\Models\InternalOrder;
use Illuminate\Database\Eloquent\Model;

class InternalOrderDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'internal_orders';
    }

    public function label(): string
    {
        return __('Internal orders');
    }

    protected function model(): string
    {
        return InternalOrder::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('IO-2027-001', 'Extruder upgrade project'),
            Column::make('cost_centre_code')->rules(['nullable', 'string'])->example('KE-CC-100'),
            Column::make('effective_from', 'date')->rules(['nullable', 'date'])->example('2026-04-01'),
            Column::make('effective_to', 'date')->rules(['nullable', 'date', 'after_or_equal:effective_from']),
            $this->description(),
            $this->active(),
        ];
    }

    public function extraRules(array $row, ImportContext $context): array
    {
        return ['cost_centre_code' => [$context->existsRule(CostCentre::class, __('Cost centre'))]];
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        return [
            'cost_centre_id' => $context->idFor(CostCentre::class, $row['cost_centre_code']),
            'effective_from' => $row['effective_from'],
            'effective_to' => $row['effective_to'],
        ];
    }

    protected function exportWith(): array
    {
        return ['costCentre'];
    }

    protected function exportValues(Model $record): array
    {
        /** @var InternalOrder $record */
        return [
            'cost_centre_code' => $record->costCentre?->code,
            'effective_from' => $record->effective_from,
            'effective_to' => $record->effective_to,
        ];
    }
}
