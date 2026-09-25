<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\LoginEvent;
use App\Domain\Identity\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->kenya = kenya();
    $this->uae = makeEntity(['code' => 'AE', 'name' => 'JF&I UAE', 'timezone' => 'Asia/Dubai']);
});

function login(array $overrides = []): TestResponse
{
    return test()->from('/login')->post('/login', array_merge([
        'email' => 'requester@jfi.lk',
        'password' => UserFactory::PASSWORD,
        'entity_id' => kenya()->id,
    ], $overrides));
}

it('lists every active entity on the login page, but not inactive ones', function () {
    Entity::factory()->inactive()->create(['code' => 'BD', 'name' => 'JF&I Bangladesh']);

    $this->get('/login')
        ->assertOk()
        ->assertSee('JF&amp;I Packaging Kenya', false)
        ->assertSee('JF&amp;I UAE', false)
        ->assertDontSee('JF&amp;I Bangladesh', false)
        ->assertSee('Approvals at PACE');
});

it('signs in with email, password and an entity the user can access', function () {
    $user = userIn($this->kenya, ['Requester'], ['email' => 'requester@jfi.lk']);

    login()->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(session('entity_id'))->toBe($this->kenya->id)
        ->and($user->fresh()->last_login_at)->not->toBeNull();

    $event = LoginEvent::query()->latest('id')->first();
    expect($event->event)->toBe(LoginEventType::Success)
        ->and($event->entity_id)->toBe($this->kenya->id)
        ->and($event->user_id)->toBe($user->id);

    expect(Activity::query()->where('event', 'login_success')->where('entity_id', $this->kenya->id)->exists())->toBeTrue();

    $this->get('/dashboard')->assertOk()->assertSee('JF&amp;I Packaging Kenya', false);
});

it('fails with the same generic message whatever the reason', function (Closure $arrange, array $input, string $reason) {
    $arrange();

    if (isset($input['entity_code'])) {
        $input['entity_id'] = Entity::query()->where('code', $input['entity_code'])->value('id');
        unset($input['entity_code']);
    }

    login($input)->assertRedirect('/login')->assertSessionHasErrors(['email' => genericLoginError()]);

    $this->assertGuest();
    expect(LoginEvent::query()->latest('id')->first())
        ->event->toBe(LoginEventType::Failed)
        ->reason->toBe($reason);
})->with([
    'unknown email' => [fn () => null, ['email' => 'nobody@jfi.lk'], 'unknown_email'],
    'wrong password' => [fn () => userIn(kenya(), [], ['email' => 'requester@jfi.lk']), ['password' => 'Wrong-Password-99!'], 'bad_password'],
    'no access to entity' => [fn () => userIn(kenya(), [], ['email' => 'requester@jfi.lk']), ['entity_code' => 'AE'], 'no_entity_access'],
    'deactivated' => [fn () => User::factory()->deactivated()->inEntity(kenya())->create(['email' => 'requester@jfi.lk']), [], 'status_deactivated'],
    'inactive entity' => [function () {
        userIn(kenya(), [], ['email' => 'requester@jfi.lk']);
        kenya()->update(['is_active' => false]);
    }, [], 'no_entity_access'],
    'entity that does not exist' => [fn () => userIn(kenya(), [], ['email' => 'requester@jfi.lk']), ['entity_id' => 999999], 'no_entity_access'],
]);

it('treats an invited user with no password as a failed attempt', function () {
    User::factory()->invited()->inEntity($this->kenya)->create(['email' => 'requester@jfi.lk']);

    login()->assertSessionHasErrors(['email' => genericLoginError()]);

    expect(LoginEvent::query()->latest('id')->value('reason'))->toBe('bad_password');
});

it('lets a Group Super Admin sign in to any active entity', function () {
    User::factory()->superAdmin()->create(['email' => 'group@jfi.lk']);

    login(['email' => 'group@jfi.lk', 'entity_id' => $this->uae->id])->assertRedirect(route('dashboard'));

    expect(session('entity_id'))->toBe($this->uae->id);
});

it('lets a user with access to several entities choose which one to sign in to', function () {
    $user = userIn($this->kenya, [], ['email' => 'requester@jfi.lk']);
    $user->entities()->attach($this->uae, ['is_home' => false]);

    login(['entity_id' => $this->uae->id])->assertRedirect(route('dashboard'));

    expect(session('entity_id'))->toBe($this->uae->id);
});

it('regenerates the session id on login', function () {
    userIn($this->kenya, [], ['email' => 'requester@jfi.lk']);
    $this->get('/login');
    $before = session()->getId();

    login();

    expect(session()->getId())->not->toBe($before);
});

it('rate limits repeated sign-in attempts', function () {
    userIn($this->kenya, [], ['email' => 'requester@jfi.lk']);
    setting('security.lockout_max_attempts', 20);

    foreach (range(1, 10) as $i) {
        login(['password' => 'Wrong-Password-99!']);
    }

    login(['password' => 'Wrong-Password-99!'])->assertStatus(429);
});

it('redirects guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->get('/admin')->assertRedirect(route('login'));
});

it('signs out and records the logout', function () {
    $user = userIn($this->kenya);
    actingInEntity($user, $this->kenya);

    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(LoginEvent::query()->latest('id')->value('event'))->toBe(LoginEventType::Logout);
});
