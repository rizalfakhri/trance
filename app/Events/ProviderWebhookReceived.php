<?php

namespace App\Events;

use App\Data\ProviderWebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;

class ProviderWebhookReceived
{
    use Dispatchable;

    public function __construct(
        public readonly string $provider,
        public readonly ProviderWebhookEvent $event,
    ) {}
}
