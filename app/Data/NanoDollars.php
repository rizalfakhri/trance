<?php

namespace App\Data;

use InvalidArgumentException;

final class NanoDollars
{
    private const PER_DOLLAR = 1_000_000_000;

    /**
     * Parses a decimal dollar string with up to nine decimals (e.g. "0.374227500") without floats.
     */
    public static function fromDecimal(string $dollars): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,9}))?$/', $dollars, $matches)) {
            throw new InvalidArgumentException("Invalid decimal amount [{$dollars}].");
        }

        return (int) $matches[1] * self::PER_DOLLAR + (int) str_pad($matches[2] ?? '', 9, '0');
    }
}
