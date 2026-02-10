<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Hub\{Auth as LegacyAuth, Invitation, AuditLogger};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * Display user management interface.
     */
    public function index(Request $request)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        return view('admin.users', [
            'isSuperAdmin' => $isSuperAdmin,
            'activeTab' => 'active' // Default tab
        ]);
    }

    /**
     * Display pending users page.
     */
    public function pending(Request $request)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        return view('admin.users', [
            'isSuperAdmin' => $isSuperAdmin,
            'activeTab' => 'pending'
        ]);
    }

    /**
     * Display invitations page.
     */
    public function invitations(Request $request)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        return view('admin.users', [
            'isSuperAdmin' => $isSuperAdmin,
            'activeTab' => 'invitations'
        ]);
    }

    /**
     * Display organization roles page.
     */
    public function roles(Request $request)
    {
        $currentUser = $request->attributes->get('user');
        $isSuperAdmin = ($currentUser['role'] === 'super_admin');

        return view('admin.users', [
            'isSuperAdmin' => $isSuperAdmin,
            'activeTab' => 'roles'
        ]);
    }

    /**
     * List all users (API endpoint).
     */
    public function list(Request $request): JsonResponse
    {
        $pending = $request->query('pending') === 'true';

        if ($pending) {
            // Pending users: have google_id but have never logged in (no last_login)
            // AND are inactive (is_active = false)
            $users = User::whereNotNull('google_id')
                ->whereNull('last_login')
                ->where('is_active', false)
                ->select('id', 'name', 'email', 'role', 'picture', 'is_active', 'last_login', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            // Return all users who have logged in at least once OR are active
            // (they've been approved implicitly or explicitly)
            $users = User::where(function($query) {
                    $query->whereNotNull('last_login')
                          ->orWhere('is_active', true);
                })
                ->select('id', 'name', 'email', 'role', 'picture', 'is_active', 'last_login')
                ->orderBy('name')
                ->get();
        }

        return response()->json($users);
    }

    /**
     * Update user (role change, approve, deactivate, delete).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->attributes->get('user');
        $action = $request->input('action');

        // Prevent self-modification
        if ($id == $currentUser['id']) {
            return response()->json(['error' => 'Cannot modify your own account'], 403);
        }

        $user = User::findOrFail($id);
        $oldData = $user->toArray();

        switch ($action) {
            case 'change_role':
                $newRole = $request->input('role');
                $validRoles = [
                    'staff',
                    'maintenance',
                    'maintenance_director',
                    'counselor',
                    'custodial',
                    'substitute_manager',
                    'principal',
                    'cafeteria',
                    'admin',
                    'super_admin'
                ];

                if (!in_array($newRole, $validRoles)) {
                    return response()->json(['error' => 'Invalid role'], 400);
                }

                $user->role = $newRole;
                $user->save();

                AuditLogger::logUpdate(
                    'users',
                    $id,
                    ['role' => $oldData['role']],
                    ['role' => $newRole, 'changed_by' => $currentUser['name']]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'User role updated successfully'
                ]);

            case 'approve':
                $user->is_active = true;
                $user->approved_by = $currentUser['id'];
                $user->approved_at = now();
                $user->save();

                // Send approval email
                $invitation = new Invitation();
                $invitation->sendApprovalEmail($user->email);

                $logger = new AuditLogger();
                $logger->log(
                    'approve',
                    'users',
                    $id,
                    ['is_active' => $oldData['is_active']],
                    [
                        'is_active' => 1,
                        'approved_by' => $currentUser['name'],
                        'email' => $user->email
                    ]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'User approved and email sent'
                ]);

            case 'deactivate':
                $user->is_active = false;
                $user->save();

                AuditLogger::logUpdate(
                    'users',
                    $id,
                    ['is_active' => $oldData['is_active']],
                    ['is_active' => 0, 'deactivated_by' => $currentUser['name']]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'User deactivated'
                ]);

            case 'reactivate':
                $user->is_active = true;
                $user->save();

                AuditLogger::logUpdate(
                    'users',
                    $id,
                    ['is_active' => $oldData['is_active']],
                    ['is_active' => 1, 'reactivated_by' => $currentUser['name']]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'User reactivated'
                ]);

            case 'delete':
                // Check if user can be deleted
                if (!LegacyAuth::canDeleteUser($id)) {
                    return response()->json([
                        'error' => 'Cannot delete this user (super admin or self)'
                    ], 403);
                }

                AuditLogger::logDelete(
                    'users',
                    $id,
                    $oldData
                );

                $user->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);

            default:
                return response()->json(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Get invitations list (API endpoint).
     */
    public function invitationsList(Request $request): JsonResponse
    {
        $invitation = new Invitation();
        $invites = $invitation->getAll();

        return response()->json($invites);
    }

    /**
     * Send new invitation.
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:staff,maintenance,maintenance_director,counselor,custodial,substitute_manager,principal,cafeteria,admin'
        ]);

        $currentUser = $request->attributes->get('user');
        $invitation = new Invitation();

        try {
            $token = $invitation->create(
                $request->input('email'),
                $request->input('role'),
                $currentUser['id']
            );

            // Send email
            $appUrl = env('APP_URL', 'https://hub.woodsonisd.net');
            $invitationUrl = "{$appUrl}/accept-invitation.php?token={$token}";

            $invitation->sendInvitationEmail(
                $request->input('email'),
                $invitationUrl
            );

            // Audit log
            $logger = new AuditLogger();
            $logger->log(
                'create',
                'invitations',
                null,
                null,
                [
                    'email' => $request->input('email'),
                    'role' => $request->input('role'),
                    'invited_by' => $currentUser['name']
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke invitation.
     */
    public function revokeInvitation(Request $request, int $id): JsonResponse
    {
        $invitation = new Invitation();
        $currentUser = $request->attributes->get('user');

        try {
            $invite = $invitation->getById($id);

            if (!$invite) {
                return response()->json(['error' => 'Invitation not found'], 404);
            }

            $invitation->revoke($id);

            // Audit log
            $logger = new AuditLogger();
            $logger->log(
                'delete',
                'invitations',
                $id,
                $invite,
                ['revoked_by' => $currentUser['name']]
            );

            return response()->json([
                'success' => true,
                'message' => 'Invitation revoked'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
