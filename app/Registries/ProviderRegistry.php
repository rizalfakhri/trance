<?php

namespace App\Registries;

use App\Contracts\Providers\Capabilities\ProviderCapabilities;
use App\Contracts\Providers\EstimatesUsage;
use App\Contracts\Providers\Generates;
use App\Contracts\Providers\PricesGenerations;
use App\Contracts\Providers\Provider;
use App\Contracts\Providers\ReceivesWebhooks;
use App\Contracts\Providers\ReportsUsage;
use App\Enums\Modality;
use App\Exceptions\Providers\UnknownProviderException;
use App\Exceptions\Providers\UnsupportedCapabilityException;
use InvalidArgumentException;

final class ProviderRegistry
{
    /**
     * @var array<string, Provider>
     */
    private array $providers = [];

    /**
     * @param  list<string>  $disabledProviders
     */
    public function __construct(
        private readonly ?string $defaultProvider = null,
        private readonly array $disabledProviders = [],
    ) {}

    public function register(Provider $provider): void
    {
        if ($this->has($provider->name())) {
            throw new InvalidArgumentException("Provider [{$provider->name()}] is already registered.");
        }

        $this->providers[$provider->name()] = $provider;
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * @throws UnknownProviderException
     */
    public function get(string $name): Provider
    {
        return $this->providers[$name] ?? throw new UnknownProviderException("Provider [{$name}] is not registered.");
    }

    /**
     * @return array<string, Provider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @throws UnknownProviderException
     */
    public function isEnabled(string $name): bool
    {
        return $this->has($name) && ! in_array($name, $this->disabledProviders, true);
    }

    /**
     * @return array<string, Provider>
     */
    public function enabled(): array
    {
        return array_filter($this->providers, fn (Provider $provider): bool => $this->isEnabled($provider->name()));
    }

    /**
     * The configured default provider, or the first enabled one when the default is disabled.
     *
     * @throws UnknownProviderException
     */
    public function default(): Provider
    {
        if ($this->defaultProvider !== null && $this->isEnabled($this->defaultProvider)) {
            return $this->get($this->defaultProvider);
        }

        return array_values($this->enabled())[0] ?? throw new UnknownProviderException('No enabled provider is configured.');
    }

    /**
     * @throws UnknownProviderException
     * @throws UnsupportedCapabilityException
     */
    public function generator(string $name): Generates
    {
        return $this->capability($name, Generates::class);
    }

    /**
     * @throws UnknownProviderException
     * @throws UnsupportedCapabilityException
     */
    public function pricing(string $name): PricesGenerations
    {
        return $this->capability($name, PricesGenerations::class);
    }

    /**
     * @throws UnknownProviderException
     * @throws UnsupportedCapabilityException
     */
    public function estimator(string $name): EstimatesUsage
    {
        return $this->capability($name, EstimatesUsage::class);
    }

    /**
     * @throws UnknownProviderException
     * @throws UnsupportedCapabilityException
     */
    public function webhooks(string $name): ReceivesWebhooks
    {
        return $this->capability($name, ReceivesWebhooks::class);
    }

    /**
     * @throws UnknownProviderException
     * @throws UnsupportedCapabilityException
     */
    public function usageReporter(string $name): ReportsUsage
    {
        return $this->capability($name, ReportsUsage::class);
    }

    /**
     * @return list<ProviderCapabilities>
     */
    public function capabilities(?Modality $modality = null): array
    {
        $capabilities = array_map(fn (Provider $provider): ProviderCapabilities => $provider->capabilities(), array_values($this->providers));

        if ($modality === null) {
            return $capabilities;
        }

        return array_values(array_filter($capabilities, fn (ProviderCapabilities $capabilities): bool => $capabilities->supports($modality)));
    }

    /**
     * @template TCapability of object
     *
     * @param  class-string<TCapability>  $contract
     * @return TCapability
     */
    private function capability(string $name, string $contract): object
    {
        $provider = $this->get($name);

        if (! $provider instanceof $contract) {
            throw new UnsupportedCapabilityException("Provider [{$name}] does not implement [{$contract}].");
        }

        return $provider;
    }
}
