<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Hub\Auth;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    /**
     * Handle an incoming request - integrate existing Auth system.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Laravel admin routes should use BOTH Laravel AND PHP sessions
        // Check Laravel session first, then fall back to PHP session
        
        $user = null;
        
        // Try Laravel session (for admin routes)
        if ($request->session()->has('user')) {
            $user = $request->session()->get('user');
        }
        
        // Fall back to PHP session (for legacy hub/modules)
        if (!$user) {
            // Start PHP session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Get user from PHP session via Auth class
            $user = Auth::getCurrentUser();
            
            // If found in PHP session, copy to Laravel session for future requests
            if ($user) {
                $request->session()->put('user', $user);
                $request->session()->save();
            }
        }

        if (!$user) {
            return new RedirectResponse('/login.php');
        }

        // Check role-based access if roles are specified
        if (!empty($roles) && !in_array($user['role'], $roles)) {
            return new JsonResponse([
                'error' => 'Access denied',
                'required_roles' => $roles,
                'your_role' => $user['role']
            ], 403);
        }

        // Attach user to request for controllers
        $request->attributes->set('user', $user);

        return $next($request);
    }
}
