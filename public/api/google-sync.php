<?php
/**
 * Google Groups Sync API
 * 
 * Admin-only endpoint to force-sync Google Groups → Organization Roles.
 * 
 * POST /api/google-sync.php
 *   action=check-config    — Verify Google Groups configuration
 *   action=preview-user    — Preview a user's groups without syncing (requires user_id)
 *   action=sync-user       — Sync a single user's groups → roles (requires user_id)
 *   action=sync-all        — Sync all active users
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\GoogleGroupSync;
use Hub\AuditLogger;

// Super admin only
Auth::requireRole(['super_admin']);

$currentUser = Auth::getCurrentUser();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Verify CSRF token
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verifyCsrfToken($csrfToken)) {
    jsonResponse(['error' => 'Invalid CSRF token'], 403);
}

$action = $input['action'] ?? '';

try {
    $sync = new GoogleGroupSync();

    switch ($action) {
        case 'check-config':
            $config = $sync->checkConfiguration();
            jsonResponse([
                'success' => true,
                'config' => $config,
            ]);
            break;

        case 'preview-user':
            $userId = (int) ($input['user_id'] ?? 0);
            if (!$userId) {
                jsonResponse(['error' => 'user_id is required'], 400);
            }

            $result = $sync->previewUser($userId);
            jsonResponse($result);
            break;

        case 'sync-user':
            $userId = (int) ($input['user_id'] ?? 0);
            if (!$userId) {
                jsonResponse(['error' => 'user_id is required'], 400);
            }

            $result = $sync->syncUser($userId, $currentUser['id']);
            jsonResponse($result);
            break;

        case 'sync-all':
            $result = $sync->syncAllUsers($currentUser['id']);
            jsonResponse([
                'success' => true,
                'summary' => [
                    'total' => $result['total'],
                    'synced' => $result['synced'],
                    'errors' => $result['errors'],
                    'skipped' => $result['skipped'],
                    'roles_assigned' => $result['roles_assigned'],
                ],
                'users' => $result['users'],
            ]);
            break;

        default:
            jsonResponse(['error' => 'Invalid action. Valid: check-config, preview-user, sync-user, sync-all'], 400);
    }

} catch (\Exception $e) {
    error_log("Google Sync API error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage(),
    ], 500);
}
