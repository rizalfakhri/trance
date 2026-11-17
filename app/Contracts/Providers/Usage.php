<?php

namespace App\Contracts\Providers;

interface Usage
{
    public function inputTextTokens(): int;

    public function inputImageTokens(): int;

    public function outputTokens(): int;

    public function reasoningTokens(): int;

    /**
     * @return array<string, int>
     */
    public function additionalUnits(): array;

    public function totalTokens(): int;
}
