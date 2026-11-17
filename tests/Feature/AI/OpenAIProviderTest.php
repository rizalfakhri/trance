<?php

use App\AI\Providers\OpenAI\OpenAIProvider;
use App\Data\GenerationCost;
use App\Data\GenerationRequest;
use App\Data\ReferenceAsset;
use App\Enums\GenerationStatus;
use App\Enums\Modality;
use App\Exceptions\Providers\InvalidCredentialsException;
use App\Exceptions\Providers\PromptRejectedException;
use App\Exceptions\Providers\ProviderException;
use App\Exceptions\Providers\ProviderUnavailableException;
use App\Exceptions\Providers\RateLimitedException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

const OPENAI_TEST_URL = 'https://openai.test/v1';

beforeEach(function () {
    config([
        'services.openai.base_url' => OPENAI_TEST_URL,
        'services.openai.token' => 'sk-test',
    ]);
});

function openai(): OpenAIProvider
{
    return app(OpenAIProvider::class);
}

function openaiRequest(array $options = [], array $references = [], ?string $model = null): GenerationRequest
{
    return new GenerationRequest(
        prompt: str_repeat('a', 400),
        modality: Modality::Image,
        model: $model,
        options: $options,
        references: $references,
        metadata: [GenerationRequest::GENERATION_ID_METADATA_KEY => 'local-uuid', GenerationRequest::ATTEMPT_METADATA_KEY => '1'],
        idempotencyKey: 'local-uuid',
    );
}

/**
 * @return array<string, mixed>
 */
function openaiImageResponse(): array
{
    return [
        'created' => 1_763_000_000,
        'data' => [['b64_json' => base64_encode('png-bytes')]],
        'usage' => [
            'input_tokens' => 1700,
            'input_tokens_details' => ['text_tokens' => 100, 'image_tokens' => 1600],
            'output_tokens' => 6000,
            'total_tokens' => 7700,
        ],
    ];
}

test('capabilities expose both gpt image models with size, quality and up to 16 references', function () {
    $capabilities = openai()->capabilities();

    expect(array_map(fn ($model) => $model->id(), $capabilities->models()))->toBe(['gpt-image-2.6-sunburst', 'gpt-image-2.6-flare'])
        ->and($capabilities->model()->option('quality')->default())->toBe('medium')
        ->and($capabilities->model()->references()->maxCount())->toBe(16);
});

test('generating without references calls the generations endpoint and returns a finished image', function () {
    Http::fake([OPENAI_TEST_URL.'/images/generations' => Http::response(openaiImageResponse())]);

    $generation = openai()->generate(openaiRequest(['size' => '1536x1024', 'quality' => 'high'], model: 'gpt-image-2.6-flare'));

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->externalId)->toBe('openai_local-uuid')
        ->and($generation->outputs[0]->url)->toBe('data:image/png;base64,'.base64_encode('png-bytes'))
        ->and([$generation->outputs[0]->width, $generation->outputs[0]->height])->toBe([1536, 1024])
        ->and($generation->usage->inputTextTokens())->toBe(100)
        ->and($generation->usage->inputImageTokens())->toBe(1600)
        ->and($generation->usage->outputTokens())->toBe(6000)
        ->and($generation->generationId())->toBe('local-uuid')
        ->and(GenerationCost::calculate($generation->usage, $generation->pricing)->total())->toBe(193_300_000);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === OPENAI_TEST_URL.'/images/generations'
        && $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['model'] === 'gpt-image-2.6-flare'
        && $request['size'] === '1536x1024'
        && $request['quality'] === 'high'
        && $request['safety_identifier'] === hash('sha256', 'local-uuid'));
});

test('generating with references calls the edits endpoint with the reference urls', function () {
    Http::fake([OPENAI_TEST_URL.'/images/edits' => Http::response(openaiImageResponse())]);

    openai()->generate(openaiRequest(references: [new ReferenceAsset('https://trance.test/signed/1', 'image/png')]));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === OPENAI_TEST_URL.'/images/edits'
        && $request['images'] === [['image_url' => 'https://trance.test/signed/1']]
        && $request['model'] === 'gpt-image-2.6-sunburst');
});

test('api errors are translated into provider exceptions', function (int $status, array $error, array $headers, string $exception) {
    Http::fake([OPENAI_TEST_URL.'/images/generations' => Http::response(['error' => $error], $status, $headers)]);

    expect(fn () => openai()->generate(openaiRequest()))->toThrow($exception);
})->with([
    'invalid key' => [401, ['message' => 'Incorrect API key', 'code' => 'invalid_api_key'], [], InvalidCredentialsException::class],
    'rate limited' => [429, ['message' => 'Slow down', 'code' => 'slow_down'], ['Retry-After' => '3'], RateLimitedException::class],
    'out of credits' => [429, ['message' => 'No credits', 'code' => 'credit_balance_exhausted'], [], ProviderException::class],
    'moderation' => [400, ['message' => 'Your request was rejected by the safety system.', 'code' => 'moderation_blocked'], [], PromptRejectedException::class],
    'overloaded' => [503, ['message' => 'Overloaded', 'code' => 'server_is_overloaded'], [], ProviderUnavailableException::class],
]);

test('exhausted credits are not retried as a rate limit', function () {
    Http::fake([OPENAI_TEST_URL.'/images/generations' => Http::response(['error' => ['message' => 'No credits', 'code' => 'credit_balance_exhausted']], 429)]);

    expect(fn () => openai()->generate(openaiRequest()))->not->toThrow(RateLimitedException::class)
        ->and(fn () => openai()->generate(openaiRequest()))->toThrow(ProviderException::class);
});

test('pricing follows the requested model and the estimate covers the worst case', function () {
    $request = openaiRequest(['size' => '1024x1536', 'quality' => 'high'], [new ReferenceAsset('', 'image/png')]);
    $usage = openai()->estimateUsage($request);

    expect(openai()->pricing($request)->outputTokenRate()->toDecimal())->toBe('30.000000000')
        ->and($usage->inputTextTokens())->toBe(115)
        ->and($usage->inputImageTokens())->toBe(1600)
        ->and($usage->outputTokens())->toBe(9400)
        ->and($usage->reasoningTokens())->toBe(0);
});
