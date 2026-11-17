<?php

namespace App\Providers;

use App\Contracts\Billing\PlatformFee;
use App\Models\User;
use App\Pricing\PercentageMarkup;
use App\Registries\ProviderRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProviderRegistry::class, function (Application $app): ProviderRegistry {
            $registry = new ProviderRegistry(config('generation.default_provider'), config('generation.disabled_providers'));

            foreach (config('generation.providers') as $providerClass) {
                $registry->register($app->make($providerClass));
            }

            return $registry;
        });

        $this->app->singleton(PlatformFee::class, fn (): PlatformFee => new PercentageMarkup(
            config('generation.platform_fee.default_basis_points'),
            config('generation.platform_fee.providers'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
    }
}
