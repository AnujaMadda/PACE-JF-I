<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\ActivationLinkNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Notification::fake();
    $this->kenya = kenya();
});

function invited(string $email = 'new.starter@jfi.lk'): User
{
    return User::factory()->invited()->inEntity(kenya())->create(['email' => $email])->fresh();
}

function activationUrlFor(User $user): string
{
    $url = null;

    Notification::assertSentTo($user, ActivationLinkNotification::class, function (ActivationLinkNotification $n) use (&$url) {
        $url = $n->url;

        return true;
    });

    return $url;
}

$neutral = 'If this email is registered for PACE, we have sent a link to set your password. Please check your inbox.';

it('emails an invited user a signed activation link from the Sign Up page', function () use ($neutral) {
    $user = invited();

    $this->post('/sign-up', ['email' => 'New.Starter@JFI.lk'])->assertSessionHas('status', $neutral);

    $url = activationUrlFor($user);
    expect($url)->toContain('/activate/'.$user->id)->toContain('signature=')->toContain('expires=');
});

it('gives the same neutral answer and sends nothing for unknown emails', function () use ($neutral) {
    $this->post('/sign-up', ['email' => 'stranger@jfi.lk'])->assertSessionHas('status', $neutral);

    Notification::assertNothingSent();
    expect(User::query()->where('email', 'stranger@jfi.lk')->exists())->toBeFalse();
});

it('sends nothing to an invited email outside the allowed domains', function () use ($neutral) {
    invited('someone@gmail.com');

    $this->post('/sign-up', ['email' => 'someone@gmail.com'])->assertSessionHas('status', $neutral);

    Notification::assertNothingSent();
});

it('sends nothing to users who are already active', function () {
    userIn($this->kenya, [], ['email' => 'active@jfi.lk']);

    $this->post('/sign-up', ['email' => 'active@jfi.lk']);

    Notification::assertNothingSent();
});

it('activates the account when the password is set, then allows sign in', function () {
    $user = invited();
    $this->post('/sign-up', ['email' => $user->email]);
    $url = activationUrlFor($user);

    $this->get($url)->assertOk()->assertSee('Set your password');

    $this->post($url, ['password' => 'Brand-New-Pass-77!', 'password_confirmation' => 'Brand-New-Pass-77!'])
        ->assertRedirect(route('login'));

    $user->refresh();
    expect($user->status)->toBe(UserStatus::Active)
        ->and($user->activated_at)->not->toBeNull()
        ->and($user->password_changed_at)->not->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'Brand-New-Pass-77!', 'entity_id' => $this->kenya->id])
        ->assertRedirect(route('dashboard'));
});

it('enforces the password policy on activation', function () {
    $user = invited();
    $this->post('/sign-up', ['email' => $user->email]);
    $url = activationUrlFor($user);

    $this->from($url)->post($url, ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');

    expect($user->fresh()->status)->toBe(UserStatus::Invited);
});

it('rejects a tampered activation link', function () {
    $user = invited();
    $other = invited('other@jfi.lk');
    $this->post('/sign-up', ['email' => $user->email]);
    $url = activationUrlFor($user);

    $this->get(str_replace('/activate/'.$user->id, '/activate/'.$other->id, $url))->assertForbidden();
});

it('rejects an expired activation link', function () {
    $user = invited();
    $this->post('/sign-up', ['email' => $user->email]);
    $url = activationUrlFor($user);

    $this->travel(61)->minutes();

    $this->get($url)->assertForbidden();
});

it('invalidates older links when a new one is issued, and links once used', function () {
    $user = invited();
    $this->post('/sign-up', ['email' => $user->email]);
    $first = activationUrlFor($user);

    $this->travel(2)->seconds();
    $this->post('/sign-up', ['email' => $user->email]);
    $sent = Notification::sent($user, ActivationLinkNotification::class);
    $second = $sent->last()->url;

    $this->get($first)->assertRedirect(route('sign-up'));

    $this->post($second, ['password' => 'Brand-New-Pass-77!', 'password_confirmation' => 'Brand-New-Pass-77!']);
    $this->get($second)->assertRedirect(route('sign-up'));
});

it('creates a pending account only when self-registration is on', function () {
    $this->post('/sign-up', ['email' => 'walk.in@jfi.lk', 'name' => 'Walk In']);
    expect(User::query()->where('email', 'walk.in@jfi.lk')->exists())->toBeFalse();

    setting('auth.self_registration', true);
    $this->post('/sign-up', ['email' => 'walk.in@jfi.lk', 'name' => 'Walk In']);
    $this->post('/sign-up', ['email' => 'outsider@gmail.com', 'name' => 'Outsider']);

    expect(User::query()->where('email', 'walk.in@jfi.lk')->value('status'))->toBe(UserStatus::PendingApproval)
        ->and(User::query()->where('email', 'outsider@gmail.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('never builds activation links for non-invited users', function () {
    $user = userIn($this->kenya);

    $url = URL::temporarySignedRoute('activation.show', now()->addHour(), ['user' => $user->id, 'issued' => now()->getTimestamp()]);

    $this->get($url)->assertRedirect(route('sign-up'));
});
