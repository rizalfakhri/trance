<?php

use App\Data\UnitRate;

test('decimal rates are converted to nano dollars per million units', function (string $decimal, int $nano) {
    expect(UnitRate::fromDecimal($decimal)->nanoPerMillionUnits)->toBe($nano);
})->with([
    'whole dollars' => ['40', 40_000_000_000],
    'cents' => ['5.00', 5_000_000_000],
    'sub cent' => ['12.50', 12_500_000_000],
    'nine decimals' => ['0.000000001', 1],
]);

test('malformed decimal rates are rejected', function (string $decimal) {
    UnitRate::fromDecimal($decimal);
})->throws(InvalidArgumentException::class)->with(['-1', '1.0000000001', 'abc', '']);

test('costs are exact in nano dollars', function () {
    expect(UnitRate::fromDecimal('40.00')->costFor(6450))->toBe(258_000_000);
});

test('costs round fractional nano dollars to the nearest whole', function () {
    expect(UnitRate::fromDecimal('0.000000001')->costFor(500_000))->toBe(1)
        ->and(UnitRate::fromDecimal('0.000000001')->costFor(499_999))->toBe(0);
});

test('rates render back as nine decimal strings', function () {
    expect(UnitRate::fromDecimal('12.5')->toDecimal())->toBe('12.500000000');
});
