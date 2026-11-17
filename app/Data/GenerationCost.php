<?php

namespace App\Data;

use App\Contracts\Providers\PricingCatalog;
use App\Contracts\Providers\Usage;

final readonly class GenerationCost
{
    /**
     * @param  array<string, int>  $additional
     */
    public function __construct(
        public string $currency,
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
        public array $additional,
    ) {}

    public static function calculate(Usage $usage, PricingCatalog $pricing): self
    {
        $additionalRates = $pricing->additionalRates();
        $additional = [];

        foreach ($usage->additionalUnits() as $unit => $count) {
            $additional[$unit] = isset($additionalRates[$unit]) ? $additionalRates[$unit]->costFor($count) : 0;
        }

        return new self(
            currency: $pricing->currency(),
            inputText: $pricing->inputTextTokenRate()->costFor($usage->inputTextTokens()),
            inputImage: $pricing->inputImageTokenRate()->costFor($usage->inputImageTokens()),
            output: $pricing->outputTokenRate()->costFor($usage->outputTokens()),
            reasoning: $pricing->reasoningTokenRate()->costFor($usage->reasoningTokens()),
            additional: $additional,
        );
    }

    public function total(): int
    {
        return $this->inputText + $this->inputImage + $this->output + $this->reasoning + array_sum($this->additional);
    }
}
