<?php
/**
 * Sections Management API
 * Super Admin only - Create, edit, activate/deactivate sections
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;

// Only super admins can manage sections
Auth::requireRole(['super_admin']);

$db = Database::getInstance();
$currentUser = Auth::getCurrentUser();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get all sections
        $sections = $db->fetchAll("SELECT * FROM sections ORDER BY sort_order, display_name");
        jsonResponse($sections);
        break;

    case 'POST':
        // Create or update section
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $icon = trim($_POST['icon'] ?? '📁');
        $description = trim($_POST['description'] ?? '');
        $baseUrl = trim($_POST['base_url'] ?? '');
        $sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        if (!$name || !$displayName || !$baseUrl) {
            jsonResponse(['error' => 'Name, Display Name, and Base URL are required'], 400);
        }

        // Ensure slug format (lowercase, hyphens only)
        $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $name));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));

        try {
            if ($id) {
                // Get old data for audit log
                $oldSection = $db->fetchOne("SELECT * FROM sections WHERE id = ?", [$id]);
                
                // Update existing section
                $db->execute("
                    UPDATE sections 
                    SET name = ?, slug = ?, display_name = ?, icon = ?, 
                        description = ?, base_url = ?, sort_order = ?, is_active = ?
                    WHERE id = ?
                ", [$name, $slug, $displayName, $icon, $description, $baseUrl, $sortOrder, $isActive, $id]);
                
                // Audit log
                AuditLogger::logUpdate(
                    'sections',
                    $id,
                    [
                        'display_name' => $oldSection['display_name'],
                        'base_url' => $oldSection['base_url'],
                        'is_active' => $oldSection['is_active']
                    ],
                    [
                        'display_name' => $displayName,
                        'base_url' => $baseUrl,
                        'is_active' => $isActive,
                        'updated_by' => $currentUser['name']
                    ]
                );
                
                jsonResponse(['success' => true, 'message' => 'Section updated successfully']);
            } else {
                // Create new section
                $db->execute("
                    INSERT INTO sections (name, slug, display_name, icon, description, base_url, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ", [$name, $slug, $displayName, $icon, $description, $baseUrl, $sortOrder, $isActive]);
                
                $newId = $db->lastInsertId();
                
                // Audit log
                AuditLogger::logCreate(
                    'sections',
                    $newId,
                    [
                        'name' => $name,
                        'display_name' => $displayName,
                        'base_url' => $baseUrl,
                        'icon' => $icon,
                        'created_by' => $currentUser['name']
                    ]
                );
                
                jsonResponse(['success' => true, 'message' => 'Section created successfully']);
            }
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                jsonResponse(['error' => 'A section with this name already exists'], 400);
            }
            jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
        break;

    case 'PATCH':
        // Toggle section active status
        parse_str(file_get_contents('php://input'), $_PATCH);
        
        if (!verifyCsrfToken($_PATCH['csrf_token'] ?? '')) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $id = filter_var($_PATCH['id'] ?? null, FILTER_VALIDATE_INT);
        $isActive = filter_var($_PATCH['is_active'] ?? 0, FILTER_VALIDATE_INT);

        if (!$id) {
            jsonResponse(['error' => 'Section ID is required'], 400);
        }

        try {
            $oldSection = $db->fetchOne("SELECT * FROM sections WHERE id = ?", [$id]);
            
            $db->execute("UPDATE sections SET is_active = ? WHERE id = ?", [$isActive, $id]);
            
            // Audit log
            $logger = new AuditLogger();
            $logger->log(
                $isActive ? 'activate' : 'deactivate',
                'sections',
                $id,
                ['is_active' => $oldSection['is_active'], 'display_name' => $oldSection['display_name']],
                ['is_active' => $isActive, 'changed_by' => $currentUser['name']]
            );
            
            jsonResponse(['success' => true, 'message' => 'Section status updated']);
        } catch (\Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        break;

    case 'DELETE':
        // Delete section (careful - will cascade delete access records)
        parse_str(file_get_contents('php://input'), $_DELETE);
        
        if (!verifyCsrfToken($_DELETE['csrf_token'] ?? '')) {
            jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $id = filter_var($_DELETE['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id) {
            jsonResponse(['error' => 'Section ID is required'], 400);
        }

        try {
            $deletedSection = $db->fetchOne("SELECT * FROM sections WHERE id = ?", [$id]);
            
            $db->execute("DELETE FROM sections WHERE id = ?", [$id]);
            
            // Audit log
            AuditLogger::logDelete(
                'sections',
                $id,
                [
                    'name' => $deletedSection['name'],
                    'display_name' => $deletedSection['display_name'],
                    'base_url' => $deletedSection['base_url'],
                    'deleted_by' => $currentUser['name']
                ]
            );
            
            jsonResponse(['success' => true, 'message' => 'Section deleted successfully']);
        } catch (\Exception $e) {
            jsonResponse(['error' => 'Cannot delete section: ' . $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
