<?php

use App\Domain\Core\Money\DecimalCast;

it('parses strings and integers exactly, rounding half up', function (mixed $input, int $scale, string $expected) {
    expect((string) DecimalCast::normalise($input, $scale))->toBe($expected);
})->with([
    ['1234.5', 2, '1234.50'],
    ['1,234,567.899', 2, '1234567.90'],
    ['0.005', 2, '0.01'],
    ['-0.005', 2, '-0.01'],
    [42, 2, '42.00'],
    ['129.2500004', 6, '129.250000'],
    ['129.2500005', 6, '129.250001'],
    ['999999999999999.99', 2, '999999999999999.99'],
]);

it('refuses floats', function () {
    DecimalCast::normalise(0.1, 2);
})->throws(InvalidArgumentException::class);

it('refuses text that is not a number', function () {
    DecimalCast::normalise('12abc', 2);
})->throws(InvalidArgumentException::class);
