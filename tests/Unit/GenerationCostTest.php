<?php

use App\Data\GenerationCost;
use App\Data\StaticPricingCatalog;
use App\Data\TokenUsage;
use App\Data\UnitRate;

function pixelforgeRates(array $additionalRates = []): StaticPricingCatalog
{
    return new StaticPricingCatalog(
        inputTextTokenRate: UnitRate::fromDecimal('5.00'),
        inputImageTokenRate: UnitRate::fromDecimal('10.00'),
        outputTokenRate: UnitRate::fromDecimal('40.00'),
        reasoningTokenRate: UnitRate::fromDecimal('12.50'),
        additionalRates: $additionalRates,
    );
}

test('the cost is broken down per token type and totalled in nano dollars', function () {
    $usage = new TokenUsage(inputTextTokens: 100, inputImageTokens: 1530, outputTokens: 6450, reasoningTokens: 8000);

    $cost = GenerationCost::calculate($usage, pixelforgeRates());

    expect($cost->inputText)->toBe(500_000)
        ->and($cost->inputImage)->toBe(15_300_000)
        ->and($cost->output)->toBe(258_000_000)
        ->and($cost->reasoning)->toBe(100_000_000)
        ->and($cost->total())->toBe(373_800_000)
        ->and($cost->currency)->toBe('usd');
});

test('additional units are priced by matching rate key', function () {
    $usage = new TokenUsage(additionalUnits: ['video_seconds' => 8]);

    $cost = GenerationCost::calculate($usage, pixelforgeRates(['video_seconds' => UnitRate::fromDecimal('500000')]));

    expect($cost->additional)->toBe(['video_seconds' => 4_000_000_000])
        ->and($cost->total())->toBe(4_000_000_000);
});

test('additional units without a rate cost nothing', function () {
    $cost = GenerationCost::calculate(new TokenUsage(additionalUnits: ['upscales' => 3]), pixelforgeRates());

    expect($cost->additional)->toBe(['upscales' => 0])
        ->and($cost->total())->toBe(0);
});
