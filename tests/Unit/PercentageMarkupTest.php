<?php

use App\Data\GenerationCost;
use App\Pricing\PercentageMarkup;

function costOf(int $nanoDollars): GenerationCost
{
    return new GenerationCost('usd', $nanoDollars, 0, 0, 0, []);
}

test('the markup is added on top of the provider cost', function () {
    $charge = (new PercentageMarkup(2500))->apply(costOf(373_875_000), 'pixelforge');

    expect($charge->fee)->toBe(93_468_750)
        ->and($charge->total())->toBe(467_343_750)
        ->and($charge->feeBasisPoints)->toBe(2500);
});

test('fractional fees round up so the platform never undercharges', function () {
    expect((new PercentageMarkup(2500))->apply(costOf(1), 'pixelforge')->fee)->toBe(1);
});

test('providers can override the default markup', function () {
    $markup = new PercentageMarkup(2500, ['cheap-provider' => 5000]);

    expect($markup->apply(costOf(1_000), 'cheap-provider')->fee)->toBe(500)
        ->and($markup->apply(costOf(1_000), 'pixelforge')->fee)->toBe(250);
});

test('a zero markup charges the provider cost only', function () {
    $charge = (new PercentageMarkup(0))->apply(costOf(1_000), 'pixelforge');

    expect($charge->fee)->toBe(0)
        ->and($charge->total())->toBe(1_000);
});
