<?php

namespace App\Data;

final readonly class GenerationCharge
{
    public function __construct(
        public GenerationCost $cost,
        public int $feeBasisPoints,
        public int $fee,
    ) {}

    public function currency(): string
    {
        return $this->cost->currency;
    }

    public function total(): int
    {
        return $this->cost->total() + $this->fee;
    }
}
