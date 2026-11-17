<?php

namespace App\AI\Providers\OpenAI;

use App\AI\Providers\ProviderLogo;
use App\Contracts\Providers\Capabilities\ProviderCapabilities;
use App\Contracts\Providers\EstimatesUsage;
use App\Contracts\Providers\Generates;
use App\Contracts\Providers\PricesGenerations;
use App\Contracts\Providers\PricingCatalog;
use App\Contracts\Providers\Provider;
use App\Contracts\Providers\Usage;
use App\Data\Capabilities\ModelCapabilitiesData;
use App\Data\Capabilities\OptionChoiceData;
use App\Data\Capabilities\PromptConstraintsData;
use App\Data\Capabilities\ProviderCapabilitiesData;
use App\Data\Capabilities\ReferenceInputsData;
use App\Data\Capabilities\SelectOption;
use App\Data\GeneratedAsset;
use App\Data\GenerationRequest;
use App\Data\ProviderGeneration;
use App\Data\ProviderGenerationPage;
use App\Data\ReferenceAsset;
use App\Data\StaticPricingCatalog;
use App\Data\TokenUsage;
use App\Data\UnitRate;
use App\Enums\GenerationStatus;
use App\Enums\Modality;
use App\Exceptions\Providers\GenerationNotFoundException;
use App\Exceptions\Providers\InvalidCredentialsException;
use App\Exceptions\Providers\PromptRejectedException;
use App\Exceptions\Providers\ProviderException;
use App\Exceptions\Providers\ProviderUnavailableException;
use App\Exceptions\Providers\RateLimitedException;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * OpenAI GPT Image models through the synchronous Images API: the image comes back in the
 * response as base64, so every generation is terminal as soon as generate() returns.
 */
final class OpenAIProvider implements EstimatesUsage, Generates, PricesGenerations, Provider
{
    private const DEFAULT_SIZE = '1024x1024';

    private const DEFAULT_QUALITY = 'medium';

    private const CHARACTERS_PER_TEXT_TOKEN = 4;

    private const TEXT_TOKEN_VARIANCE = 0.15;

    private const QUOTA_ERROR_CODES = [
        'insufficient_quota',
        'credit_balance_exhausted',
        'organization_spend_limit_exceeded',
        'project_spend_limit_exceeded',
        'organization_usage_limit_exceeded',
    ];

    /**
     * @param  array<string, array{label: string, rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string}}>  $models
     * @param  array<string, array<string, int>>  $maxOutputTokens
     */
    public function __construct(
        #[Config('services.openai.base_url')] private readonly string $baseUrl,
        #[Config('services.openai.token')] private readonly ?string $token,
        #[Config('services.openai.timeout')] private readonly int $timeoutSeconds,
        #[Config('services.openai.models')] private readonly array $models,
        #[Config('services.openai.max_output_tokens')] private readonly array $maxOutputTokens,
        #[Config('services.openai.input_image_tokens_per_reference')] private readonly int $inputImageTokensPerReference,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function token(): string
    {
        return (string) $this->token;
    }

    public function capabilities(): ProviderCapabilities
    {
        $models = [];

        foreach ($this->models as $id => $model) {
            $models[] = new ModelCapabilitiesData(
                id: $id,
                label: $model['label'],
                modality: Modality::Image,
                prompt: new PromptConstraintsData(32000),
                options: [
                    new SelectOption('size', 'Size', [
                        new OptionChoiceData('1024x1024', '1024×1024', 'Square'),
                        new OptionChoiceData('1536x1024', '1536×1024', 'Landscape'),
                        new OptionChoiceData('1024x1536', '1024×1536', 'Portrait'),
                    ], default: self::DEFAULT_SIZE),
                    new SelectOption('quality', 'Quality', [
                        new OptionChoiceData('low', 'Low'),
                        new OptionChoiceData('medium', 'Medium'),
                        new OptionChoiceData('high', 'High'),
                    ], default: self::DEFAULT_QUALITY),
                ],
                references: new ReferenceInputsData(16, ['image/png', 'image/jpeg', 'image/webp']),
            );
        }

        return new ProviderCapabilitiesData($this->name(), $this->label(), $models, ProviderLogo::url($this->name()), ProviderLogo::darkUrl($this->name()));
    }

    public function generate(GenerationRequest $request): ProviderGeneration
    {
        $model = $this->model($request);
        $size = $this->size($request);
        $payload = [
            'model' => $model,
            'prompt' => $request->prompt,
            'size' => $size,
            'quality' => $this->quality($request),
            'n' => 1,
            'output_format' => 'png',
        ];

        if ($request->generationId() !== null) {
            $payload['safety_identifier'] = hash('sha256', $request->generationId());
        }

        $endpoint = 'images/generations';

        if ($request->references !== []) {
            $endpoint = 'images/edits';
            $payload['images'] = array_map(fn (ReferenceAsset $reference): array => ['image_url' => $reference->url], $request->references);
        }

        $response = $this->send(fn () => $this->client()->post($endpoint, $payload));
        $image = $response->json('data.0.b64_json');

        if (! is_string($image)) {
            throw new ProviderException('OpenAI returned no image.');
        }

        [$width, $height] = array_map(intval(...), explode('x', $size));
        $completedAt = CarbonImmutable::now();

        return new ProviderGeneration(
            externalId: 'openai_'.($request->idempotencyKey ?? Str::uuid()->toString()),
            status: GenerationStatus::Succeeded,
            outputs: [new GeneratedAsset(Modality::Image, "data:image/png;base64,{$image}", 'image/png', $width, $height)],
            usage: new TokenUsage(
                inputTextTokens: (int) $response->json('usage.input_tokens_details.text_tokens', 0),
                inputImageTokens: (int) $response->json('usage.input_tokens_details.image_tokens', 0),
                outputTokens: (int) $response->json('usage.output_tokens', 0),
            ),
            pricing: $this->catalog($model),
            startedAt: $completedAt,
            completedAt: $completedAt,
            metadata: $request->metadata,
        );
    }

    public function generation(string $externalId): ProviderGeneration
    {
        throw new GenerationNotFoundException('OpenAI images are returned synchronously and cannot be looked up later.');
    }

    public function generations(int $limit = 20, ?string $cursor = null): ProviderGenerationPage
    {
        return new ProviderGenerationPage([]);
    }

    public function pricing(GenerationRequest $request): PricingCatalog
    {
        return $this->catalog($this->model($request));
    }

    public function estimateUsage(GenerationRequest $request): Usage
    {
        $estimatedTextTokens = mb_strlen($request->prompt) / self::CHARACTERS_PER_TEXT_TOKEN;

        return new TokenUsage(
            inputTextTokens: (int) ceil($estimatedTextTokens * (1 + self::TEXT_TOKEN_VARIANCE)),
            inputImageTokens: $this->inputImageTokensPerReference * count($request->references),
            outputTokens: $this->maxOutputTokens[$this->quality($request)][$this->size($request)],
        );
    }

    private function catalog(string $model): PricingCatalog
    {
        $rates = $this->models[$model]['rates'];

        return new StaticPricingCatalog(
            inputTextTokenRate: UnitRate::fromDecimal($rates['input_text_tokens']),
            inputImageTokenRate: UnitRate::fromDecimal($rates['input_image_tokens']),
            outputTokenRate: UnitRate::fromDecimal($rates['output_tokens']),
            reasoningTokenRate: UnitRate::free(),
        );
    }

    private function model(GenerationRequest $request): string
    {
        return $request->model ?? array_key_first($this->models);
    }

    private function size(GenerationRequest $request): string
    {
        return (string) ($request->options['size'] ?? self::DEFAULT_SIZE);
    }

    private function quality(GenerationRequest $request): string
    {
        return (string) ($request->options['quality'] ?? self::DEFAULT_QUALITY);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout($this->timeoutSeconds);
    }

    /**
     * @param  callable(): Response  $request
     *
     * @throws ProviderException
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            throw new ProviderUnavailableException('OpenAI could not be reached.', previous: $exception);
        }

        if ($response->successful()) {
            return $response;
        }

        $message = (string) $response->json('error.message', 'OpenAI request failed.');
        $code = (string) $response->json('error.code', '');
        $retryAfter = $response->header('Retry-After') === '' ? null : (int) $response->header('Retry-After');

        throw match (true) {
            $response->status() === 401 => new InvalidCredentialsException($message),
            $response->status() === 429 && in_array($code, self::QUOTA_ERROR_CODES, true) => new ProviderException($message),
            $response->status() === 429 => new RateLimitedException($message, $retryAfter),
            $response->serverError() => new ProviderUnavailableException($message, $retryAfter),
            $response->status() === 400 && $this->isSafetyRejection($code, $message) => new PromptRejectedException($message),
            default => new ProviderException($message),
        };
    }

    private function isSafetyRejection(string $code, string $message): bool
    {
        return str_contains($code, 'moderation')
            || str_contains($code, 'content_policy')
            || str_contains($code, 'safety')
            || Str::contains($message, ['safety system', 'content policy'], ignoreCase: true);
    }
}
