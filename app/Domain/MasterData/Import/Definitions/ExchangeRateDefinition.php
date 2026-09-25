<?php

namespace App\Domain\MasterData\Import\Definitions;

use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Import\Column;
use App\Domain\MasterData\Import\ImportContext;
use App\Domain\MasterData\Import\ImportDefinition;
use App\Domain\MasterData\Models\Currency;
use App\Domain\MasterData\Models\ExchangeRate;
use Illuminate\Validation\Rule;

/**
 * Exchange rates, keyed by currency pair and effective date.
 */
class ExchangeRateDefinition extends ImportDefinition
{
    public function key(): string
    {
        return 'exchange_rates';
    }

    public function label(): string
    {
        return __('Exchange rates');
    }

    protected function columns(): array
    {
        return [
            Column::make('from_currency')->rules(['required', 'string', 'size:3'])->example('USD'),
            Column::make('to_currency')->rules(['required', 'string', 'size:3', 'different:from_currency'])->example('KES'),
            Column::make('rate', 'rate')->rules(['required', 'numeric', 'gt:0'])->example('129.500000', '1 unit of from_currency = rate units of to_currency'),
            Column::make('effective_from', 'date')->rules(['required', 'date'])->example('2026-09-01'),
            Column::make('source')->rules(['nullable', 'string', 'max:255'])->example('Central Bank of Kenya'),
            Column::make('is_active', 'bool')->rules(['nullable', 'boolean'])->example('yes'),
        ];
    }

    public function rowKey(array $row): ?string
    {
        return strtolower($row['from_currency'].'|'.$row['to_currency'].'|'.$row['effective_from']);
    }

    public function extraRules(array $row, ImportContext $context): array
    {
        $codes = Currency::query()->where('is_active', true)->pluck('code')->all();

        return [
            'from_currency' => [Rule::in($codes)],
            'to_currency' => [Rule::in($codes)],
        ];
    }

    public function normalise(array $raw, User $user): array
    {
        $row = parent::normalise($raw, $user);

        foreach (['from_currency', 'to_currency'] as $key) {
            $row[$key] = $row[$key] !== null ? strtoupper((string) $row[$key]) : null;
        }

        return $row;
    }

    public function persist(array $row, ImportContext $context): string
    {
        $rate = ExchangeRate::query()
            ->where('from_currency', $row['from_currency'])
            ->where('to_currency', $row['to_currency'])
            ->whereDate('effective_from', $row['effective_from'])
            ->first();

        $created = $rate === null;
        $rate ??= new ExchangeRate(['from_currency' => $row['from_currency'], 'to_currency' => $row['to_currency'], 'effective_from' => $row['effective_from']]);
        $rate->fill(['rate' => $row['rate'], 'source' => $row['source'], 'is_active' => $row['is_active'] ?? true])->save();

        return $created ? 'created' : 'updated';
    }

    public function exportRows(User $user): iterable
    {
        foreach (ExchangeRate::query()->orderBy('from_currency')->orderBy('to_currency')->orderByDesc('effective_from')->lazy(500) as $rate) {
            yield [
                'from_currency' => $rate->from_currency,
                'to_currency' => $rate->to_currency,
                'rate' => (string) $rate->rate,
                'effective_from' => $rate->effective_from,
                'source' => $rate->source,
                'is_active' => $rate->is_active,
            ];
        }
    }
}
