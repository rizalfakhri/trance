<?php

use App\Contracts\Providers\Capabilities\ProviderCapabilities;
use App\Enums\Modality;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\BalanceTopUpController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\GenerationOutputDownloadController;
use App\Http\Controllers\GenerationQuoteController;
use App\Http\Controllers\GenerationRetryController;
use App\Http\Controllers\TransactionController;
use App\Registries\ProviderRegistry;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (ProviderRegistry $registry) {
    return Inertia::render('Home', [
        'providers' => array_map(
            fn (ProviderCapabilities $capabilities): array => [...$capabilities->jsonSerialize(), 'enabled' => $registry->isEnabled($capabilities->provider())],
            $registry->capabilities(Modality::Image),
        ),
        'defaultProvider' => $registry->default()->name(),
    ]);
})->name('home');

Route::get('admin', AdminDashboardController::class)
    ->middleware(['auth', 'can:access-admin'])
    ->name('admin.dashboard');

Route::get('gallery', [GalleryController::class, 'index'])
    ->middleware('auth')
    ->name('gallery');

Route::get('transactions', [TransactionController::class, 'index'])
    ->middleware('auth')
    ->name('transactions');

Route::post('generations/quote', GenerationQuoteController::class)
    ->middleware('auth')
    ->name('generations.quote');

Route::post('balance/top-ups', [BalanceTopUpController::class, 'store'])
    ->middleware('auth')
    ->name('balance.top-ups.store');

Route::post('generations', [GenerationController::class, 'store'])
    ->middleware('auth')
    ->name('generations.store');

Route::get('g/{generation}', [GenerationController::class, 'show'])
    ->middleware('auth')
    ->whereUuid('generation')
    ->name('generations.show');

Route::post('g/{generation}/retry', GenerationRetryController::class)
    ->middleware('auth')
    ->whereUuid('generation')
    ->name('generations.retry');

Route::get('g/{generation}/outputs/{output}/download', GenerationOutputDownloadController::class)
    ->middleware('auth')
    ->whereUuid('generation')
    ->scopeBindings()
    ->name('generations.outputs.download');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
