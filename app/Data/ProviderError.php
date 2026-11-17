<?php

namespace App\Data;

final readonly class ProviderError
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}
}
