<?php

namespace App\Http\Controllers;

use App\Billing\Transactions;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request, Transactions $transactions): Response
    {
        $user = $request->user();
        $page = $user->transactions()
            ->with('reference')
            ->withExists('resolvedEntries')
            ->latest()
            ->orderByDesc('id')
            ->simplePaginate(self::PER_PAGE);
        $summary = $transactions->summary($user);

        return Inertia::render('Transactions', [
            'summary' => fn (): array => [
                'available' => $summary->available(),
                'held' => $summary->held,
                'topUps' => $summary->topUps + $summary->credits,
                'spent' => $summary->usage - $summary->refunds,
                'currency' => 'usd',
            ],
            'transactions' => Inertia::merge(fn (): array => TransactionResource::collection($page->getCollection())->resolve($request)),
            'nextPage' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }
}
