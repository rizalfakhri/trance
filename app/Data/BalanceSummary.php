<?php

namespace App\Data;

final readonly class BalanceSummary
{
    public function __construct(
        public int $topUps,
        public int $credits,
        public int $usage,
        public int $refunds,
        public int $adjustments,
        public int $held,
    ) {}

    public function settled(): int
    {
        return $this->topUps + $this->credits - $this->usage + $this->refunds + $this->adjustments;
    }

    public function available(): int
    {
        return $this->settled() - $this->held;
    }
}
