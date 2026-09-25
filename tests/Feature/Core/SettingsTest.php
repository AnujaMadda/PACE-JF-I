<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Settings\Settings;
use App\Domain\Identity\Models\User;
use App\Filament\Admin\Pages\SystemSettings;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('returns registry defaults until a value is stored', function () {
    $settings = app(Settings::class);

    expect($settings->int('security.lockout_max_attempts'))->toBe(5)
        ->and($settings->int('security.session_idle_minutes'))->toBe(30)
        ->and($settings->bool('auth.self_registration'))->toBeFalse();
});

it('stores, casts and audits changes', function () {
    $settings = app(Settings::class);

    $settings->set('security.lockout_max_attempts', '7');

    expect($settings->int('security.lockout_max_attempts'))->toBe(7);
    $log = Activity::query()->where('event', 'setting_changed')->latest('id')->firstOrFail();
    expect($log->getProperty('old'))->toBe(5)->and($log->getProperty('new'))->toBe(7);
});

it('validates values against the registry rules', function () {
    app(Settings::class)->set('security.password_min_length', 8);
})->throws(ValidationException::class);

it('refuses per-entity overrides of group-wide settings', function () {
    app(Settings::class)->set('security.lockout_minutes', 30, kenya());
})->throws(InvalidArgumentException::class);

it('refuses unknown keys', function () {
    app(Settings::class)->get('nope.nothing');
})->throws(InvalidArgumentException::class);

it('lets a Group Super Admin edit system settings from the admin panel', function () {
    $super = User::factory()->superAdmin()->create();
    actingInEntity($super, kenya());

    Livewire::test(SystemSettings::class)
        ->assertSchemaStateSet(['security__session_idle_minutes' => 30], 'form')
        ->fillForm(['security__session_idle_minutes' => 45, 'auth__self_registration' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Settings::class)->int('security.session_idle_minutes'))->toBe(45)
        ->and(app(Settings::class)->bool('auth.self_registration'))->toBeTrue();
});
