<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberIntendedRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $redirectPath = $request->query('redirect');

        if (is_string($redirectPath) && $this->isLocalPath($redirectPath)) {
            $request->session()->put('url.intended', url($redirectPath));
        }

        return $next($request);
    }

    private function isLocalPath(string $path): bool
    {
        return str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ! str_contains($path, '\\');
    }
}
