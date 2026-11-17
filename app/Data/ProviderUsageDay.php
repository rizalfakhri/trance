<?php

namespace App\Data;

final readonly class ProviderUsageDay
{
    /**
     * @param  string  $date  UTC day, Y-m-d
     * @param  int  $cost  Nano-dollars the provider billed for that day
     */
    public function __construct(
        public string $date,
        public int $generations,
        public int $cost,
    ) {}
}
