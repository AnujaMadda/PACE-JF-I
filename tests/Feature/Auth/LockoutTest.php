<?php

use App\Domain\Identity\Actions\UnlockUser;
use App\Domain\Identity\Enums\LoginEventType;
use App\Domain\Identity\Models\LoginEvent;
use Database\Factories\UserFactory;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->kenya = kenya();
    $this->user = userIn($this->kenya, [], ['email' => 'locked@jfi.lk']);
});

function attempt(string $password): TestResponse
{
    return test()->from('/login')->post('/login', [
        'email' => 'locked@jfi.lk',
        'password' => $password,
        'entity_id' => kenya()->id,
    ]);
}

it('locks the account after 5 failed attempts for 15 minutes', function () {
    foreach (range(1, 5) as $i) {
        attempt('Wrong-Password-99!');
    }

    $user = $this->user->fresh();
    expect($user->isLocked())->toBeTrue()
        ->and($user->locked_until->diffInMinutes(now(), true))->toBeGreaterThan(14.9)
        ->and(LoginEvent::query()->where('event', LoginEventType::Locked)->count())->toBe(1);

    // Even the correct password is refused while locked, with the same message.
    attempt(UserFactory::PASSWORD)->assertSessionHasErrors(['email' => genericLoginError()]);
    $this->assertGuest();
    expect(LoginEvent::query()->latest('id')->value('reason'))->toBe('locked');
});

it('does not lock before the limit and resets the count on success', function () {
    foreach (range(1, 4) as $i) {
        attempt('Wrong-Password-99!');
    }

    expect($this->user->fresh()->isLocked())->toBeFalse();

    attempt(UserFactory::PASSWORD)->assertRedirect(route('dashboard'));
    expect($this->user->fresh()->failed_login_attempts)->toBe(0);
});

it('unlocks automatically when the lockout period ends', function () {
    foreach (range(1, 5) as $i) {
        attempt('Wrong-Password-99!');
    }

    $this->travel(16)->minutes();

    attempt(UserFactory::PASSWORD)->assertRedirect(route('dashboard'));
});

it('restarts the count after an expired lockout', function () {
    foreach (range(1, 5) as $i) {
        attempt('Wrong-Password-99!');
    }
    $this->travel(16)->minutes();

    attempt('Wrong-Password-99!');

    expect($this->user->fresh())->isLocked()->toBeFalse()->failed_login_attempts->toBe(1);
});

it('uses the configured attempt limit and duration', function () {
    setting('security.lockout_max_attempts', 3);
    setting('security.lockout_minutes', 60);

    foreach (range(1, 3) as $i) {
        attempt('Wrong-Password-99!');
    }

    $this->travel(30)->minutes();
    expect($this->user->fresh()->isLocked())->toBeTrue();
});

it('can be unlocked by an admin', function () {
    foreach (range(1, 5) as $i) {
        attempt('Wrong-Password-99!');
    }
    $admin = userIn($this->kenya, ['Entity Admin']);

    app(UnlockUser::class)->handle($this->user->fresh(), $admin);

    attempt(UserFactory::PASSWORD)->assertRedirect(route('dashboard'));
});
