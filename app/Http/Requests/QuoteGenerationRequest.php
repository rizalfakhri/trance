<?php

namespace App\Http\Requests;

use App\Contracts\Providers\Capabilities\ModelCapabilities;
use App\Data\GenerationRequest;
use App\Data\ReferenceAsset;
use App\Enums\OptionType;
use App\Exceptions\Providers\ProviderException;
use App\Registries\ProviderRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuoteGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $registry = app(ProviderRegistry::class);
        $rules = [
            'provider' => ['required', 'string', Rule::in(array_keys($registry->enabled()))],
            'model' => ['nullable', 'string'],
            'prompt' => ['nullable', 'string'],
            'options' => ['array'],
            'reference_types' => ['array'],
            'reference_urls' => ['array'],
            'reference_urls.*' => ['string', 'url:http,https', 'max:2048'],
        ];

        $provider = $this->string('provider')->toString();

        if ($registry->has($provider)) {
            $modelIds = array_map(fn (ModelCapabilities $model): ?string => $model->id(), $registry->get($provider)->capabilities()->models());
            $rules['model'][] = Rule::in(array_filter($modelIds));
        }

        $model = $this->modelCapabilities();

        if ($model === null) {
            return $rules;
        }

        $rules['prompt'][] = 'max:'.$model->prompt()->maxLength();
        $rules['reference_types'][] = 'max:'.($model->references()?->maxCount() ?? 0);
        $rules['reference_types.*'] = ['string', Rule::in($model->references()?->acceptedMimeTypes() ?? [])];

        foreach ($model->options() as $option) {
            $rules["options.{$option->key()}"] = $option->rules();
        }

        return $rules;
    }

    public function modelCapabilities(): ?ModelCapabilities
    {
        $registry = app(ProviderRegistry::class);
        $provider = $this->string('provider')->toString();

        if (! $registry->has($provider)) {
            return null;
        }

        try {
            return $registry->get($provider)->capabilities()->model($this->input('model'));
        } catch (ProviderException) {
            return null;
        }
    }

    public function toGenerationRequest(): GenerationRequest
    {
        $model = $this->modelCapabilities();
        $options = [];

        foreach ($model?->options() ?? [] as $option) {
            $value = $this->validated("options.{$option->key()}", $option->default());

            if ($value !== null) {
                $options[$option->key()] = match ($option->type()) {
                    OptionType::Number => (int) $value,
                    OptionType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    OptionType::Select => $value,
                };
            }
        }

        return new GenerationRequest(
            prompt: (string) $this->validated('prompt'),
            modality: $model->modality(),
            model: $model->id(),
            options: $options,
            references: $this->referenceAssets(),
        );
    }

    /**
     * @return list<string>
     */
    public function referenceUrls(): array
    {
        return array_values($this->validated('reference_urls', []));
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $maxCount = $this->modelCapabilities()?->references()?->maxCount() ?? 0;

            if ($this->referenceCount() > $maxCount) {
                $validator->errors()->add('references', "You can add up to {$maxCount} reference images.");
            }
        }];
    }

    protected function referenceCount(): int
    {
        return count((array) $this->input('reference_types', [])) + count((array) $this->input('reference_urls', []));
    }

    /**
     * Uploaded references have no URL yet (only their type matters for the quote); linked
     * references are passed to the provider as given.
     *
     * @return list<ReferenceAsset>
     */
    protected function referenceAssets(): array
    {
        return [
            ...array_map(fn (string $mimeType): ReferenceAsset => new ReferenceAsset('', $mimeType), array_values($this->validated('reference_types', []))),
            ...array_map(fn (string $url): ReferenceAsset => new ReferenceAsset($url, 'image/*'), $this->referenceUrls()),
        ];
    }
}
