<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\ProfitCentre;
use Illuminate\Database\Eloquent\Model;

class ProfitCentreDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'profit_centres';
    }

    public function label(): string
    {
        return __('Profit centres');
    }

    protected function model(): string
    {
        return ProfitCentre::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('KE-PC-10', 'Flexible packaging'),
            Column::make('effective_from', 'date')->rules(['nullable', 'date'])->example('2026-04-01'),
            Column::make('effective_to', 'date')->rules(['nullable', 'date', 'after_or_equal:effective_from']),
            $this->description(),
            $this->active(),
        ];
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        return ['effective_from' => $row['effective_from'], 'effective_to' => $row['effective_to']];
    }

    protected function exportValues(Model $record): array
    {
        /** @var ProfitCentre $record */
        return ['effective_from' => $record->effective_from, 'effective_to' => $record->effective_to];
    }
}
