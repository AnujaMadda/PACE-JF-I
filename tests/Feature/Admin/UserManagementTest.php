<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Models\Entity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\ActivationLinkNotification;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use App\Filament\Admin\Resources\Entities\Pages\CreateEntity;
use App\Filament\Admin\Resources\Roles\Pages\CreateRole;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    $this->kenya = kenya();
    $this->admin = userIn($this->kenya, ['Entity Admin']);
    actingInEntity($this->admin, $this->kenya);
});

function roleId(string $name): int
{
    return Role::query()->where('team_id', kenya()->id)->where('name', $name)->value('id');
}

it('invites a user with entity access and roles, and emails the activation link', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Grace Wanjiru',
            'email' => 'grace.wanjiru@jfi.lk',
            'designation' => 'Maintenance Engineer',
            'role_ids' => [roleId('Requester')],
            'is_home' => true,
            'send_activation_link' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'grace.wanjiru@jfi.lk')->firstOrFail();

    expect($user->status)->toBe(UserStatus::Invited)
        ->and($user->password)->toBeNull()
        ->and($user->entities->pluck('id')->all())->toBe([$this->kenya->id])
        ->and((bool) $user->entities->first()->pivot->is_home)->toBeTrue();

    app(CurrentEntity::class)->set($this->kenya);
    expect($user->roles()->pluck('name')->all())->toBe(['Requester']);

    Notification::assertSentTo($user, ActivationLinkNotification::class);
    expect(Activity::query()->where('event', 'invited')->where('subject_id', $user->id)->where('entity_id', $this->kenya->id)->exists())->toBeTrue();
});

it('rejects emails outside the entity\'s allowed domains', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Outsider', 'email' => 'outsider@gmail.com'])
        ->call('create')
        ->assertHasFormErrors(['email']);

    expect(User::query()->where('email', 'outsider@gmail.com')->exists())->toBeFalse();
});

it('rejects duplicate emails', function () {
    userIn($this->kenya, [], ['email' => 'taken@jfi.lk']);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Dup', 'email' => 'taken@jfi.lk'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('changes roles and records before and after values', function () {
    $user = userIn($this->kenya, ['Requester']);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertFormSet(['role_ids' => [roleId('Requester')]])
        ->fillForm(['designation' => 'Senior Engineer', 'role_ids' => [roleId('Requester'), roleId('Validator')]])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = Activity::query()->where('event', 'roles_changed')->where('subject_id', $user->id)->latest('id')->firstOrFail();
    expect($log->getProperty('old'))->toBe(['Requester'])
        ->and($log->getProperty('new'))->toBe(['Requester', 'Validator']);

    $update = Activity::query()->where('event', 'updated')->where('subject_id', $user->id)->latest('id')->firstOrFail();
    expect($update->attribute_changes['old']['designation'])->not->toBe('Senior Engineer')
        ->and($update->attribute_changes['attributes']['designation'])->toBe('Senior Engineer');
});

it('does not let an Entity Admin grant Group Super Admin', function () {
    $user = userIn($this->kenya);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['is_group_super_admin' => true])
        ->call('save');

    expect($user->fresh()->is_group_super_admin)->toBeFalse();
});

it('sends a password reset link without the admin ever seeing a password', function () {
    $user = userIn($this->kenya);

    Livewire::test(ListUsers::class)->callTableAction('sendPasswordReset', $user);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('resends the activation link to invited users only', function () {
    $invited = User::factory()->invited()->inEntity($this->kenya)->create();
    $active = userIn($this->kenya);

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('resendInvitation', $invited)
        ->assertTableActionHidden('resendInvitation', $active)
        ->callTableAction('resendInvitation', $invited);

    Notification::assertSentTo($invited, ActivationLinkNotification::class);
});

it('locks, unlocks, deactivates and reactivates with an audit trail', function () {
    $user = userIn($this->kenya);

    Livewire::test(ListUsers::class)->callTableAction('lock', $user, ['reason' => 'Suspicious activity']);
    expect($user->fresh()->isLocked())->toBeTrue();

    Livewire::test(ListUsers::class)->callTableAction('unlock', $user);
    expect($user->fresh()->isLocked())->toBeFalse();

    Livewire::test(ListUsers::class)->callTableAction('deactivate', $user, ['reason' => 'Left the company']);
    expect($user->fresh()->status)->toBe(UserStatus::Deactivated);

    Livewire::test(ListUsers::class)->callTableAction('reactivate', $user);
    expect($user->fresh()->status)->toBe(UserStatus::Active);

    expect(Activity::query()->where('subject_id', $user->id)->where('subject_type', $user->getMorphClass())
        ->whereIn('event', ['locked', 'unlocked', 'deactivated', 'reactivated'])->count())->toBe(4);
});

it('does not let admins lock or deactivate themselves', function () {
    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('lock', $this->admin)
        ->assertTableActionHidden('deactivate', $this->admin);
});

it('lets an Entity Admin remove access to their entity but not deactivate a multi-entity user', function () {
    $uae = makeEntity(['code' => 'AE']);
    $user = userIn($this->kenya);
    $user->entities()->attach($uae);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('deactivate', $user)
        ->callTableAction('revokeAccess', $user);

    expect($user->fresh()->entities->pluck('code')->all())->toBe(['AE'])
        ->and($user->fresh()->status)->toBe(UserStatus::Active);
});

it('approves a self-registration with roles and sends the activation link', function () {
    setting('auth.self_registration', true);
    $this->post('/logout');
    $this->post('/sign-up', ['email' => 'walk.in@jfi.lk', 'name' => 'Walk In']);
    $pending = User::query()->where('email', 'walk.in@jfi.lk')->firstOrFail();

    actingInEntity($this->admin, $this->kenya);
    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$pending])
        ->callTableAction('approveRegistration', $pending, ['role_ids' => [roleId('Requester')]]);

    expect($pending->fresh()->status)->toBe(UserStatus::Invited);
    Notification::assertSentTo($pending, ActivationLinkNotification::class);
});

it('creates a role for the current entity only', function () {
    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'Plant Engineer'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Role::query()->where('name', 'Plant Engineer')->value('team_id'))->toBe($this->kenya->id);
});

it('provisions the default roles when a Group Super Admin adds an entity', function () {
    $super = User::factory()->superAdmin()->create();
    actingInEntity($super, $this->kenya);

    Livewire::test(CreateEntity::class)
        ->fillForm([
            'code' => 'AE',
            'name' => 'JF&I Packaging UAE',
            'country' => 'United Arab Emirates',
            'base_currency' => 'AED',
            'timezone' => 'Asia/Dubai',
            'fy_start_month' => 4,
            'request_prefix' => 'AE',
            'allowed_email_domains' => ['jfi.lk'],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $uae = Entity::query()->where('code', 'AE')->firstOrFail();
    expect(Role::query()->where('team_id', $uae->id)->count())->toBe(count(config('pace.default_roles')));
});
