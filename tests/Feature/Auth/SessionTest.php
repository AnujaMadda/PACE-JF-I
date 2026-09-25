<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Identity\Actions\DeactivateUser;
use App\Domain\Identity\Actions\ForceLogout;
use App\Domain\Identity\Actions\LockUser;
use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\LoginEvent;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->kenya = kenya();
    $this->uae = makeEntity(['code' => 'AE', 'name' => 'JF&I UAE']);
    $this->user = userIn($this->kenya, ['Requester']);
});

it('signs the user out after the idle timeout', function () {
    actingInEntity($this->user, $this->kenya);
    $this->get('/dashboard')->assertOk();

    $this->travel(31)->minutes();

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
    expect(LoginEvent::query()->latest('id')->value('event'))->toBe(LoginEventType::IdleTimeout);
});

it('keeps the session alive while the user is active', function () {
    actingInEntity($this->user, $this->kenya);

    foreach (range(1, 3) as $i) {
        $this->travel(20)->minutes();
        $this->get('/dashboard')->assertOk();
    }
});

it('uses the configured idle timeout', function () {
    setting('security.session_idle_minutes', 10);
    actingInEntity($this->user, $this->kenya);

    $this->travel(11)->minutes();

    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('ends the session on the next request after deactivation or lock', function (string $action) {
    $admin = userIn($this->kenya, ['Entity Admin']);
    $this->post('/login', ['email' => $this->user->email, 'password' => UserFactory::PASSWORD, 'entity_id' => $this->kenya->id]);
    $this->get('/dashboard')->assertOk();

    app($action)->handle($this->user->fresh(), $admin, 'test');
    $this->app['auth']->forgetGuards(); // the next request loads the user from the database, as in production

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
})->with([DeactivateUser::class, LockUser::class]);

it('ends the session when access to the entity is removed', function () {
    actingInEntity($this->user, $this->kenya);
    $this->user->entities()->detach($this->kenya);

    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('switches to another entity the user has access to and audits it', function () {
    $this->user->entities()->attach($this->uae, ['is_home' => false]);
    actingInEntity($this->user, $this->kenya);

    $this->post('/entity/switch', ['entity_id' => $this->uae->id])->assertRedirect(route('dashboard'));

    expect(session('entity_id'))->toBe($this->uae->id);
    $this->get('/dashboard')->assertOk()->assertSee('JF&amp;I UAE', false);

    $log = Activity::query()->where('event', 'entity_switched')->latest('id')->first();
    expect($log->entity_id)->toBe($this->uae->id)
        ->and($log->getProperty('from_entity_id'))->toBe($this->kenya->id);
});

it('refuses to switch to an entity without access', function () {
    actingInEntity($this->user, $this->kenya);

    $this->post('/entity/switch', ['entity_id' => $this->uae->id])->assertForbidden();

    expect(session('entity_id'))->toBe($this->kenya->id);
});

it('ignores a tampered session entity the user cannot access', function () {
    actingInEntity($this->user, $this->kenya);
    $this->withSession(['entity_id' => $this->uae->id]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('force logout removes every stored session of the user', function () {
    DB::table('sessions')->insert([
        ['id' => 'a', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'b', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'c', 'user_id' => null, 'payload' => '', 'last_activity' => time()],
    ]);

    expect(app(ForceLogout::class)->handle($this->user))->toBe(2)
        ->and(DB::table('sessions')->count())->toBe(1);
});

it('sets security headers on every page', function () {
    $this->get('/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Request-Id');

    expect($this->get('/login')->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});
