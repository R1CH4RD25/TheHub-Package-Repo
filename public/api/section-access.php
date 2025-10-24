<?php
/**
 * Section Access API
 * Manage user access to different sections of the platform
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\SectionAccess;
use Hub\User;
use Hub\AuditLogger;

// Only super admins can manage section access
Auth::requireRole(['super_admin', 'admin']);

$sectionAccess = new SectionAccess();
$userModel = new User();
$currentUser = Auth::getCurrentUser();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get all users with their section access
        $users = $userModel->getAll();
        $sections = $sectionAccess->getAllSections();
        
        // Build a matrix of user => sections
        $accessMatrix = [];
        foreach ($users as $user) {
            $userSections = $sectionAccess->getUserSections($user['id']);
            $accessMatrix[] = [
                'user' => $user,
                'sections' => $userSections
            ];
        }
        
        jsonResponse([
            'users' => $users,
            'sections' => $sections,
            'accessMatrix' => $accessMatrix
        ]);
        break;

    case 'POST':
        // Grant access to a section
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
        $sectionSlug = $_POST['section_slug'] ?? '';
        $role = $_POST['role'] ?? 'staff';
        $grantedBy = Auth::getUser()['id'];

        if (!$userId || !$sectionSlug) {
            jsonResponse(['error' => 'User ID and Section are required'], 400);
        }

        try {
            $sectionAccess->grantAccess($userId, $sectionSlug, $role, $grantedBy);
            
            // Get user and section details for audit log
            $user = $userModel->getById($userId);
            
            // Audit log
            $logger = new AuditLogger();
            $logger->log(
                'grant_access',
                'section_access',
                $userId,
                null,
                [
                    'user_name' => $user['name'],
                    'user_email' => $user['email'],
                    'section' => $sectionSlug,
                    'role' => $role,
                    'granted_by' => $currentUser['name']
                ]
            );
            
            jsonResponse(['success' => true, 'message' => 'Access granted successfully']);
        } catch (\Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;

    case 'DELETE':
        // Revoke access from a section
        parse_str(file_get_contents('php://input'), $_DELETE);
        
        if (!verifyCsrfToken($_DELETE['csrf_token'] ?? '')) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $userId = filter_var($_DELETE['user_id'] ?? '', FILTER_VALIDATE_INT);
        $sectionSlug = $_DELETE['section_slug'] ?? '';

        if (!$userId || !$sectionSlug) {
            jsonResponse(['error' => 'User ID and Section are required'], 400);
        }

        try {
            // Get user details before revoking for audit log
            $user = $userModel->getById($userId);
            
            $sectionAccess->revokeAccess($userId, $sectionSlug);
            
            // Audit log
            $logger = new AuditLogger();
            $logger->log(
                'revoke_access',
                'section_access',
                $userId,
                [
                    'user_name' => $user['name'],
                    'section' => $sectionSlug,
                    'had_access' => true
                ],
                [
                    'revoked_by' => $currentUser['name']
                ]
            );
            
            jsonResponse(['success' => true, 'message' => 'Access revoked successfully']);
        } catch (\Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
