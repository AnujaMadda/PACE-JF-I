<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\MasterData\Import\ExportSheet;
use App\Domain\MasterData\Models\Vendor;
use App\Filament\Admin\Resources\Vendors\Pages\ManageVendors;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->kenya = kenya();
    $this->admin = userIn($this->kenya, ['Entity Admin']);
    $this->payments = userIn($this->kenya, ['Payment Team']);
    $this->vendor = inEntity($this->kenya, fn () => Vendor::query()->create([
        'code' => 'V-1', 'name' => 'Acme Kenya', 'bank_name' => 'Equity Bank', 'bank_account_number' => '0240291234567',
    ]));
});

it('encrypts bank details at rest', function () {
    $raw = DB::table('vendors')->where('id', $this->vendor->id)->value('bank_account_number');

    expect($raw)->not->toContain('0240291234567')
        ->and($this->vendor->fresh()->bank_account_number)->toBe('0240291234567');
});

it('never serialises bank details', function () {
    expect($this->vendor->fresh()->toArray())->not->toHaveKey('bank_account_number');
});

it('masks bank details for roles without the payment permission', function () {
    actingInEntity($this->admin, $this->kenya);

    Livewire::test(ManageVendors::class)
        ->mountTableAction('edit', $this->vendor)
        ->assertTableActionDataSet(fn (array $data) => ! array_key_exists('bank_account_number', $data) || $data['bank_account_number'] === null)
        ->assertDontSee('0240291234567')
        ->assertSee('****4567');
});

it('does not wipe bank details when a non-payment user edits the vendor', function () {
    actingInEntity($this->admin, $this->kenya);

    Livewire::test(ManageVendors::class)
        ->callTableAction('edit', $this->vendor, ['name' => 'Acme Kenya Ltd', 'bank_account_number' => '999'])
        ->assertHasNoTableActionErrors();

    $fresh = $this->vendor->fresh();
    expect($fresh->name)->toBe('Acme Kenya Ltd')
        ->and($fresh->bank_account_number)->toBe('0240291234567');
});

it('lets payment roles see and change bank details, audited with masked values', function () {
    actingInEntity($this->payments, $this->kenya);

    Livewire::test(ManageVendors::class)
        ->mountTableAction('edit', $this->vendor)
        ->assertTableActionDataSet(['bank_account_number' => '0240291234567'])
        ->setTableActionData(['bank_account_number' => '1111222233334444'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($this->vendor->fresh()->bank_account_number)->toBe('1111222233334444');

    $log = Activity::query()->where('event', 'bank_details_changed')->latest('id')->firstOrFail();
    expect($log->getProperty('old.bank_account_number'))->toBe('****4567')
        ->and($log->getProperty('new.bank_account_number'))->toBe('****4444')
        ->and(json_encode($log->toArray()))->not->toContain('1111222233334444')
        ->and(json_encode(Activity::query()->get()->toArray()))->not->toContain('0240291234567');
});

it('lets payment roles change bank details only', function () {
    actingInEntity($this->payments, $this->kenya);

    Livewire::test(ManageVendors::class)
        ->assertTableActionHidden('toggleActive', $this->vendor)
        ->callTableAction('edit', $this->vendor, ['name' => 'Renamed by payments', 'bank_name' => 'KCB Bank'])
        ->assertHasNoTableActionErrors();

    $fresh = $this->vendor->fresh();
    expect($fresh->name)->toBe('Acme Kenya')->and($fresh->bank_name)->toBe('KCB Bank');
});

it('leaves bank columns out of exports for non-payment roles', function () {
    actingInEntity($this->admin, $this->kenya);
    $response = app(ExportSheet::class)->handle('vendors', $this->admin);
    $adminRows = readXlsx($response->getFile()->getPathname());

    actingInEntity($this->payments, $this->kenya);
    $this->payments->givePermissionTo('masterdata.export');
    $response = app(ExportSheet::class)->handle('vendors', $this->payments->fresh());
    $paymentRows = readXlsx($response->getFile()->getPathname());

    expect($adminRows[0])->not->toContain('bank_account_number')
        ->and(json_encode($adminRows))->not->toContain('0240291234567')
        ->and($paymentRows[0])->toContain('bank_account_number')
        ->and(json_encode($paymentRows))->toContain('0240291234567');
});
