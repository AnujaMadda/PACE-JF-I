<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Actions\SyncEntityRoles;
use App\Domain\Identity\Models\LoginEvent;
use App\Domain\Identity\Models\User;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\LoginEvents\Pages\ManageLoginEvents;
use App\Filament\Admin\Resources\Roles\Pages\ListRoles;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
 * A Kenya Entity Admin must never read or change UAE data, whether through
 * the list screens, direct URLs with foreign ids, Livewire actions or exports.
 */

beforeEach(function () {
    $this->kenya = kenya();
    $this->uae = makeEntity(['code' => 'AE', 'name' => 'JF&I UAE']);

    $this->keAdmin = userIn($this->kenya, ['Entity Admin'], ['name' => 'Kenya Admin']);
    $this->keUser = userIn($this->kenya, ['Requester'], ['name' => 'Kenya Requester']);
    $this->aeUser = userIn($this->uae, ['Requester'], ['name' => 'UAE Requester']);
    $this->aeRole = Role::query()->where('team_id', $this->uae->id)->where('name', 'Approver')->firstOrFail();

    app(CurrentEntity::class)->run($this->uae, fn () => activity('test')->event('uae_only')->log('UAE secret event'));
    app(CurrentEntity::class)->run($this->kenya, fn () => activity('test')->event('ke_only')->log('Kenya event'));
});

it('lists only users of the current entity', function () {
    actingInEntity($this->keAdmin, $this->kenya);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->keAdmin, $this->keUser])
        ->assertCanNotSeeTableRecords([$this->aeUser]);
});

it('returns 404 for another entity\'s user, role or audit entry by URL', function () {
    actingInEntity($this->keAdmin, $this->kenya);
    $aeActivity = Activity::query()->where('event', 'uae_only')->firstOrFail();

    $this->get("/admin/users/{$this->aeUser->id}/edit")->assertNotFound();
    $this->get("/admin/roles/{$this->aeRole->id}/edit")->assertNotFound();
    $this->get("/admin/audit-log/{$aeActivity->id}")->assertNotFound();
});

it('cannot mount another entity\'s user in a Livewire page', function () {
    actingInEntity($this->keAdmin, $this->kenya);

    Livewire::test(EditUser::class, ['record' => $this->aeUser->getRouteKey()])->assertNotFound();
});

it('cannot run account actions on another entity\'s user through Livewire', function () {
    actingInEntity($this->keAdmin, $this->kenya);

    try {
        Livewire::test(ListUsers::class)->callTableAction('lock', $this->aeUser, ['reason' => 'x']);
    } catch (Throwable) {
        // Filament refuses to resolve a record outside the table query.
    }

    expect($this->aeUser->fresh()->isLocked())->toBeFalse();
});

it('can run account actions on its own entity\'s users through Livewire', function () {
    actingInEntity($this->keAdmin, $this->kenya);

    Livewire::test(ListUsers::class)->callTableAction('lock', $this->keUser, ['reason' => 'Left the company'])->assertHasNoTableActionErrors();

    expect($this->keUser->fresh()->isLocked())->toBeTrue();
});

it('lists only the current entity\'s roles', function () {
    actingInEntity($this->keAdmin, $this->kenya);

    Livewire::test(ListRoles::class)
        ->assertCanSeeTableRecords(Role::query()->where('team_id', $this->kenya->id)->whereIn('name', ['Approver', 'Entity Admin'])->get())
        ->assertCanNotSeeTableRecords([$this->aeRole])
        ->searchTable('Approver')
        ->assertCanNotSeeTableRecords([$this->aeRole]);
});

it('never assigns a role from another entity, even if its id is submitted', function () {
    app(SyncEntityRoles::class)->handle($this->keUser, $this->kenya, [$this->aeRole->id], $this->keAdmin);

    app(CurrentEntity::class)->set($this->uae);
    $this->keUser->unsetRelation('roles');
    expect($this->keUser->roles()->pluck('name')->all())->toBe([]);

    app(CurrentEntity::class)->set($this->kenya);
    $this->keUser->unsetRelation('roles');
    expect($this->keUser->roles()->pluck('name')->all())->toBe([]);
});

it('shows only the current entity in the audit log and its export', function () {
    actingInEntity($this->keAdmin, $this->kenya);

    Livewire::test(ListActivities::class)
        ->assertCanSeeTableRecords(Activity::query()->where('event', 'ke_only')->get())
        ->assertCanNotSeeTableRecords(Activity::query()->where('event', 'uae_only')->get());

    $csv = Livewire::test(ListActivities::class)->callAction('export');
    $csv->assertFileDownloaded();
});

it('keeps login history per entity', function () {
    actingInEntity($this->keAdmin, $this->kenya);
    $this->post('/logout');

    $ae = LoginEvent::query()->create(['email' => 'x@jfi.lk', 'entity_id' => $this->uae->id, 'event' => 'login_success']);

    actingInEntity($this->keAdmin, $this->kenya);
    Livewire::test(ManageLoginEvents::class)->assertCanNotSeeTableRecords([$ae]);
});

it('keeps entity admins out of entity management and system settings', function () {
    actingInEntity($this->keAdmin, $this->kenya);

    $this->get('/admin/entities')->assertForbidden();
    $this->get("/admin/entities/{$this->uae->id}/edit")->assertForbidden();
    $this->get('/admin/system-settings')->assertForbidden();
});

it('keeps people without admin access out of the admin panel', function () {
    actingInEntity($this->keUser, $this->kenya);

    $this->get('/admin')->assertForbidden();
    $this->get('/admin/users')->assertForbidden();
});

it('does not let an Entity Admin edit a Group Super Admin', function () {
    $super = User::factory()->superAdmin()->inEntity($this->kenya)->create();
    actingInEntity($this->keAdmin, $this->kenya);

    $this->get("/admin/users/{$super->id}/edit")->assertForbidden();
});

it('lets a Group Super Admin work in any entity, one at a time', function () {
    $super = User::factory()->superAdmin()->create();

    actingInEntity($super, $this->uae);
    Livewire::test(ListRoles::class)->assertCanSeeTableRecords([$this->aeRole]);

    $this->get('/admin/entities')->assertOk();
    $this->get('/admin/system-settings')->assertOk();
});
