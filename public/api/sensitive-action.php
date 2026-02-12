<?php

/**
 * Sensitive Action Token API
 *
 * Endpoints:
 *   POST   /api/package/sensitive-action/request  → Issue a reveal token
 *   POST   /api/package/sensitive-action/consume   → Consume a token and get data
 *   POST   /api/package/sensitive-action/validate  → Check token validity
 *   POST   /api/package/sensitive-action/revoke    → Revoke a token
 *
 * All endpoints require authentication and CSRF token.
 *
 * @see Hub\Package\SensitiveActionManager
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Package\SensitiveActionManager;
use Hub\Package\PolicyEngine;

// Require login
Auth::requireLogin();

$user = Auth::getCurrentUser();
$userId = $user['id'] ?? null;

if (!$userId) {
    jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
}

// Parse action from query string
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Verify CSRF
verifyCsrfToken();

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$manager = new SensitiveActionManager();

try {
    switch ($action) {
        case 'request':
            // Issue a new reveal token
            $packageId = $input['packageId'] ?? '';
            $actionName = $input['actionName'] ?? '';
            $reason = $input['reason'] ?? '';
            $targetRecordId = isset($input['targetRecordId']) ? (int)$input['targetRecordId'] : null;
            $ttl = isset($input['ttl']) ? (int)$input['ttl'] : 30;

            if (!$packageId || !$actionName) {
                jsonResponse(['success' => false, 'error' => 'Package ID and action name required'], 400);
            }

            // Check permission via PolicyEngine
            $policy = new PolicyEngine();
            $userObj = \Hub\User::find($userId);

            if (!$userObj) {
                jsonResponse(['success' => false, 'error' => 'User not found'], 404);
            }

            // Require at least manager role for sensitive actions
            if (!$policy->hasRole($userObj, 'manager')) {
                jsonResponse(['success' => false, 'error' => 'Insufficient privileges for sensitive actions'], 403);
            }

            $result = $manager->issueToken(
                $userObj,
                $packageId,
                $actionName,
                $reason,
                $targetRecordId,
                $ttl
            );

            jsonResponse([
                'success' => true,
                'token' => $result['token'],
                'expiresAt' => $result['expiresAt'],
                'expiresIn' => $result['expiresIn'],
            ]);
            break;

        case 'consume':
            // Consume a token
            $token = $input['token'] ?? '';

            if (!$token) {
                jsonResponse(['success' => false, 'error' => 'Token required'], 400);
            }

            $metadata = $manager->consumeToken($token, $userId);

            jsonResponse([
                'success' => true,
                'metadata' => $metadata,
            ]);
            break;

        case 'validate':
            // Check if token is still valid
            $token = $input['token'] ?? '';

            if (!$token) {
                jsonResponse(['success' => false, 'error' => 'Token required'], 400);
            }

            $result = $manager->validateToken($token, $userId);

            jsonResponse([
                'success' => true,
                'valid' => $result['valid'],
                'expiresIn' => $result['expiresIn'],
                'reason' => $result['reason'],
            ]);
            break;

        case 'revoke':
            // Revoke a token
            $token = $input['token'] ?? '';

            if (!$token) {
                jsonResponse(['success' => false, 'error' => 'Token required'], 400);
            }

            $revoked = $manager->revokeToken($token, $userId);

            jsonResponse([
                'success' => true,
                'revoked' => $revoked,
            ]);
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }
} catch (\RuntimeException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
} catch (\Exception $e) {
    error_log("[SensitiveAction API] Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
}
