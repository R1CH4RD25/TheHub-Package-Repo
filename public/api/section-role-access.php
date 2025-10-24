<?php
/**
 * Section Role Access API
 * Manage role-based access to platform sections
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;

// Only super admins can manage section access
Auth::requireRole(['super_admin', 'admin']);

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get all sections with their role access
        try {
            $sections = $db->fetchAll("
                SELECT 
                    s.id,
                    s.name,
                    s.slug,
                    s.display_name,
                    s.icon,
                    s.description,
                    s.is_active,
                    s.sort_order
                FROM sections s
                ORDER BY s.sort_order, s.display_name
            ");

            // For each section, get which roles have access
            foreach ($sections as &$section) {
                $roleAccess = $db->fetchAll("
                    SELECT role 
                    FROM section_role_access 
                    WHERE section_id = ?
                    ORDER BY 
                        CASE role
                            WHEN 'super_admin' THEN 1
                            WHEN 'admin' THEN 2
                            WHEN 'manager' THEN 3
                            WHEN 'maintenance_director' THEN 4
                            WHEN 'maintenance' THEN 5
                            WHEN 'staff' THEN 6
                        END
                ", [$section['id']]);
                
                $section['roles'] = array_column($roleAccess, 'role');
            }

            jsonResponse(['sections' => $sections]);
        } catch (\Exception $e) {
            error_log("Error fetching section access: " . $e->getMessage());
            jsonResponse(['error' => 'Failed to load section access'], 500);
        }
        break;

    case 'POST':
        // Update role access for a section
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $sectionId = filter_var($_POST['section_id'] ?? null, FILTER_VALIDATE_INT);
        $roles = $_POST['roles'] ?? [];

        if (!$sectionId) {
            jsonResponse(['error' => 'Section ID required'], 400);
        }

        if (!is_array($roles)) {
            $roles = [];
        }

        // Validate roles
        $validRoles = ['staff', 'maintenance', 'maintenance_director', 'counselor', 'custodial', 'substitute_manager', 'principal', 'cafeteria', 'admin', 'super_admin'];
        foreach ($roles as $role) {
            if (!in_array($role, $validRoles)) {
                jsonResponse(['error' => "Invalid role: $role"], 400);
            }
        }

        try {
            $db->beginTransaction();

            // Get section details for audit log
            $section = $db->fetchOne("SELECT * FROM sections WHERE id = ?", [$sectionId]);
            
            // Get old roles for audit log
            $oldRoles = $db->fetchAll("SELECT role FROM section_role_access WHERE section_id = ?", [$sectionId]);
            $oldRoleNames = array_column($oldRoles, 'role');

            // Remove all existing role access for this section
            $db->execute("DELETE FROM section_role_access WHERE section_id = ?", [$sectionId]);

            // Add new role access
            foreach ($roles as $role) {
                $db->execute("
                    INSERT INTO section_role_access (section_id, role, granted_by)
                    VALUES (?, ?, ?)
                ", [$sectionId, $role, $currentUser['id']]);
            }

            // Audit log
            $logger = new AuditLogger();
            $logger->log(
                'update',
                'section_role_access',
                $sectionId,
                [
                    'section' => $section['display_name'],
                    'roles' => $oldRoleNames
                ],
                [
                    'section' => $section['display_name'],
                    'roles' => $roles,
                    'updated_by' => $currentUser['name']
                ]
            );

            $db->commit();
            jsonResponse(['success' => true, 'message' => 'Section access updated successfully']);
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Error updating section access: " . $e->getMessage());
            jsonResponse(['error' => 'Failed to update section access: ' . $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
