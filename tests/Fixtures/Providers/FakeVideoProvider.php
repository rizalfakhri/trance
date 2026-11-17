<?php

namespace Tests\Fixtures\Providers;

use App\Contracts\Providers\Capabilities\ProviderCapabilities;
use App\Contracts\Providers\Provider;
use App\Data\Capabilities\ModelCapabilitiesData;
use App\Data\Capabilities\NumberOption;
use App\Data\Capabilities\PromptConstraintsData;
use App\Data\Capabilities\ProviderCapabilitiesData;
use App\Enums\Modality;

class FakeVideoProvider implements Provider
{
    public function name(): string
    {
        return 'fake-video';
    }

    public function label(): string
    {
        return 'Fake Video';
    }

    public function baseUrl(): string
    {
        return 'https://fake-video.test/v1';
    }

    public function token(): string
    {
        return 'fake-token';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilitiesData('fake-video', 'Fake Video', [
            new ModelCapabilitiesData(
                id: 'clip-1',
                label: 'Clip 1',
                modality: Modality::Video,
                prompt: new PromptConstraintsData(1000),
                options: [new NumberOption('duration', 'Duration', minimum: 2, maximum: 10, increment: 2, default: 4)],
            ),
        ]);
    }
}
