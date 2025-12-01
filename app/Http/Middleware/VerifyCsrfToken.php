<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyCsrfToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // For now, bypass CSRF for testing
        // TODO: Integrate proper CSRF validation
        return $next($request);
    }
}
