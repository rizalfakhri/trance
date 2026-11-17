<?php

namespace App\AI\Providers\PixelForge;

use App\Contracts\Providers\PricingCatalog;
use App\Contracts\Providers\Usage;
use App\Data\StaticPricingCatalog;
use App\Data\TokenUsage;
use App\Data\UnitRate;

final readonly class PixelForgeRateCard
{
    /**
     * @param  array{currency: string, rates: array<string, string>, tokens: array{characters_per_text_token: int, text_token_variance: float, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>}}  $pricing
     */
    public function __construct(private array $pricing) {}

    public function catalog(): PricingCatalog
    {
        return self::catalogFromRates($this->pricing['rates'], $this->pricing['currency']);
    }

    /**
     * @param  array<string, string>  $rates
     */
    public static function catalogFromRates(array $rates, string $currency = 'usd'): PricingCatalog
    {
        return new StaticPricingCatalog(
            inputTextTokenRate: UnitRate::fromDecimal($rates['input_text_tokens']),
            inputImageTokenRate: UnitRate::fromDecimal($rates['input_image_tokens']),
            outputTokenRate: UnitRate::fromDecimal($rates['output_tokens']),
            reasoningTokenRate: UnitRate::fromDecimal($rates['reasoning_tokens']),
            currency: $currency,
        );
    }

    public function maximumUsage(string $prompt, string $size, string $quality, int $referenceCount): Usage
    {
        $tokens = $this->pricing['tokens'];
        $estimatedTextTokens = mb_strlen($prompt) / $tokens['characters_per_text_token'];

        return new TokenUsage(
            inputTextTokens: (int) ceil($estimatedTextTokens * (1 + $tokens['text_token_variance'])),
            inputImageTokens: $tokens['per_reference_image'] * $referenceCount,
            outputTokens: $tokens['output'][$quality][$size],
            reasoningTokens: $tokens['reasoning'][$quality]['max'],
        );
    }
}
