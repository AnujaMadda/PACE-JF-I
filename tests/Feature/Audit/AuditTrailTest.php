<?php

use App\Domain\Audit\Exceptions\AppendOnlyViolation;
use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\LoginEvent;

beforeEach(function () {
    $this->kenya = kenya();
});

it('refuses to update or delete audit records', function () {
    $activity = activity('test')->log('Something happened');

    expect(fn () => $activity->update(['description' => 'Rewritten']))->toThrow(AppendOnlyViolation::class)
        ->and(fn () => $activity->delete())->toThrow(AppendOnlyViolation::class);

    expect(Activity::query()->find($activity->id)->description)->toBe('Something happened');
});

it('refuses to update or delete login history', function () {
    $event = LoginEvent::query()->create(['email' => 'a@jfi.lk', 'event' => 'login_failed']);

    expect(fn () => $event->update(['reason' => 'x']))->toThrow(AppendOnlyViolation::class)
        ->and(fn () => $event->delete())->toThrow(AppendOnlyViolation::class);
});

it('stamps entity, ip address, user agent and request id on audit records', function () {
    $user = userIn($this->kenya, ['Entity Admin']);
    actingInEntity($user, $this->kenya);

    $this->withHeaders(['User-Agent' => 'PACE-Test/1.0', 'X-Request-Id' => 'req-12345678'])
        ->post('/logout');

    $log = Activity::query()->where('event', 'logout')->latest('id')->firstOrFail();

    expect($log->entity_id)->toBe($this->kenya->id)
        ->and($log->ip_address)->toBe('127.0.0.1')
        ->and($log->user_agent)->toBe('PACE-Test/1.0')
        ->and($log->request_id)->toBe('req-12345678')
        ->and($log->causer_id)->toBe($user->id);
});

it('logs model changes with before and after values', function () {
    app(CurrentEntity::class)->set($this->kenya);
    $this->kenya->update(['name' => 'JF&I Kenya Ltd']);

    $log = Activity::query()->where('subject_type', $this->kenya->getMorphClass())->where('event', 'updated')->latest('id')->firstOrFail();

    expect($log->attribute_changes['old']['name'])->toBe('JF&I Packaging Kenya')
        ->and($log->attribute_changes['attributes']['name'])->toBe('JF&I Kenya Ltd');
});
