<?php

namespace App\Http\Controllers;

use App\Exceptions\Billing\InsufficientBalanceException;
use App\Exceptions\Providers\ProviderException;
use App\Generations\RetryGeneration;
use App\Models\Generation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;

class GenerationRetryController extends Controller
{
    public function __invoke(Request $request, Generation $generation, RetryGeneration $retryGeneration): RedirectResponse
    {
        abort_unless($generation->user()->is($request->user()), 404);

        try {
            $retryGeneration->handle($generation);
        } catch (InsufficientBalanceException) {
            throw ValidationException::withMessages([
                'balance' => "You don't have enough balance to generate this image. Top up your credits to continue.",
            ]);
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['generation' => $exception->getMessage()]);
        } catch (ProviderException) {
            throw ValidationException::withMessages(['generation' => 'The provider is unavailable. Please try again.']);
        }

        return to_route('generations.show', $generation);
    }
}
