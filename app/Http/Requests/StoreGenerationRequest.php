<?php

namespace App\Http\Requests;

use App\Data\ReferenceAsset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class StoreGenerationRequest extends QuoteGenerationRequest
{
    private const MAX_REFERENCE_KILOBYTES = 10_240;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $references = $this->modelCapabilities()?->references();

        unset($rules['reference_types'], $rules['reference_types.*']);

        $rules['prompt'] = ['required', 'string', ...array_slice((array) $rules['prompt'], 2)];
        $rules['references'] = ['array'];
        $rules['references.*'] = [
            'file',
            'mimetypes:'.implode(',', $references?->acceptedMimeTypes() ?? []),
            'max:'.self::MAX_REFERENCE_KILOBYTES,
        ];

        return $rules;
    }

    /**
     * @return list<UploadedFile>
     */
    public function referenceFiles(): array
    {
        return array_values($this->file('references', []));
    }

    protected function referenceCount(): int
    {
        return count($this->file('references', [])) + count((array) $this->input('reference_urls', []));
    }

    protected function referenceAssets(): array
    {
        return [
            ...array_map(fn (UploadedFile $file): ReferenceAsset => new ReferenceAsset('', (string) $file->getMimeType()), $this->referenceFiles()),
            ...array_map(fn (string $url): ReferenceAsset => new ReferenceAsset($url, 'image/*'), $this->referenceUrls()),
        ];
    }
}
