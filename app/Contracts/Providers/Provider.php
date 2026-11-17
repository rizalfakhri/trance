<?php

namespace App\Contracts\Providers;

use App\Contracts\Providers\Capabilities\ProviderCapabilities;

interface Provider
{
    public function name(): string;

    public function label(): string;

    public function baseUrl(): string;

    public function token(): string;

    public function capabilities(): ProviderCapabilities;
}
