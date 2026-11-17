<?php

namespace App\Data;

use App\Contracts\Providers\Usage;

final readonly class TokenUsage implements Usage
{
    /**
     * @param  array<string, int>  $additionalUnits
     */
    public function __construct(
        public int $inputTextTokens = 0,
        public int $inputImageTokens = 0,
        public int $outputTokens = 0,
        public int $reasoningTokens = 0,
        public array $additionalUnits = [],
    ) {}

    public function inputTextTokens(): int
    {
        return $this->inputTextTokens;
    }

    public function inputImageTokens(): int
    {
        return $this->inputImageTokens;
    }

    public function outputTokens(): int
    {
        return $this->outputTokens;
    }

    public function reasoningTokens(): int
    {
        return $this->reasoningTokens;
    }

    public function additionalUnits(): array
    {
        return $this->additionalUnits;
    }

    public function totalTokens(): int
    {
        return $this->inputTextTokens + $this->inputImageTokens + $this->outputTokens + $this->reasoningTokens;
    }
}
