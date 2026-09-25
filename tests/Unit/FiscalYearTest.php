<?php

use App\Domain\Core\Models\Entity;
use Carbon\CarbonImmutable;

function entityWith(int $startMonth, string $timezone = 'Africa/Nairobi'): Entity
{
    return new Entity(['fy_start_month' => $startMonth, 'timezone' => $timezone]);
}

it('labels an April–March year by the year it ends in', function (string $utc, int $fy) {
    expect(entityWith(4)->fiscalYearFor(CarbonImmutable::parse($utc, 'UTC')))->toBe($fy);
})->with([
    'today (FY27)' => ['2026-09-25 09:00', 2027],
    'first day of FY27' => ['2026-04-01 09:00', 2027],
    'last day of FY26' => ['2026-03-31 09:00', 2026],
    'last day of FY27' => ['2027-03-31 12:00', 2027],
    'first day of FY28' => ['2027-04-01 12:00', 2028],
]);

it('uses the entity\'s timezone at the year boundary', function () {
    // 22:30 UTC on 31 March is 01:30 on 1 April in Nairobi (UTC+3).
    expect(entityWith(4)->fiscalYearFor(CarbonImmutable::parse('2027-03-31 22:30', 'UTC')))->toBe(2028)
        ->and(entityWith(4, 'UTC')->fiscalYearFor(CarbonImmutable::parse('2027-03-31 22:30', 'UTC')))->toBe(2027);
});

it('handles calendar-year entities', function () {
    expect(entityWith(1)->fiscalYearFor(CarbonImmutable::parse('2026-09-25', 'UTC')))->toBe(2026);
});

it('matches allowed email domains case-insensitively', function () {
    $entity = new Entity(['allowed_email_domains' => ['jfi.lk']]);

    expect($entity->allowsEmail('Someone@JFI.LK'))->toBeTrue()
        ->and($entity->allowsEmail('someone@jfi.lk.evil.com'))->toBeFalse()
        ->and($entity->allowsEmail('someone@gmail.com'))->toBeFalse();
});
