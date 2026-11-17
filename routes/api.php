<?php

use App\Http\Controllers\ProviderWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('hook/{provider}', ProviderWebhookController::class)->name('webhooks.handle');
