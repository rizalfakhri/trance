<?php

use App\Enums\Modality;
use App\Exceptions\Providers\UnsupportedCapabilityException;
use Tests\Fixtures\Providers\FakeImageProvider;
use Tests\Fixtures\Providers\FakeVideoProvider;

test('capabilities serialize into the shape the ui renders from', function () {
    $capabilities = json_decode(json_encode((new FakeImageProvider)->capabilities()), true);

    expect($capabilities)->toBe([
        'provider' => 'fake-image',
        'label' => 'Fake Image',
        'logoUrl' => null,
        'darkLogoUrl' => null,
        'models' => [[
            'id' => null,
            'label' => 'Default',
            'modality' => 'image',
            'prompt' => ['maxLength' => 4000],
            'options' => [[
                'key' => 'size',
                'label' => 'Size',
                'type' => 'select',
                'choices' => [
                    ['value' => '1024x1024', 'label' => '1024×1024', 'hint' => 'Square'],
                    ['value' => '1536x1024', 'label' => '1536×1024', 'hint' => 'Landscape'],
                ],
                'default' => '1024x1024',
                'min' => null,
                'max' => null,
                'step' => null,
            ]],
            'references' => ['maxCount' => 4, 'acceptedMimeTypes' => ['image/*'], 'modality' => 'image'],
            'outputsPerRequest' => 1,
        ]],
    ]);
});

test('the default model is returned when no model id is given', function () {
    expect((new FakeVideoProvider)->capabilities()->model()->id())->toBe('clip-1');
});

test('models are looked up by id', function () {
    expect((new FakeVideoProvider)->capabilities()->model('clip-1')->label())->toBe('Clip 1');
});

test('unknown models are rejected', function () {
    (new FakeVideoProvider)->capabilities()->model('missing');
})->throws(UnsupportedCapabilityException::class);

test('providers report the modalities their models support', function () {
    $capabilities = (new FakeVideoProvider)->capabilities();

    expect($capabilities->supports(Modality::Video))->toBeTrue()
        ->and($capabilities->supports(Modality::Image))->toBeFalse();
});

test('options are looked up by key', function () {
    $model = (new FakeImageProvider)->capabilities()->model();

    expect($model->option('size')?->label())->toBe('Size')
        ->and($model->option('missing'))->toBeNull();
});
