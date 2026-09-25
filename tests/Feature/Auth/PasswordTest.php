<?php

use App\Domain\Identity\Actions\SetPassword;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->kenya = kenya();
    $this->user = userIn($this->kenya, [], ['email' => 'pw@jfi.lk']);
});

function changePassword(string $new, string $current = UserFactory::PASSWORD): TestResponse
{
    return test()->from('/password/change')->put('/password/change', [
        'current_password' => $current,
        'password' => $new,
        'password_confirmation' => $new,
    ]);
}

it('rejects passwords that break the policy', function (string $password) {
    actingInEntity($this->user, $this->kenya);

    changePassword($password)->assertSessionHasErrors('password');
})->with([
    'too short' => 'Sh0rt-pass!',
    'no symbol' => 'NoSymbolPassword123',
    'no number' => 'No-Number-Password!',
    'no upper case' => 'no-upper-case-123!',
]);

it('accepts a compliant password and keeps the user signed in', function () {
    actingInEntity($this->user, $this->kenya);

    changePassword('Another-Good-Pass-1!')->assertRedirect(route('dashboard'));

    expect(Hash::check('Another-Good-Pass-1!', $this->user->fresh()->password))->toBeTrue();
    $this->assertAuthenticated();
});

it('requires the current password', function () {
    actingInEntity($this->user, $this->kenya);

    changePassword('Another-Good-Pass-1!', 'not-my-password')->assertSessionHasErrors('current_password');
});

it('blocks reuse of the last five passwords', function () {
    $set = app(SetPassword::class);
    $passwords = ['History-Pass-01!', 'History-Pass-02!', 'History-Pass-03!', 'History-Pass-04!'];
    foreach ($passwords as $p) {
        $set->handle($this->user->fresh(), $p, 'test');
    }
    // Last five: factory password, 01, 02, 03, 04 (current).
    actingInEntity($this->user, $this->kenya);

    foreach (['History-Pass-04!', 'History-Pass-02!', 'History-Pass-01!'] as $reused) {
        changePassword($reused, 'History-Pass-04!')->assertSessionHasErrors(['password' => 'This password was used recently. Choose a different one.']);
    }

    changePassword('History-Pass-05!', 'History-Pass-04!')->assertSessionHasNoErrors();
    // The factory password is now six changes old and may be used again.
    changePassword(UserFactory::PASSWORD, 'History-Pass-05!')->assertSessionHasNoErrors();
});

it('sends users with an expired password to the change page when expiry is on', function () {
    actingInEntity($this->user, $this->kenya);
    $this->get('/dashboard')->assertOk();

    setting('security.password_expiry_days', 90);
    $this->travel(91)->days();
    $this->withSession(['last_activity_at' => now()->getTimestamp()]);

    $this->get('/dashboard')->assertRedirect(route('password.change'));
    $this->get('/password/change')->assertOk();
});

it('emails a reset link to active users and says nothing about others', function () {
    Notification::fake();
    $neutral = 'If an active account uses this email, we have sent a password reset link.';

    $this->post('/forgot-password', ['email' => 'pw@jfi.lk'])->assertSessionHas('status', $neutral);
    $this->post('/forgot-password', ['email' => 'ghost@jfi.lk'])->assertSessionHas('status', $neutral);

    Notification::assertSentTo($this->user, ResetPasswordNotification::class);
    Notification::assertCount(1);
});

it('resets the password with a valid token and applies the policy and history', function () {
    Notification::fake();
    $this->post('/forgot-password', ['email' => 'pw@jfi.lk']);

    $token = null;
    Notification::assertSentTo($this->user, ResetPasswordNotification::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->post('/reset-password', ['token' => $token, 'email' => 'pw@jfi.lk', 'password' => UserFactory::PASSWORD, 'password_confirmation' => UserFactory::PASSWORD])
        ->assertSessionHasErrors('password');

    $this->post('/reset-password', ['token' => $token, 'email' => 'pw@jfi.lk', 'password' => 'Reset-Good-Pass-9!', 'password_confirmation' => 'Reset-Good-Pass-9!'])
        ->assertRedirect(route('login'));

    expect(Hash::check('Reset-Good-Pass-9!', $this->user->fresh()->password))->toBeTrue()
        ->and($this->user->passwordHistories()->count())->toBe(1);
});

it('rejects an invalid reset token', function () {
    $this->post('/reset-password', ['token' => 'nope', 'email' => 'pw@jfi.lk', 'password' => 'Reset-Good-Pass-9!', 'password_confirmation' => 'Reset-Good-Pass-9!'])
        ->assertSessionHasErrors('email');
});
