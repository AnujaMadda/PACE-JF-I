<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Enums\GlAccountType;
use App\Domain\MasterData\Import\CodeKeyedDefinition;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Models\GlAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class GlAccountDefinition extends CodeKeyedDefinition
{
    public function key(): string
    {
        return 'gl_accounts';
    }

    public function label(): string
    {
        return __('GL accounts');
    }

    protected function model(): string
    {
        return GlAccount::class;
    }

    protected function columns(): array
    {
        return [
            ...$this->codeAndName('150000', 'Plant and machinery'),
            Column::make('type')
                ->rules(['required', Rule::enum(GlAccountType::class)])
                ->example('capex', implode(' / ', array_map(fn (GlAccountType $t) => $t->value, GlAccountType::cases()))),
            $this->description(),
            $this->active(),
        ];
    }

    protected function attributes(array $row, ImportContext $context): array
    {
        return ['type' => GlAccountType::from(strtolower((string) $row['type']))];
    }

    public function normalise(array $raw, User $user): array
    {
        $row = parent::normalise($raw, $user);
        $row['type'] = $row['type'] !== null ? strtolower((string) $row['type']) : null;

        return $row;
    }

    protected function exportValues(Model $record): array
    {
        /** @var GlAccount $record */
        return ['type' => $record->type];
    }
}
