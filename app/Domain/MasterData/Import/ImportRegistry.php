<?php

namespace App\Domain\MasterData\Import;

use InvalidArgumentException;

/**
 * Every importable sheet, by key. Later phases register budgets here.
 */
final class ImportRegistry
{
    /** @var array<string, class-string<ImportDefinition>> */
    private const DEFINITIONS = [
        'departments' => Definitions\DepartmentDefinition::class,
        'cost_centres' => Definitions\CostCentreDefinition::class,
        'profit_centres' => Definitions\ProfitCentreDefinition::class,
        'gl_accounts' => Definitions\GlAccountDefinition::class,
        'internal_orders' => Definitions\InternalOrderDefinition::class,
        'payment_terms' => Definitions\PaymentTermDefinition::class,
        'vendors' => Definitions\VendorDefinition::class,
        'budget_codes' => Definitions\BudgetCodeDefinition::class,
        'capex_categories' => Definitions\CapexCategoryDefinition::class,
        'board_papers' => Definitions\BoardPaperDefinition::class,
        'exchange_rates' => Definitions\ExchangeRateDefinition::class,
        'users' => Definitions\UserDefinition::class,
    ];

    public function get(string $key): ImportDefinition
    {
        $class = self::DEFINITIONS[$key] ?? throw new InvalidArgumentException("Unknown import type [{$key}].");

        return app($class);
    }

    /**
     * @return array<string, string> key => label
     */
    public function options(): array
    {
        return collect(self::DEFINITIONS)->map(fn (string $class) => app($class)->label())->all();
    }
}
