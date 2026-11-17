<?php

use App\Models\Generation;
use App\Models\GenerationAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->generation = Generation::factory()->succeeded()->create();
    $this->output = GenerationAsset::factory()->output()->for($this->generation)->create([
        'path' => "generations/{$this->generation->id}/outputs/0.png",
    ]);
    Storage::disk('local')->put($this->output->path, 'png-bytes');
});

test('owners download the stored output as an attachment', function () {
    $this->actingAs($this->generation->user)
        ->get(route('generations.outputs.download', [$this->generation, $this->output]))
        ->assertOk()
        ->assertDownload('trance-'.substr($this->generation->id, 0, 8).'.png');
});

test('other users cannot download the output', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('generations.outputs.download', [$this->generation, $this->output]))
        ->assertNotFound();
});

test('outputs of another generation and reference images cannot be downloaded through this generation', function (string $case) {
    $asset = $case === 'reference'
        ? GenerationAsset::factory()->reference()->for($this->generation)->create(['position' => 1])
        : GenerationAsset::factory()->output()->create();

    $this->actingAs($this->generation->user)
        ->get(route('generations.outputs.download', [$this->generation, $asset]))
        ->assertNotFound();
})->with(['reference', 'another generation output']);

test('the generation page links each output to its download', function () {
    $this->actingAs($this->generation->user)
        ->get(route('generations.show', $this->generation))
        ->assertInertia(fn ($page) => $page->where(
            'generation.outputs.0.downloadUrl',
            route('generations.outputs.download', [$this->generation, $this->output]),
        ));
});
