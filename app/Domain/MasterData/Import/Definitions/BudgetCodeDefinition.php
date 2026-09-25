<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\BudgetCode;
use App\Domain\MasterData\Models\GlAccount;
use Illuminate\Database\Eloquent\Model;

class BudgetCodeDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'budget_codes';
    }

    public function label(): string
    {
        return __('Budget codes');
    }

    protected function model(): string
    {
        return BudgetCode::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('CPX-PM', 'Capex — plant and machinery'),
            Column::make('gl_account_code')->rules(['nullable', 'string'])->example('150000'),
            $this->description(),
            $this->active(),
        ];
    }

    public function extraRules(array $row, ImportContext $context): array
    {
        return ['gl_account_code' => [$context->existsRule(GlAccount::class, __('GL account'))]];
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        return ['gl_account_id' => $context->idFor(GlAccount::class, $row['gl_account_code'])];
    }

    protected function exportWith(): array
    {
        return ['glAccount'];
    }

    protected function exportValues(Model $record): array
    {
        /** @var BudgetCode $record */
        return ['gl_account_code' => $record->glAccount?->code];
    }
}
