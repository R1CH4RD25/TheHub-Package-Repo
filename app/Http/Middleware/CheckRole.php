<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Check if the authenticated user has the required role.
     *
     * Usage in routes: ->middleware('role:super_admin')
     *                  ->middleware('role:admin,super_admin')
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$roles Allowed roles (comma-separated in route definition)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->attributes->get('user');

        if (!$user) {
            abort(403, 'Authentication required');
        }

        $userRole = $user['role'] ?? '';

        if (!in_array($userRole, $roles)) {
            abort(403, 'Insufficient permissions. Required role: ' . implode(' or ', $roles));
        }

        return $next($request);
    }
}
