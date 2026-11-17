<?php

namespace App\Exceptions\Billing;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $available)
    {
        parent::__construct("Insufficient balance: {$required} required, {$available} available.");
    }
}
