<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Settings\Settings;
use App\Domain\MasterData\Enums\GlAccountType;
use App\Domain\MasterData\Models\CapexCategory;
use App\Domain\MasterData\Models\CostCentre;
use App\Domain\MasterData\Models\Currency;
use App\Domain\MasterData\Models\Department;
use App\Domain\MasterData\Models\GlAccount;
use App\Filament\Admin\Resources\CostCentres\Pages\ManageCostCentres;
use App\Filament\Admin\Resources\Currencies\Pages\ManageCurrencies;
use App\Filament\Admin\Resources\Departments\Pages\ManageDepartments;
use App\Filament\Admin\Resources\GlAccounts\Pages\ManageGlAccounts;
use Livewire\Livewire;

beforeEach(function () {
    $this->kenya = kenya();
    $this->admin = userIn($this->kenya, ['Entity Admin']);
    actingInEntity($this->admin, $this->kenya);
});

it('creates a department with a head of department, audited', function () {
    $head = userIn($this->kenya, ['Approver']);

    Livewire::test(ManageDepartments::class)
        ->callAction('create', ['code' => 'ENG', 'name' => 'Engineering', 'head_user_id' => $head->id])
        ->assertHasNoActionErrors();

    $department = Department::query()->where('code', 'ENG')->firstOrFail();
    expect($department->entity_id)->toBe($this->kenya->id)
        ->and($department->head_user_id)->toBe($head->id);

    $log = Activity::query()->where('subject_type', $department->getMorphClass())->where('subject_id', $department->id)->where('event', 'created')->firstOrFail();
    expect($log->entity_id)->toBe($this->kenya->id)->and($log->log_name)->toBe('master_data');
});

it('rejects a duplicate code within the entity', function () {
    Department::query()->create(['code' => 'ENG', 'name' => 'Engineering']);

    Livewire::test(ManageDepartments::class)
        ->callAction('create', ['code' => 'ENG', 'name' => 'Another'])
        ->assertHasActionErrors(['code' => 'unique']);
});

it('allows the same code in another entity', function () {
    $uae = makeEntity(['code' => 'AE']);
    inEntity($uae, fn () => Department::query()->create(['code' => 'ENG', 'name' => 'UAE Engineering']));

    Livewire::test(ManageDepartments::class)
        ->callAction('create', ['code' => 'ENG', 'name' => 'Kenya Engineering'])
        ->assertHasNoActionErrors();
});

it('deactivates and reactivates instead of deleting', function () {
    $department = Department::query()->create(['code' => 'ENG', 'name' => 'Engineering']);

    Livewire::test(ManageDepartments::class)
        ->assertTableActionDoesNotExist('delete')
        ->callTableAction('toggleActive', $department);
    expect($department->fresh()->is_active)->toBeFalse();

    Livewire::test(ManageDepartments::class)
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$department])
        ->callTableAction('toggleActive', $department);
    expect($department->fresh()->is_active)->toBeTrue();
});

it('offers only active departments of this entity when creating a cost centre', function () {
    $active = Department::query()->create(['code' => 'PROD', 'name' => 'Production']);
    Department::query()->create(['code' => 'OLD', 'name' => 'Old dept', 'is_active' => false]);

    Livewire::test(ManageCostCentres::class)
        ->callAction('create', ['code' => 'KE-CC-1', 'name' => 'Line 1', 'department_id' => $active->id, 'effective_from' => '2026-04-01'])
        ->assertHasNoActionErrors();

    expect(CostCentre::query()->where('code', 'KE-CC-1')->value('department_id'))->toBe($active->id);
});

it('requires effective-to after effective-from', function () {
    Livewire::test(ManageCostCentres::class)
        ->callAction('create', ['code' => 'KE-CC-1', 'name' => 'Line 1', 'effective_from' => '2026-04-01', 'effective_to' => '2026-03-01'])
        ->assertHasActionErrors(['effective_to']);
});

it('flags GL accounts by type so Capex screens can offer Capex accounts only', function () {
    GlAccount::query()->create(['code' => '150000', 'name' => 'Plant', 'type' => GlAccountType::Capex]);
    GlAccount::query()->create(['code' => '610000', 'name' => 'Repairs', 'type' => GlAccountType::Opex]);

    expect(GlAccount::query()->ofType(GlAccountType::Capex)->pluck('code')->all())->toBe(['150000']);

    Livewire::test(ManageGlAccounts::class)
        ->filterTable('type', 'opex')
        ->assertCountTableRecords(1);
});

it('uses the category quotation minimum, else the entity setting', function () {
    $plant = CapexCategory::query()->create(['code' => 'PLANT', 'name' => 'Plant']);
    $furniture = CapexCategory::query()->create(['code' => 'FURN', 'name' => 'Furniture', 'minimum_quotations' => 2]);

    expect($plant->requiredQuotations())->toBe(3)->and($furniture->requiredQuotations())->toBe(2);

    app(Settings::class)->set('capex.minimum_quotations', 4, $this->kenya);

    expect($plant->requiredQuotations())->toBe(4);
});

it('lets an entity admin choose the currencies the entity uses', function () {
    $eur = Currency::query()->findOrFail('EUR');

    Livewire::test(ManageCurrencies::class)
        ->assertTableActionHidden('toggleEnabled', Currency::query()->findOrFail('KES'))
        ->callTableAction('toggleEnabled', $eur);

    expect(Currency::optionsFor($this->kenya))->toHaveKeys(['KES', 'EUR'])
        ->and(Activity::query()->where('event', 'currency_enabled')->exists())->toBeTrue();
});

it('keeps people without master data permission out', function () {
    $requester = userIn($this->kenya, ['Requester']);
    actingInEntity($requester, $this->kenya);

    $this->get('/admin/departments')->assertForbidden();
    $this->get('/admin/vendors')->assertForbidden();
});

it('lets viewers read master data but not change it', function () {
    $auditor = userIn($this->kenya, ['Viewer / Auditor']);
    $department = Department::query()->create(['code' => 'ENG', 'name' => 'Engineering']);
    actingInEntity($auditor, $this->kenya);

    $this->get('/admin/departments')->assertOk();

    Livewire::test(ManageDepartments::class)
        ->assertActionHidden('create')
        ->assertTableActionHidden('toggleActive', $department)
        ->assertTableActionHidden('edit', $department);
});
