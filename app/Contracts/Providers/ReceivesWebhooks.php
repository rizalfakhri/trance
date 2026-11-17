<?php

namespace App\Contracts\Providers;

use App\Data\ProviderWebhookEvent;
use App\Exceptions\Providers\InvalidWebhookSignatureException;
use Illuminate\Http\Request;

interface ReceivesWebhooks
{
    /**
     * @throws InvalidWebhookSignatureException
     */
    public function handleWebhook(Request $request): ProviderWebhookEvent;
}
