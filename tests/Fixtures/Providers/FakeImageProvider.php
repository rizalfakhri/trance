<?php

namespace Tests\Fixtures\Providers;

use App\Contracts\Providers\Capabilities\ProviderCapabilities;
use App\Contracts\Providers\EstimatesUsage;
use App\Contracts\Providers\Generates;
use App\Contracts\Providers\PricesGenerations;
use App\Contracts\Providers\PricingCatalog;
use App\Contracts\Providers\Provider;
use App\Contracts\Providers\ReceivesWebhooks;
use App\Contracts\Providers\Usage;
use App\Data\Capabilities\ModelCapabilitiesData;
use App\Data\Capabilities\OptionChoiceData;
use App\Data\Capabilities\PromptConstraintsData;
use App\Data\Capabilities\ProviderCapabilitiesData;
use App\Data\Capabilities\ReferenceInputsData;
use App\Data\Capabilities\SelectOption;
use App\Data\GenerationRequest;
use App\Data\ProviderGeneration;
use App\Data\ProviderGenerationPage;
use App\Data\ProviderWebhookEvent;
use App\Data\StaticPricingCatalog;
use App\Data\TokenUsage;
use App\Data\UnitRate;
use App\Enums\GenerationStatus;
use App\Enums\Modality;
use App\Exceptions\Providers\InvalidWebhookSignatureException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class FakeImageProvider implements EstimatesUsage, Generates, PricesGenerations, Provider, ReceivesWebhooks
{
    public const VALID_SIGNATURE = 'valid-signature';

    public function name(): string
    {
        return 'fake-image';
    }

    public function label(): string
    {
        return 'Fake Image';
    }

    public function baseUrl(): string
    {
        return 'https://fake-image.test/v1';
    }

    public function token(): string
    {
        return 'fake-token';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilitiesData('fake-image', 'Fake Image', [
            new ModelCapabilitiesData(
                id: null,
                label: 'Default',
                modality: Modality::Image,
                prompt: new PromptConstraintsData(4000),
                options: [
                    new SelectOption('size', 'Size', [
                        new OptionChoiceData('1024x1024', '1024×1024', 'Square'),
                        new OptionChoiceData('1536x1024', '1536×1024', 'Landscape'),
                    ], default: '1024x1024'),
                ],
                references: new ReferenceInputsData(4, ['image/*']),
            ),
        ]);
    }

    public function generate(GenerationRequest $request): ProviderGeneration
    {
        return new ProviderGeneration('gen_fake', GenerationStatus::Queued);
    }

    public function generation(string $externalId): ProviderGeneration
    {
        return new ProviderGeneration($externalId, GenerationStatus::Processing);
    }

    public function generations(int $limit = 20, ?string $cursor = null): ProviderGenerationPage
    {
        return new ProviderGenerationPage([]);
    }

    public function pricing(GenerationRequest $request): PricingCatalog
    {
        return new StaticPricingCatalog(
            inputTextTokenRate: UnitRate::fromDecimal('5.00'),
            inputImageTokenRate: UnitRate::fromDecimal('10.00'),
            outputTokenRate: UnitRate::fromDecimal('40.00'),
            reasoningTokenRate: UnitRate::fromDecimal('12.50'),
        );
    }

    public function estimateUsage(GenerationRequest $request): Usage
    {
        return new TokenUsage(
            inputTextTokens: (int) ceil(mb_strlen($request->prompt) / 4),
            inputImageTokens: 765 * count($request->references),
            outputTokens: 6450,
            reasoningTokens: 8000,
        );
    }

    public function handleWebhook(Request $request): ProviderWebhookEvent
    {
        if ($request->header('X-Fake-Signature') !== self::VALID_SIGNATURE) {
            throw new InvalidWebhookSignatureException('Signature mismatch.');
        }

        return new ProviderWebhookEvent(
            id: $request->string('id')->toString(),
            type: $request->string('type')->toString(),
            occurredAt: CarbonImmutable::now(),
            generation: new ProviderGeneration($request->string('generation_id')->toString(), GenerationStatus::Processing),
        );
    }
}
