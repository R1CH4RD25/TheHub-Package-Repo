<?php
/**
 * User Global Roles API
 * Manage multiple platform-wide roles for users
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;

// Only admins and above can manage user roles
Auth::requireRole(['admin', 'super_admin']);

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get roles for a specific user
        $userId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
        
        if (!$userId) {
            jsonResponse(['error' => 'User ID required'], 400);
        }

        try {
            // Get all roles for this user
            $roles = $db->fetchAll("
                SELECT ugr.role, ugr.granted_at, ugr.granted_by, u.name as granted_by_name
                FROM user_global_roles ugr
                LEFT JOIN users u ON ugr.granted_by = u.id
                WHERE ugr.user_id = ?
                ORDER BY 
                    CASE ugr.role
                        WHEN 'super_admin' THEN 1
                        WHEN 'admin' THEN 2
                        WHEN 'principal' THEN 3
                        WHEN 'counselor' THEN 4
                        WHEN 'substitute_manager' THEN 5
                        WHEN 'maintenance_director' THEN 6
                        WHEN 'maintenance' THEN 7
                        WHEN 'custodial' THEN 8
                        WHEN 'cafeteria' THEN 9
                        WHEN 'staff' THEN 10
                    END
            ", [$userId]);

            jsonResponse(['roles' => $roles ?? []]);
        } catch (\Exception $e) {
            error_log("Error fetching user roles: " . $e->getMessage());
            jsonResponse(['roles' => []]); // Return empty array on error
        }
        break;

    case 'POST':
        // Add or update roles for a user
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $roles = $_POST['roles'] ?? [];

        if (!$userId) {
            jsonResponse(['error' => 'User ID required'], 400);
        }

        if (!is_array($roles)) {
            jsonResponse(['error' => 'Roles must be an array'], 400);
        }

        // Validate roles
        $validRoles = ['staff', 'maintenance', 'maintenance_director', 'counselor', 'custodial', 'substitute_manager', 'principal', 'cafeteria', 'admin', 'super_admin'];
        foreach ($roles as $role) {
            if (!in_array($role, $validRoles)) {
                jsonResponse(['error' => "Invalid role: $role"], 400);
            }
        }

        // Only super admins can grant super_admin role
        if (in_array('super_admin', $roles) && !Auth::isSuperAdmin()) {
            jsonResponse(['error' => 'Only super admins can grant super admin role'], 403);
        }

        try {
            $db->beginTransaction();

            // Get old roles for audit log
            $oldRoles = $db->fetchAll("
                SELECT role FROM user_global_roles WHERE user_id = ?
            ", [$userId]);
            $oldRoleNames = array_column($oldRoles, 'role');

            // Remove all existing roles for this user
            $db->execute("DELETE FROM user_global_roles WHERE user_id = ?", [$userId]);

            // Add new roles
            foreach ($roles as $role) {
                $db->execute("
                    INSERT INTO user_global_roles (user_id, role, granted_by)
                    VALUES (?, ?, ?)
                ", [$userId, $role, $currentUser['id']]);
            }

            // Update the primary role in users table (use highest role)
            $roleHierarchy = [
                'super_admin' => 10,
                'admin' => 9,
                'principal' => 8,
                'counselor' => 7,
                'substitute_manager' => 6,
                'maintenance_director' => 5,
                'maintenance' => 4,
                'custodial' => 3,
                'cafeteria' => 2,
                'staff' => 1
            ];

            $highestRole = 'staff';
            $highestValue = 0;
            foreach ($roles as $role) {
                if (isset($roleHierarchy[$role]) && $roleHierarchy[$role] > $highestValue) {
                    $highestValue = $roleHierarchy[$role];
                    $highestRole = $role;
                }
            }

            $db->execute("UPDATE users SET role = ? WHERE id = ?", [$highestRole, $userId]);

            // Audit log - role changes
            $logger = new AuditLogger();
            $logger->log(
                'update',
                'user_global_roles',
                $userId,
                ['roles' => $oldRoleNames],
                [
                    'roles' => $roles,
                    'primary_role' => $highestRole,
                    'changed_by' => $currentUser['name']
                ]
            );

            $db->commit();
            jsonResponse(['success' => true, 'message' => 'Roles updated successfully']);
        } catch (\Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Failed to update roles: ' . $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
