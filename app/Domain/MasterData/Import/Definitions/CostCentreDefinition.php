<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\CostCentre;
use App\Domain\MasterData\Models\Department;
use Illuminate\Database\Eloquent\Model;

class CostCentreDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'cost_centres';
    }

    public function label(): string
    {
        return __('Cost centres');
    }

    protected function model(): string
    {
        return CostCentre::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('KE-CC-100', 'Extrusion line 1'),
            Column::make('department_code')->rules(['nullable', 'string'])->example('PROD'),
            Column::make('owner_email')->rules(['nullable', 'email'])->example('line.manager@jfi.lk', 'Cost centre owner (a user of this entity)'),
            Column::make('effective_from', 'date')->rules(['nullable', 'date'])->example('2026-04-01'),
            Column::make('effective_to', 'date')->rules(['nullable', 'date', 'after_or_equal:effective_from'])->example(''),
            $this->description(),
            $this->active(),
        ];
    }

    public function extraRules(array $row, ImportContext $context): array
    {
        return [
            'department_code' => [$context->existsRule(Department::class, __('Department'))],
            'owner_email' => [$context->userExistsRule(__('Owner'))],
        ];
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        return [
            'department_id' => $context->idFor(Department::class, $row['department_code']),
            'owner_user_id' => $context->userIdFor($row['owner_email']),
            'effective_from' => $row['effective_from'],
            'effective_to' => $row['effective_to'],
        ];
    }

    protected function exportWith(): array
    {
        return ['department', 'owner'];
    }

    protected function exportValues(Model $record): array
    {
        /** @var CostCentre $record */
        return [
            'department_code' => $record->department?->code,
            'owner_email' => $record->owner?->email,
            'effective_from' => $record->effective_from,
            'effective_to' => $record->effective_to,
        ];
    }
}
