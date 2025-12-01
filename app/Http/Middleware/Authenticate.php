<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Hub\Auth;

class Authenticate
{
    /**
     * Handle an incoming request - integrate existing Auth system.
     */
    public function handle(Request $request, Closure $next)
    {
        // Use existing Auth class from src/Auth.php
        $user = Auth::getCurrentUser();

        if (!$user) {
            return redirect('/login.php');
        }

        // Attach user to request for controllers
        $request->merge(['user' => (object)$user]);

        return $next($request);
    }
}
