<?php

use App\Domain\Core\Settings\Settings;
use App\Domain\Identity\Actions\ProvisionEntityRoles;
use App\Filament\Admin\Pages\EntitySettings;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->kenya = kenya();
    $this->uae = makeEntity(['code' => 'AE']);
});

it('stores entity overrides without affecting other entities', function () {
    $settings = app(Settings::class);

    $settings->set('capex.minimum_quotations', 5, $this->kenya);

    expect($settings->int('capex.minimum_quotations', $this->kenya))->toBe(5)
        ->and($settings->int('capex.minimum_quotations', $this->uae))->toBe(3);
});

it('lets an entity admin edit their entity\'s settings', function () {
    actingInEntity(userIn($this->kenya, ['Entity Admin']), $this->kenya);

    Livewire::test(EntitySettings::class)
        ->assertSchemaStateSet(['capex__minimum_quotations' => 3], 'form')
        ->fillForm(['capex__minimum_quotations' => 2])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Settings::class)->int('capex.minimum_quotations', $this->kenya))->toBe(2);
});

it('keeps entity settings away from people without settings permission', function () {
    actingInEntity(userIn($this->kenya, ['Requester']), $this->kenya);

    $this->get('/admin/entity-settings')->assertForbidden();
});

it('rolls new permissions out to existing default roles without touching custom roles', function () {
    $approver = Role::query()->where('team_id', $this->kenya->id)->where('name', 'Payment Team')->firstOrFail();
    $approver->revokePermissionTo('vendors.view_bank_details');
    $custom = Role::query()->create(['name' => 'Custom', 'guard_name' => 'web', 'team_id' => $this->kenya->id]);

    app(ProvisionEntityRoles::class)->grantNewDefaults(['vendors.view_bank_details']);

    expect($approver->fresh()->hasPermissionTo('vendors.view_bank_details'))->toBeTrue()
        ->and($custom->fresh()->permissions)->toHaveCount(0);
});
