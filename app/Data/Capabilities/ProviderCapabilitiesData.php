<?php

namespace App\Data\Capabilities;

use App\Contracts\Providers\Capabilities\ModelCapabilities;
use App\Contracts\Providers\Capabilities\ProviderCapabilities;
use App\Enums\Modality;
use App\Exceptions\Providers\UnsupportedCapabilityException;

final readonly class ProviderCapabilitiesData implements ProviderCapabilities
{
    /**
     * @param  list<ModelCapabilities>  $models
     */
    public function __construct(
        public string $provider,
        public string $label,
        public array $models,
        public ?string $logoUrl = null,
        public ?string $darkLogoUrl = null,
    ) {}

    public function logoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function darkLogoUrl(): ?string
    {
        return $this->darkLogoUrl;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function models(): array
    {
        return $this->models;
    }

    public function model(?string $id = null): ModelCapabilities
    {
        foreach ($this->models as $model) {
            if ($id === null || $model->id() === $id) {
                return $model;
            }
        }

        throw new UnsupportedCapabilityException("Provider [{$this->provider}] has no model [{$id}].");
    }

    public function supports(Modality $modality): bool
    {
        foreach ($this->models as $model) {
            if ($model->modality() === $modality) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{provider: string, label: string, logoUrl: string|null, darkLogoUrl: string|null, models: list<ModelCapabilities>}
     */
    public function jsonSerialize(): array
    {
        return [
            'provider' => $this->provider,
            'label' => $this->label,
            'logoUrl' => $this->logoUrl,
            'darkLogoUrl' => $this->darkLogoUrl,
            'models' => $this->models,
        ];
    }
}
