<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\CapexCategory;
use Illuminate\Database\Eloquent\Model;

class CapexCategoryDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'capex_categories';
    }

    public function label(): string
    {
        return __('Capex categories');
    }

    protected function model(): string
    {
        return CapexCategory::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('PLANT', 'Plant and machinery'),
            Column::make('minimum_quotations', 'int')->rules(['nullable', 'integer', 'min:0', 'max:10'])->example('', 'Blank = entity default'),
            $this->description(),
            $this->active(),
        ];
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        return ['minimum_quotations' => $row['minimum_quotations'] === null ? null : (int) $row['minimum_quotations']];
    }

    protected function exportValues(Model $record): array
    {
        /** @var CapexCategory $record */
        return ['minimum_quotations' => $record->minimum_quotations];
    }
}
