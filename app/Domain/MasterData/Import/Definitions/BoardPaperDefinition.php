<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\BoardPaper;
use App\Domain\MasterData\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Board paper details. Documents are attached on screen, not imported.
 */
class BoardPaperDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'board_papers';
    }

    public function label(): string
    {
        return __('Board papers');
    }

    protected function model(): string
    {
        return BoardPaper::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('BP-2026-07', 'Second extrusion line'),
            Column::make('paper_date', 'date')->rules(['required', 'date'])->example('2026-07-15'),
            Column::make('approved_amount', 'decimal')->rules(['required', 'numeric', 'min:0'])->example('45000000.00'),
            Column::make('currency')->rules(['required', 'string', 'size:3'])->example('KES'),
            $this->description(),
            $this->active(),
        ];
    }

    public function extraRules(array $row, ImportContext $context): array
    {
        return ['currency' => [Rule::in(array_keys(Currency::optionsFor($context->entity)))]];
    }

    public function normalise(array $raw, User $user): array
    {
        $row = parent::normalise($raw, $user);
        $row['currency'] = $row['currency'] !== null ? strtoupper((string) $row['currency']) : null;

        return $row;
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        return [
            'paper_date' => $row['paper_date'],
            'approved_amount' => $row['approved_amount'],
            'currency_code' => $row['currency'],
        ];
    }

    protected function exportValues(Model $record): array
    {
        /** @var BoardPaper $record */
        return [
            'paper_date' => $record->paper_date,
            'approved_amount' => (string) $record->approved_amount,
            'currency' => $record->currency_code,
        ];
    }
}
