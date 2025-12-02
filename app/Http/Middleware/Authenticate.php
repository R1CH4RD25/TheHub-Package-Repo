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
        // Check if user is logged in via PHP session
        // Auth.php stores: $_SESSION['user_id'], $_SESSION['email'], $_SESSION['role'], etc.
        $isLoggedIn = $request->session()->get('logged_in') ?? $_SESSION['logged_in'] ?? false;

        if (!$isLoggedIn) {
            return new RedirectResponse('/login.php');
        }

        // Build user array from session keys (matches Auth.php format)
        $user = [
            'id' => $request->session()->get('user_id') ?? $_SESSION['user_id'] ?? null,
            'email' => $request->session()->get('email') ?? $_SESSION['email'] ?? null,
            'name' => $request->session()->get('name') ?? $_SESSION['name'] ?? null,
            'role' => $request->session()->get('role') ?? $_SESSION['role'] ?? 'user',
            'picture' => $request->session()->get('picture') ?? $_SESSION['picture'] ?? null,
        ];

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
