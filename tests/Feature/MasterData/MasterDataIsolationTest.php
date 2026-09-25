<?php

use App\Domain\MasterData\Enums\GlAccountType;
use App\Domain\MasterData\Models;
use App\Filament\Admin\Resources;
use Livewire\Livewire;

/*
 * Every master data screen shows and acts on the current entity's records only.
 */

$types = [
    'departments' => [Resources\Departments\Pages\ManageDepartments::class, fn (string $c) => Models\Department::query()->create(['code' => $c, 'name' => "Dept {$c}"])],
    'cost centres' => [Resources\CostCentres\Pages\ManageCostCentres::class, fn (string $c) => Models\CostCentre::query()->create(['code' => $c, 'name' => "CC {$c}"])],
    'profit centres' => [Resources\ProfitCentres\Pages\ManageProfitCentres::class, fn (string $c) => Models\ProfitCentre::query()->create(['code' => $c, 'name' => "PC {$c}"])],
    'GL accounts' => [Resources\GlAccounts\Pages\ManageGlAccounts::class, fn (string $c) => Models\GlAccount::query()->create(['code' => $c, 'name' => "GL {$c}", 'type' => GlAccountType::Capex])],
    'internal orders' => [Resources\InternalOrders\Pages\ManageInternalOrders::class, fn (string $c) => Models\InternalOrder::query()->create(['code' => $c, 'name' => "IO {$c}"])],
    'payment terms' => [Resources\PaymentTerms\Pages\ManagePaymentTerms::class, fn (string $c) => Models\PaymentTerm::query()->create(['code' => $c, 'name' => "PT {$c}", 'days' => 30])],
    'vendors' => [Resources\Vendors\Pages\ManageVendors::class, fn (string $c) => Models\Vendor::query()->create(['code' => $c, 'name' => "Vendor {$c}"])],
    'budget codes' => [Resources\BudgetCodes\Pages\ManageBudgetCodes::class, fn (string $c) => Models\BudgetCode::query()->create(['code' => $c, 'name' => "BC {$c}"])],
    'capex categories' => [Resources\CapexCategories\Pages\ManageCapexCategories::class, fn (string $c) => Models\CapexCategory::query()->create(['code' => $c, 'name' => "Cat {$c}"])],
    'board papers' => [Resources\BoardPapers\Pages\ManageBoardPapers::class, fn (string $c) => Models\BoardPaper::query()->create(['code' => $c, 'name' => "BP {$c}", 'paper_date' => '2026-07-01', 'approved_amount' => '100.00', 'currency_code' => 'KES'])],
    'exchange rates' => [Resources\ExchangeRates\Pages\ManageExchangeRates::class, fn (string $c) => Models\ExchangeRate::query()->create(['from_currency' => 'USD', 'to_currency' => $c === 'KE-1' ? 'KES' : 'AED', 'rate' => '1.500000', 'effective_from' => '2026-09-01'])],
];

beforeEach(function () {
    $this->kenya = kenya();
    $this->uae = makeEntity(['code' => 'AE', 'base_currency' => 'AED']);
    $this->admin = userIn($this->kenya, ['Entity Admin']);
});

it('lists only the current entity\'s records', function (string $page, Closure $make) {
    $ke = inEntity($this->kenya, fn () => $make('KE-1'));
    $ae = inEntity($this->uae, fn () => $make('AE-1'));
    actingInEntity($this->admin, $this->kenya);

    Livewire::test($page)
        ->assertCanSeeTableRecords([$ke])
        ->assertCanNotSeeTableRecords([$ae]);
})->with($types);

it('cannot edit or deactivate another entity\'s record through Livewire', function (string $page, Closure $make) {
    $ae = inEntity($this->uae, fn () => $make('AE-1'));
    actingInEntity($this->admin, $this->kenya);

    foreach (['toggleActive', 'edit'] as $action) {
        try {
            Livewire::test($page)->callTableAction($action, $ae, ['name' => 'Hacked']);
        } catch (Throwable) {
            // The record is outside the table query and cannot be resolved.
        }
    }

    $fresh = inEntity($this->uae, fn () => $ae->fresh());
    expect((bool) $fresh->is_active)->toBeTrue()
        ->and($fresh->getAttributes()['name'] ?? null)->not->toBe('Hacked');
})->with($types);

it('shows the same code independently in each entity', function () {
    inEntity($this->kenya, fn () => Models\Department::query()->create(['code' => 'FIN', 'name' => 'Kenya Finance']));
    inEntity($this->uae, fn () => Models\Department::query()->create(['code' => 'FIN', 'name' => 'UAE Finance']));

    expect(inEntity($this->kenya, fn () => Models\Department::query()->pluck('name')->all()))->toBe(['Kenya Finance']);
});
