<?php

namespace App\Data;

final readonly class UnitRate
{
    private const NANO_PER_UNIT = 1_000_000_000;

    private const UNITS_PER_RATE = 1_000_000;

    public function __construct(public int $nanoPerMillionUnits) {}

    public static function fromDecimal(string $pricePerMillionUnits): self
    {
        return new self(NanoDollars::fromDecimal($pricePerMillionUnits));
    }

    public static function free(): self
    {
        return new self(0);
    }

    public function costFor(int $units): int
    {
        return intdiv($units * $this->nanoPerMillionUnits + intdiv(self::UNITS_PER_RATE, 2), self::UNITS_PER_RATE);
    }

    public function toDecimal(): string
    {
        $whole = intdiv($this->nanoPerMillionUnits, self::NANO_PER_UNIT);
        $fraction = str_pad((string) ($this->nanoPerMillionUnits % self::NANO_PER_UNIT), 9, '0', STR_PAD_LEFT);

        return $whole.'.'.$fraction;
    }
}
