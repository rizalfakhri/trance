<?php

use App\AI\Providers\PixelForge\PixelForgeProvider;
use App\Data\GenerationCost;
use App\Data\GenerationRequest;
use App\Data\ReferenceAsset;
use App\Enums\GenerationStatus;
use App\Enums\Modality;
use App\Events\ProviderWebhookReceived;
use App\Exceptions\Providers\GenerationNotFoundException;
use App\Exceptions\Providers\InvalidCredentialsException;
use App\Exceptions\Providers\InvalidWebhookSignatureException;
use App\Exceptions\Providers\PromptRejectedException;
use App\Exceptions\Providers\ProviderException;
use App\Exceptions\Providers\ProviderUnavailableException;
use App\Exceptions\Providers\RateLimitedException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const PIXELFORGE_URL = 'https://pixelforge.test/v1';
const PIXELFORGE_SECRET = 'whsec_test';

beforeEach(function () {
    config([
        'services.pixelforge.base_url' => PIXELFORGE_URL,
        'services.pixelforge.token' => 'pf_test_key',
        'services.pixelforge.webhook_secret' => PIXELFORGE_SECRET,
        'services.pixelforge.webhook_tolerance' => 300,
    ]);
});

function pixelforge(): PixelForgeProvider
{
    return app(PixelForgeProvider::class);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pixelforgeGeneration(array $overrides = []): array
{
    return array_merge([
        'id' => 'gen_123',
        'object' => 'generation',
        'status' => 'queued',
        'prompt' => 'A lighthouse at dawn',
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => [],
        'metadata' => [],
        'image_url' => null,
        'image_url_expires_at' => null,
        'usage' => null,
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'error' => null,
        'created_at' => '2026-11-04T14:03:00Z',
        'started_at' => null,
        'completed_at' => null,
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function pixelforgePricing(): array
{
    return [
        'object' => 'pricing',
        'currency' => 'usd',
        'unit' => 'per_1m_tokens',
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}

function pixelforgeRequest(array $options = [], array $references = [], array $metadata = []): GenerationRequest
{
    return new GenerationRequest(
        prompt: str_repeat('a', 400),
        modality: Modality::Image,
        idempotencyKey: 'trance-generation-1',
        options: $options,
        references: $references,
        metadata: $metadata,
    );
}

function signedWebhook(array $payload, ?int $timestamp = null, string $secret = PIXELFORGE_SECRET): Request
{
    $body = json_encode($payload);
    $timestamp ??= now()->getTimestamp();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return Request::create('/api/hook/pixelforge', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], content: $body);
}

test('capabilities expose the sizes, qualities and reference limits the ui renders', function () {
    $model = pixelforge()->capabilities()->model();

    expect($model->modality())->toBe(Modality::Image)
        ->and($model->prompt()->maxLength())->toBe(4000)
        ->and(array_map(fn ($choice) => $choice->value(), $model->option('size')->choices()))->toBe(['1024x1024', '1536x1024', '1024x1536'])
        ->and(array_map(fn ($choice) => $choice->value(), $model->option('quality')->choices()))->toBe(['low', 'medium', 'high'])
        ->and($model->option('quality')->default())->toBe('medium')
        ->and($model->references()->maxCount())->toBe(4);
});

test('generating submits the prompt with an idempotency key and maps the queued generation', function () {
    Http::fake([PIXELFORGE_URL.'/generations' => Http::response(pixelforgeGeneration(), 202)]);

    $generation = pixelforge()->generate(pixelforgeRequest(
        options: ['size' => '1536x1024', 'quality' => 'high'],
        references: [new ReferenceAsset('https://trance.test/references/one.png', 'image/png')],
        metadata: ['generation_id' => 'trance-generation-1'],
    ));

    expect($generation->externalId)->toBe('gen_123')
        ->and($generation->status)->toBe(GenerationStatus::Queued);

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request->url() === PIXELFORGE_URL.'/generations'
        && $request->hasHeader('Authorization', 'Bearer pf_test_key')
        && $request->hasHeader('Idempotency-Key', 'trance-generation-1')
        && $request->data() === [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://trance.test/references/one.png'],
            'metadata' => ['generation_id' => 'trance-generation-1'],
        ]);
});

test('generating without options sends the provider defaults and omits empty lists', function () {
    Http::fake([PIXELFORGE_URL.'/generations' => Http::response(pixelforgeGeneration(), 202)]);

    pixelforge()->generate(pixelforgeRequest());

    Http::assertSent(fn (ClientRequest $request): bool => $request->data() === [
        'prompt' => str_repeat('a', 400),
        'size' => '1024x1024',
        'quality' => 'medium',
    ]);
});

test('other api errors are reported as provider exceptions', function () {
    Http::fake([PIXELFORGE_URL.'/generations' => Http::response(['error' => ['code' => 'validation_failed', 'message' => 'Bad size']], 422)]);

    pixelforge()->generate(pixelforgeRequest());
})->throws(ProviderException::class, 'Bad size');

test('rate limits and outages carry the retry after delay', function (int $status, string $exception) {
    Http::fake([PIXELFORGE_URL.'/generations' => Http::response(['error' => ['code' => 'busy', 'message' => 'Busy']], $status, ['Retry-After' => '5'])]);

    try {
        pixelforge()->generate(pixelforgeRequest());
        $this->fail('Expected an exception.');
    } catch (RateLimitedException|ProviderUnavailableException $caught) {
        expect($caught)->toBeInstanceOf($exception)
            ->and($caught->retryAfterSeconds)->toBe(5);
    }
})->with([
    'rate limited' => [429, RateLimitedException::class],
    'overloaded' => [503, ProviderUnavailableException::class],
]);

test('each api error maps to its specific exception', function (int $status, ?string $code, string $exception) {
    Http::fake([PIXELFORGE_URL.'/generations/gen_missing' => Http::response(['error' => ['code' => $code, 'message' => 'Nope']], $status)]);

    expect(fn () => pixelforge()->generation('gen_missing'))->toThrow($exception);
})->with([
    'invalid key' => [401, 'invalid_api_key', InvalidCredentialsException::class],
    'not found' => [404, 'not_found', GenerationNotFoundException::class],
    'content policy' => [400, 'content_policy_violation', PromptRejectedException::class],
]);

test('connection failures are reported as the provider being unavailable', function () {
    Http::fake([PIXELFORGE_URL.'/*' => Http::failedConnection()]);

    pixelforge()->generation('gen_123');
})->throws(ProviderUnavailableException::class);

test('a succeeded generation maps its image, usage and rate snapshot', function () {
    Http::fake([PIXELFORGE_URL.'/generations/gen_123' => Http::response(pixelforgeGeneration([
        'status' => 'succeeded',
        'image_url' => 'https://cdn.pixelforge.test/gen_123.png',
        'image_url_expires_at' => '2026-11-05T14:03:29Z',
        'usage' => ['input_text_tokens' => 100, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 8000, 'total_tokens' => 16080],
        'metadata' => ['trance_generation_id' => 'local-generation-uuid'],
        'started_at' => '2026-11-04T14:03:05Z',
        'completed_at' => '2026-11-04T14:03:29Z',
    ]))]);

    $generation = pixelforge()->generation('gen_123');
    $output = $generation->outputs[0];

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($output->url)->toBe('https://cdn.pixelforge.test/gen_123.png')
        ->and([$output->width, $output->height])->toBe([1536, 1024])
        ->and($output->expiresAt->toIso8601ZuluString())->toBe('2026-11-05T14:03:29Z')
        ->and($generation->usage->totalTokens())->toBe(16080)
        ->and($generation->completedAt->toIso8601ZuluString())->toBe('2026-11-04T14:03:29Z')
        ->and(GenerationCost::calculate($generation->usage, $generation->pricing)->total())->toBe(373_800_000)
        ->and($generation->generationId())->toBe('local-generation-uuid');
});

test('a failed generation maps its error without outputs', function () {
    Http::fake([PIXELFORGE_URL.'/generations/gen_123' => Http::response(pixelforgeGeneration([
        'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'Could not render.'],
    ]))]);

    $generation = pixelforge()->generation('gen_123');

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->outputs)->toBe([])
        ->and($generation->error->code)->toBe('generation_failed');
});

test('listing generations returns the next cursor only when there are more', function () {
    Http::fake([PIXELFORGE_URL.'/generations?*' => Http::response([
        'object' => 'list',
        'data' => [pixelforgeGeneration(), pixelforgeGeneration(['id' => 'gen_456'])],
        'has_more' => true,
        'next_cursor' => 'cur_2',
    ])]);

    $page = pixelforge()->generations(limit: 2, cursor: 'cur_1');

    expect(array_map(fn ($generation) => $generation->externalId, $page->generations))->toBe(['gen_123', 'gen_456'])
        ->and($page->nextCursor)->toBe('cur_2');

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === PIXELFORGE_URL.'/generations?limit=2&cursor=cur_1');
});

test('the worst case usage is estimated from the published token tables', function () {
    Http::fake([PIXELFORGE_URL.'/pricing' => Http::response(pixelforgePricing())]);

    $usage = pixelforge()->estimateUsage(pixelforgeRequest(
        options: ['size' => '1536x1024', 'quality' => 'high'],
        references: [new ReferenceAsset('https://a.test/1.png', 'image/png'), new ReferenceAsset('https://a.test/2.png', 'image/png')],
    ));

    expect($usage->inputTextTokens())->toBe(115)
        ->and($usage->inputImageTokens())->toBe(1530)
        ->and($usage->outputTokens())->toBe(6450)
        ->and($usage->reasoningTokens())->toBe(8000);
});

test('pricing is fetched once and cached', function () {
    Http::fake([PIXELFORGE_URL.'/pricing' => Http::response(pixelforgePricing())]);

    $catalog = pixelforge()->pricing(pixelforgeRequest());
    pixelforge()->estimateUsage(pixelforgeRequest());

    expect($catalog->outputTokenRate()->toDecimal())->toBe('40.000000000');
    Http::assertSentCount(1);
});

test('a correctly signed webhook is translated into a provider neutral event', function () {
    $event = pixelforge()->handleWebhook(signedWebhook([
        'id' => 'evt_1',
        'object' => 'event',
        'type' => 'generation.processing',
        'created_at' => '2026-11-04T14:03:05Z',
        'data' => ['object' => pixelforgeGeneration(['status' => 'processing', 'metadata' => ['trance_generation_id' => 'local-generation-uuid']])],
    ]));

    expect($event->id)->toBe('evt_1')
        ->and($event->type)->toBe('generation.processing')
        ->and($event->generation->status)->toBe(GenerationStatus::Processing)
        ->and($event->generation->metadata)->toBe(['trance_generation_id' => 'local-generation-uuid']);
});

test('ping webhooks carry no generation', function () {
    $event = pixelforge()->handleWebhook(signedWebhook(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'created_at' => '2026-11-04T14:03:05Z', 'data' => []]));

    expect($event->generation)->toBeNull();
});

test('webhooks with a wrong secret, stale timestamp or missing signature are rejected', function (Request $request) {
    pixelforge()->handleWebhook($request);
})->with([
    'wrong secret' => fn () => signedWebhook(['id' => 'evt_1', 'type' => 'ping', 'created_at' => '2026-11-04T14:03:05Z'], secret: 'whsec_other'),
    'stale timestamp' => fn () => signedWebhook(['id' => 'evt_1', 'type' => 'ping', 'created_at' => '2026-11-04T14:03:05Z'], timestamp: now()->getTimestamp() - 301),
    'missing signature' => fn () => Request::create('/api/hook/pixelforge', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{}'),
])->throws(InvalidWebhookSignatureException::class);

test('webhooks are rejected when no signing secret is configured', function () {
    config(['services.pixelforge.webhook_secret' => null]);

    pixelforge()->handleWebhook(signedWebhook(['id' => 'evt_1', 'type' => 'ping', 'created_at' => '2026-11-04T14:03:05Z']));
})->throws(InvalidWebhookSignatureException::class);

test('the webhook endpoint accepts a signed pixelforge delivery', function () {
    Event::fake([ProviderWebhookReceived::class]);
    $request = signedWebhook(['id' => 'evt_1', 'object' => 'event', 'type' => 'generation.succeeded', 'created_at' => '2026-11-04T14:03:29Z', 'data' => ['object' => pixelforgeGeneration(['status' => 'succeeded'])]]);

    $this->call('POST', route('webhooks.handle', 'pixelforge'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => $request->header('Pixelforge-Signature'),
    ], content: $request->getContent())->assertOk();

    Event::assertDispatched(ProviderWebhookReceived::class, fn (ProviderWebhookReceived $received): bool => $received->provider === 'pixelforge'
        && $received->event->generation?->status === GenerationStatus::Succeeded);
});
