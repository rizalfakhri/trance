<?php

namespace App\Http\Controllers;

use App\Enums\AssetRole;
use App\Models\Generation;
use App\Models\GenerationAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerationOutputDownloadController extends Controller
{
    public function __invoke(Request $request, Generation $generation, GenerationAsset $output): StreamedResponse
    {
        abort_unless($generation->user()->is($request->user()) && $output->role === AssetRole::Output, 404);

        $extension = pathinfo($output->path, PATHINFO_EXTENSION);
        $filename = 'trance-'.substr($generation->id, 0, 8).($output->position > 0 ? "-{$output->position}" : '').".{$extension}";

        return Storage::disk($output->disk)->download($output->path, $filename);
    }
}
