<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Hub\ManagementCenter;
use Hub\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Management Authentication Middleware
 *
 * Checks that the logged-in user has access to at least one installed package.
 * Unlike the admin middleware (which checks for admin/super_admin role),
 * this uses ManagementCenter::userHasManagementAccess() which consults:
 *   - users.role (primary role)
 *   - user_global_roles (additional roles, e.g. maintenance_director)
 *   - section_role_access (per-section role gating)
 *   - section_installations (installed packages)
 *
 * Any user whose role(s) appear in section_role_access for an installed
 * package gains management access. Google Groups → role sync happens
 * at login time (Auth::syncOrganizationRoles), so by this point the
 * user_global_roles table already reflects group memberships.
 */
class ManagementAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Must be logged in first
        $isLoggedIn = $request->session()->get('logged_in')
            ?? $_SESSION['logged_in']
            ?? false;

        if (!$isLoggedIn) {
            return redirect('/login.php');
        }

        // Build user array from session
        $user = [
            'id'      => $request->session()->get('user_id')   ?? $_SESSION['user_id']   ?? null,
            'email'   => $request->session()->get('email')      ?? $_SESSION['email']     ?? null,
            'name'    => $request->session()->get('name')       ?? $_SESSION['name']      ?? null,
            'role'    => $request->session()->get('role')        ?? $_SESSION['role']      ?? 'user',
            'picture' => $request->session()->get('picture')    ?? $_SESSION['picture']   ?? null,
            'display_name' => $request->session()->get('display_name') ?? $_SESSION['display_name'] ?? null,
        ];

        $userId   = (int) $user['id'];
        $userRole = Auth::getEffectiveRole() ?: $user['role'];

        // Check management access via the existing ManagementCenter
        $mc = new ManagementCenter();
        if (!$mc->userHasManagementAccess($userId, $userRole)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Access denied',
                    'message' => 'You do not have access to any management packages.',
                ], 403);
            }
            return redirect('/hub.php');
        }

        // Attach user + role to request for controllers
        $user['effective_role'] = $userRole;
        $request->attributes->set('user', $user);

        return $next($request);
    }
}
