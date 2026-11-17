<?php

namespace App\Http\Controllers;

use App\Http\Resources\GalleryItemResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    private const PER_PAGE = 24;

    public function index(Request $request): Response
    {
        $page = $request->user()
            ->generations()
            ->with('outputs')
            ->latest()
            ->orderByDesc('id')
            ->simplePaginate(self::PER_PAGE);

        return Inertia::render('Gallery', [
            'generations' => Inertia::merge(fn (): array => GalleryItemResource::collection($page->getCollection())->resolve($request)),
            'nextPage' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }
}
