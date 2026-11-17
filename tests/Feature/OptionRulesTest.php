<?php

use App\Contracts\Providers\Capabilities\OptionDefinition;
use App\Data\Capabilities\BooleanOption;
use App\Data\Capabilities\NumberOption;
use App\Data\Capabilities\OptionChoiceData;
use App\Data\Capabilities\SelectOption;
use Illuminate\Support\Facades\Validator;

function passesOptionRules(OptionDefinition $option, mixed $value): bool
{
    return Validator::make([$option->key() => $value], [$option->key() => $option->rules()])->passes();
}

test('select options only accept their choices', function () {
    $option = new SelectOption('quality', 'Quality', [new OptionChoiceData('low', 'Low'), new OptionChoiceData('high', 'High')]);

    expect(passesOptionRules($option, 'high'))->toBeTrue()
        ->and(passesOptionRules($option, 'ultra'))->toBeFalse();
});

test('number options enforce their range and step', function (int $value, bool $passes) {
    $option = new NumberOption('duration', 'Duration', minimum: 2, maximum: 10, increment: 2);

    expect(passesOptionRules($option, $value))->toBe($passes);
})->with([
    'within range on step' => [4, true],
    'below minimum' => [0, false],
    'above maximum' => [12, false],
    'off step' => [5, false],
]);

test('boolean options only accept booleans', function () {
    $option = new BooleanOption('loop', 'Loop');

    expect(passesOptionRules($option, true))->toBeTrue()
        ->and(passesOptionRules($option, 'sometimes'))->toBeFalse();
});

test('options may be omitted', function () {
    $option = new BooleanOption('loop', 'Loop');

    expect(Validator::make([], ['loop' => $option->rules()])->passes())->toBeTrue();
});
