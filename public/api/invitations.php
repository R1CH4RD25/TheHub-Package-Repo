<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Invitation;

// Only super admins can manage user invitations
Auth::requireRole(['super_admin']);

$method = $_SERVER['REQUEST_METHOD'];
$invitation = new Invitation();
$user = Auth::getCurrentUser();

try {
    switch ($method) {
        case 'GET':
            // Get all invitations
            $invitations = $invitation->getAll();
            jsonResponse($invitations);
            break;

        case 'POST':
            // Create new invitation
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }

            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'user';

            if (empty($email)) {
                jsonResponse(['error' => 'Email is required'], 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['error' => 'Invalid email address'], 400);
            }

            try {
                $invitationId = $invitation->create($email, $role, $user['id']);
                jsonResponse([
                    'success' => true,
                    'message' => 'Invitation sent to ' . $email,
                    'id' => $invitationId
                ], 201);
            } catch (\Exception $e) {
                jsonResponse(['error' => $e->getMessage()], 400);
            }
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Invitation API error: " . $e->getMessage());
    jsonResponse(['error' => 'An error occurred'], 500);
}
