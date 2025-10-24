<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\User;
use Hub\AuditLogger;

Auth::requireRole(['super_admin']);

$method = $_SERVER['REQUEST_METHOD'];
$userModel = new User();
$currentUser = Auth::getCurrentUser();

try {
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // List users (super admin only)
        try {
            // Check if requesting pending users only
            if (isset($_GET['pending']) && $_GET['pending'] === 'true') {
                $users = $userModel->getPending();
            } else {
                $users = $userModel->getAll();
            }
            jsonResponse($users);
        } catch (\Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;        case 'PUT':
            Auth::requireRole(['super_admin']);
            
            // Parse JSON body
            $input = json_decode(file_get_contents('php://input'), true);
            
            $userId = $_GET['id'] ?? null;
            $action = $input['action'] ?? null;
            $csrfToken = $input['csrf_token'] ?? null;

            if (!$userId || !$action) {
                jsonResponse(['error' => 'User ID and action required'], 400);
            }

            // Verify CSRF token
            if (!verifyCsrfToken($csrfToken)) {
                jsonResponse(['error' => 'Invalid CSRF token'], 403);
            }

            // Prevent self-modification
            if ($userId == $currentUser['id']) {
                jsonResponse(['error' => 'Cannot modify your own account'], 403);
            }

            // Get old user data for audit log
            $oldUser = $userModel->getById($userId);
            
            switch ($action) {
                case 'change_role':
                    $role = $input['role'] ?? null;
                    if (!in_array($role, ['staff', 'maintenance', 'maintenance_director', 'counselor', 'custodial', 'substitute_manager', 'principal', 'cafeteria', 'admin', 'super_admin'])) {
                        jsonResponse(['error' => 'Invalid role'], 400);
                    }
                    $userModel->updateRole($userId, $role);
                    
                    // Audit log
                    AuditLogger::logUpdate(
                        'users',
                        $userId,
                        ['role' => $oldUser['role']],
                        ['role' => $role, 'changed_by' => $currentUser['name']]
                    );
                    break;

                case 'approve':
                    $userModel->approve($userId, $currentUser['id']);
                    // Send approval email
                    $approvedUser = $userModel->getById($userId);
                    if ($approvedUser) {
                        require_once __DIR__ . '/../../src/Invitation.php';
                        $inv = new \Hub\Invitation();
                        $inv->sendApprovalEmail($approvedUser['email']);
                    }
                    
                    // Audit log
                    $logger = new AuditLogger();
                    $logger->log(
                        'approve',
                        'users',
                        $userId,
                        ['is_active' => $oldUser['is_active']],
                        ['is_active' => 1, 'approved_by' => $currentUser['name'], 'email' => $approvedUser['email']]
                    );
                    break;

                case 'deactivate':
                    $userModel->deactivate($userId);
                    
                    // Audit log
                    AuditLogger::logUpdate(
                        'users',
                        $userId,
                        ['is_active' => $oldUser['is_active']],
                        ['is_active' => 0, 'deactivated_by' => $currentUser['name']]
                    );
                    break;

                case 'activate':
                    $userModel->activate($userId);
                    
                    // Audit log
                    AuditLogger::logUpdate(
                        'users',
                        $userId,
                        ['is_active' => $oldUser['is_active']],
                        ['is_active' => 1, 'activated_by' => $currentUser['name']]
                    );
                    break;

                default:
                    jsonResponse(['error' => 'Invalid action'], 400);
            }

            jsonResponse(['success' => true]);
            break;

        case 'DELETE':
            Auth::requireRole(['super_admin']);
            
            $userId = $_GET['id'] ?? null;
            
            if (!$userId) {
                jsonResponse(['error' => 'User ID required'], 400);
            }

            // Prevent self-deletion
            if ($userId == $currentUser['id']) {
                jsonResponse(['error' => 'Cannot delete your own account'], 403);
            }

            // Get user data before deletion for audit log
            $deletedUser = $userModel->getById($userId);
            
            $userModel->delete($userId);
            
            // Audit log
            AuditLogger::logDelete(
                'users',
                $userId,
                [
                    'name' => $deletedUser['name'],
                    'email' => $deletedUser['email'],
                    'role' => $deletedUser['role'],
                    'deleted_by' => $currentUser['name']
                ]
            );
            
            jsonResponse(['success' => true]);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("User API error: " . $e->getMessage());
    jsonResponse(['error' => 'An error occurred'], 500);
}
