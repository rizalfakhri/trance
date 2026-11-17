<?php

namespace App\Http\Controllers;

use App\Billing\Transactions;
use App\Http\Requests\TopUpBalanceRequest;
use Illuminate\Http\RedirectResponse;

class BalanceTopUpController extends Controller
{
    private const NANO_DOLLARS_PER_DOLLAR = 1_000_000_000;

    public function store(TopUpBalanceRequest $request, Transactions $transactions): RedirectResponse
    {
        $user = $request->user();

        $transactions->topUp(
            $user,
            $request->integer('amount') * self::NANO_DOLLARS_PER_DOLLAR,
            'Top-up',
            "top-up:{$user->getKey()}:{$request->string('idempotency_key')}",
        );

        return back();
    }
}
