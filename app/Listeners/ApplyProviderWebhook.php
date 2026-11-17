<?php

namespace App\Listeners;

use App\Events\ProviderWebhookReceived;
use App\Generations\ApplyProviderUpdate;
use App\Models\Generation;

class ApplyProviderWebhook
{
    public function __construct(private readonly ApplyProviderUpdate $apply) {}

    public function handle(ProviderWebhookReceived $received): void
    {
        $update = $received->event->generation;

        if ($update === null) {
            return;
        }

        $generation = Generation::resolveFromProvider($received->provider, $update);

        if ($generation !== null) {
            $this->apply->apply($generation, $update, $received->event->id, $received->event->type);
        }
    }
}
