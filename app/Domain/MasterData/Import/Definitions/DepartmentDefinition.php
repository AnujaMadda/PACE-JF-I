<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\Department;
use Illuminate\Database\Eloquent\Model;

class DepartmentDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'departments';
    }

    public function label(): string
    {
        return __('Departments');
    }

    protected function model(): string
    {
        return Department::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('PROD', 'Production'),
            Column::make('head_email')->rules(['nullable', 'email'])->example('plant.manager@jfi.lk', 'Head of Department (must be a user of this entity)'),
            $this->description(),
            $this->active(),
        ];
    }

    public function extraRules(array $row, ImportContext $context): array
    {
        return ['head_email' => [$context->userExistsRule(__('Head of Department'))]];
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        return ['head_user_id' => $context->userIdFor($row['head_email'])];
    }

    protected function exportWith(): array
    {
        return ['head'];
    }

    protected function exportValues(Model $record): array
    {
        /** @var Department $record */
        return ['head_email' => $record->head?->email];
    }
}
