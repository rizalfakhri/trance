<?php

namespace App\Exceptions\Providers;

use Throwable;

class ProviderUnavailableException extends ProviderException
{
    public function __construct(string $message = '', public readonly ?int $retryAfterSeconds = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
