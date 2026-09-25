<?php

use App\Domain\Core\Money\Contracts\ExchangeRateProvider;
use App\Domain\MasterData\Models\ExchangeRate;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->kenya = kenya();
    $this->fx = app(ExchangeRateProvider::class);

    inEntity($this->kenya, function () {
        ExchangeRate::query()->create(['from_currency' => 'USD', 'to_currency' => 'KES', 'rate' => '128.000000', 'effective_from' => '2026-08-01']);
        ExchangeRate::query()->create(['from_currency' => 'USD', 'to_currency' => 'KES', 'rate' => '129.250000', 'effective_from' => '2026-09-01']);
        ExchangeRate::query()->create(['from_currency' => 'USD', 'to_currency' => 'KES', 'rate' => '131.000000', 'effective_from' => '2026-10-01']);
        ExchangeRate::query()->create(['from_currency' => 'EUR', 'to_currency' => 'KES', 'rate' => '999.000000', 'effective_from' => '2026-09-01', 'is_active' => false]);
    });
});

it('uses the latest rate effective on the date', function (string $date, ?string $expected) {
    expect((string) $this->fx->rate($this->kenya, 'USD', 'KES', CarbonImmutable::parse($date)))->toBe((string) $expected);
})->with([
    'before any rate' => ['2026-07-31', ''],
    'first day of August rate' => ['2026-08-01', '128.000000'],
    'mid September' => ['2026-09-25', '129.250000'],
    'future rate applies from its date' => ['2026-10-01', '131.000000'],
]);

it('inverts the opposite pair when only that one exists', function () {
    expect((string) $this->fx->rate($this->kenya, 'KES', 'USD', CarbonImmutable::parse('2026-09-25')))->toBe('0.007737');
});

it('returns 1 for the same currency and ignores inactive rates', function () {
    expect((string) $this->fx->rate($this->kenya, 'KES', 'KES', now()))->toBe('1.000000')
        ->and($this->fx->rate($this->kenya, 'EUR', 'KES', CarbonImmutable::parse('2026-09-25')))->toBeNull();
});

it('never uses another entity\'s rates', function () {
    $uae = makeEntity(['code' => 'AE', 'base_currency' => 'AED']);

    expect($this->fx->rate($uae, 'USD', 'KES', CarbonImmutable::parse('2026-09-25')))->toBeNull();
});
