<?php

namespace App\Contracts\Providers\Capabilities;

use App\Enums\Modality;
use App\Exceptions\Providers\UnsupportedCapabilityException;
use JsonSerializable;

interface ProviderCapabilities extends JsonSerializable
{
    public function provider(): string;

    public function label(): string;

    public function logoUrl(): ?string;

    public function darkLogoUrl(): ?string;

    /**
     * @return list<ModelCapabilities>
     */
    public function models(): array;

    /**
     * @throws UnsupportedCapabilityException
     */
    public function model(?string $id = null): ModelCapabilities;

    public function supports(Modality $modality): bool;
}
