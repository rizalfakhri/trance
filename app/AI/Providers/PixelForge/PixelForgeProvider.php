<?php

namespace App\AI\Providers\PixelForge;

use App\AI\Providers\ProviderLogo;
use App\Contracts\Providers\Capabilities\ProviderCapabilities;
use App\Contracts\Providers\EstimatesUsage;
use App\Contracts\Providers\Generates;
use App\Contracts\Providers\PricesGenerations;
use App\Contracts\Providers\PricingCatalog;
use App\Contracts\Providers\Provider;
use App\Contracts\Providers\ReceivesWebhooks;
use App\Contracts\Providers\ReportsUsage;
use App\Contracts\Providers\Usage;
use App\Data\Capabilities\ModelCapabilitiesData;
use App\Data\Capabilities\OptionChoiceData;
use App\Data\Capabilities\PromptConstraintsData;
use App\Data\Capabilities\ProviderCapabilitiesData;
use App\Data\Capabilities\ReferenceInputsData;
use App\Data\Capabilities\SelectOption;
use App\Data\GenerationRequest;
use App\Data\NanoDollars;
use App\Data\ProviderGeneration;
use App\Data\ProviderGenerationPage;
use App\Data\ProviderUsageDay;
use App\Data\ProviderWebhookEvent;
use App\Data\ReferenceAsset;
use App\Enums\Modality;
use App\Exceptions\Providers\GenerationNotFoundException;
use App\Exceptions\Providers\InvalidCredentialsException;
use App\Exceptions\Providers\InvalidWebhookSignatureException;
use App\Exceptions\Providers\PromptRejectedException;
use App\Exceptions\Providers\ProviderException;
use App\Exceptions\Providers\ProviderUnavailableException;
use App\Exceptions\Providers\RateLimitedException;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

final class PixelForgeProvider implements EstimatesUsage, Generates, PricesGenerations, Provider, ReceivesWebhooks, ReportsUsage
{
    private const PRICING_CACHE_KEY = 'providers.pixelforge.pricing';

    private const PRICING_CACHE_SECONDS = 3600;

    private const DEFAULT_SIZE = '1024x1024';

    private const DEFAULT_QUALITY = 'medium';

    public function __construct(
        #[Config('services.pixelforge.base_url')] private readonly string $baseUrl,
        #[Config('services.pixelforge.token')] private readonly ?string $token,
        #[Config('services.pixelforge.webhook_secret')] private readonly ?string $webhookSecret,
        #[Config('services.pixelforge.webhook_tolerance')] private readonly int $webhookToleranceSeconds,
        private readonly Cache $cache,
        private readonly PixelForgeGenerationMapper $mapper,
    ) {}

    public function name(): string
    {
        return 'pixelforge';
    }

    public function label(): string
    {
        return 'PixelForge';
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
        return new ProviderCapabilitiesData($this->name(), $this->label(), [
            new ModelCapabilitiesData(
                id: null,
                label: 'PixelForge',
                modality: Modality::Image,
                prompt: new PromptConstraintsData(4000),
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
                references: new ReferenceInputsData(4, ['image/png', 'image/jpeg', 'image/webp']),
            ),
        ], ProviderLogo::url($this->name()), ProviderLogo::darkUrl($this->name()));
    }

    public function generate(GenerationRequest $request): ProviderGeneration
    {
        $payload = array_filter([
            'prompt' => $request->prompt,
            'size' => $this->size($request),
            'quality' => $this->quality($request),
            'reference_images' => array_map(fn (ReferenceAsset $reference): string => $reference->url, $request->references),
            'metadata' => $request->metadata,
        ], fn (mixed $value): bool => $value !== []);

        $response = $this->send(fn (PendingRequest $client): Response => $client
            ->withHeaders(array_filter(['Idempotency-Key' => $request->idempotencyKey]))
            ->post('generations', $payload));

        return $this->mapper->map($response->json());
    }

    public function generation(string $externalId): ProviderGeneration
    {
        $response = $this->send(fn (PendingRequest $client): Response => $client->get("generations/{$externalId}"));

        return $this->mapper->map($response->json());
    }

    public function generations(int $limit = 20, ?string $cursor = null): ProviderGenerationPage
    {
        $response = $this->send(fn (PendingRequest $client): Response => $client->get('generations', array_filter([
            'limit' => $limit,
            'cursor' => $cursor,
        ])));

        return new ProviderGenerationPage(
            generations: array_map($this->mapper->map(...), $response->json('data')),
            nextCursor: $response->json('has_more') ? $response->json('next_cursor') : null,
        );
    }

    public function pricing(GenerationRequest $request): PricingCatalog
    {
        return $this->rateCard()->catalog();
    }

    public function estimateUsage(GenerationRequest $request): Usage
    {
        return $this->rateCard()->maximumUsage($request->prompt, $this->size($request), $this->quality($request), count($request->references));
    }

    public function usage(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $response = $this->send(fn (PendingRequest $client): Response => $client->get('usage', [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ]));

        return array_map(fn (array $day): ProviderUsageDay => new ProviderUsageDay(
            date: $day['date'],
            generations: (int) $day['generations'],
            cost: NanoDollars::fromDecimal($day['cost_usd']),
        ), $response->json('data', []));
    }

    public function handleWebhook(Request $request): ProviderWebhookEvent
    {
        $this->verifySignature($request->header('Pixelforge-Signature', ''), $request->getContent());

        $event = $request->json()->all();
        $generation = $event['type'] === 'ping' ? null : $event['data']['object'] ?? null;

        return new ProviderWebhookEvent(
            id: $event['id'],
            type: $event['type'],
            occurredAt: CarbonImmutable::parse($event['created_at']),
            generation: $generation === null ? null : $this->mapper->map($generation),
        );
    }

    private function rateCard(): PixelForgeRateCard
    {
        $pricing = $this->cache->remember(self::PRICING_CACHE_KEY, self::PRICING_CACHE_SECONDS, fn (): array => $this
            ->send(fn (PendingRequest $client): Response => $client->get('pricing'))
            ->json());

        return new PixelForgeRateCard($pricing);
    }

    private function size(GenerationRequest $request): string
    {
        return (string) ($request->options['size'] ?? self::DEFAULT_SIZE);
    }

    private function quality(GenerationRequest $request): string
    {
        return (string) ($request->options['quality'] ?? self::DEFAULT_QUALITY);
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws ProviderException
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request(Http::baseUrl($this->baseUrl)
                ->withToken((string) $this->token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20));
        } catch (ConnectionException $exception) {
            throw new ProviderUnavailableException('PixelForge could not be reached.', previous: $exception);
        }

        if ($response->successful()) {
            return $response;
        }

        $message = (string) $response->json('error.message', 'PixelForge request failed.');
        $retryAfter = $response->header('Retry-After') === '' ? null : (int) $response->header('Retry-After');

        throw match (true) {
            $response->status() === 401 => new InvalidCredentialsException($message),
            $response->status() === 404 => new GenerationNotFoundException($message),
            $response->status() === 429 => new RateLimitedException($message, $retryAfter),
            $response->serverError() => new ProviderUnavailableException($message, $retryAfter),
            $response->json('error.code') === 'content_policy_violation' => new PromptRejectedException($message),
            default => new ProviderException($message),
        };
    }

    /**
     * @throws InvalidWebhookSignatureException
     */
    private function verifySignature(string $header, string $payload): void
    {
        if (blank($this->webhookSecret)) {
            throw new InvalidWebhookSignatureException('No PixelForge webhook secret is configured.');
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($timestamp === 0 || abs(now()->getTimestamp() - $timestamp) > $this->webhookToleranceSeconds) {
            throw new InvalidWebhookSignatureException('PixelForge webhook timestamp is missing or outside the tolerance.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->webhookSecret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new InvalidWebhookSignatureException('PixelForge webhook signature does not match.');
    }
}
