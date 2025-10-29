<?php
/**
 * Section Configuration API
 * Manage section categories, permissions, notifications, and guidelines
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;
use Hub\SectionPermissions;

header('Content-Type: application/json');

// Require admin or super_admin
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$currentUser = Auth::getCurrentUser();

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['section_id'])) {
                handleGetSectionConfig($db, $_GET['section_id']);
            } elseif (isset($_GET['validate'])) {
                handleValidateSection($db, $_GET['section_id']);
            } else {
                handleGetAllSections($db);
            }
            break;
            
        case 'POST':
            handleUpdateSectionConfig($db, $currentUser);
            break;
            
        case 'PUT':
            parse_str(file_get_contents('php://input'), $_PUT);
            handleUpdateSectionConfig($db, $currentUser, $_PUT);
            break;
            
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (\Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

/**
 * Get all sections with their configuration status
 */
function handleGetAllSections($db)
{
    $sql = "SELECT 
                s.id,
                s.slug,
                s.name,
                s.display_name,
                s.icon,
                s.category_id,
                sc.name as category_name,
                sc.display_name as category_display_name,
                sc.requires_forms,
                sc.requires_submissions,
                sc.requires_notifications,
                sc.requires_guidelines,
                (SELECT COUNT(*) FROM section_submission_permissions WHERE section_id = s.id) as submission_perms_count,
                (SELECT COUNT(*) FROM section_review_permissions WHERE section_id = s.id) as review_perms_count,
                (SELECT COUNT(*) FROM section_notification_rules WHERE section_id = s.id AND is_active = TRUE) as notification_rules_count,
                (SELECT COUNT(*) FROM section_guidelines WHERE section_id = s.id AND is_active = TRUE) as guidelines_count
            FROM sections s
            LEFT JOIN section_categories sc ON s.category_id = sc.id
            WHERE s.is_active = TRUE
            ORDER BY s.display_name";
    
    $stmt = $db->query($sql);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add validation status for each section
    foreach ($sections as &$section) {
        if ($section['category_id']) {
            $validation = SectionPermissions::validateSectionConfig($section['id']);
            $section['validation'] = $validation;
            $section['is_configured'] = empty($validation['errors']);
        } else {
            $section['is_configured'] = false;
            $section['validation'] = ['errors' => ['No category assigned'], 'warnings' => []];
        }
    }
    
    // Get all categories
    $categoriesStmt = $db->query("SELECT * FROM section_categories ORDER BY display_name");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'sections' => $sections,
        'categories' => $categories
    ]);
}

/**
 * Get detailed configuration for a specific section
 */
function handleGetSectionConfig($db, $sectionId)
{
    $pdo = $db->getConnection();
    
    // Get section basic info
    $sql = "SELECT s.*
            FROM sections s
            WHERE s.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $sectionId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        jsonResponse(['error' => 'Section not found'], 404);
    }
    
    // Get category
    $categoryStmt = $pdo->prepare("SELECT * FROM section_categories WHERE id = :id");
    $categoryStmt->execute(['id' => $section['category_id']]);
    $section['category'] = $categoryStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get submission permissions
    $submissionStmt = $pdo->prepare("SELECT * FROM section_submission_permissions WHERE section_id = :id ORDER BY role_name");
    $submissionStmt->execute(['id' => $sectionId]);
    $section['submission_permissions'] = $submissionStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get review permissions
    $reviewStmt = $pdo->prepare("SELECT * FROM section_review_permissions WHERE section_id = :id ORDER BY role_name");
    $reviewStmt->execute(['id' => $sectionId]);
    $section['review_permissions'] = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get notification rules
    $notificationStmt = $pdo->prepare("SELECT * FROM section_notification_rules WHERE section_id = :id ORDER BY role_name");
    $notificationStmt->execute(['id' => $sectionId]);
    $section['notification_rules'] = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get guidelines
    $guidelinesStmt = $pdo->prepare("SELECT * FROM section_guidelines WHERE section_id = :id ORDER BY display_order");
    $guidelinesStmt->execute(['id' => $sectionId]);
    $section['guidelines'] = $guidelinesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get configuration
    $configStmt = $pdo->prepare("SELECT * FROM section_configuration WHERE section_id = :id");
    $configStmt->execute(['id' => $sectionId]);
    $config = $configStmt->fetch(PDO::FETCH_ASSOC);
    if ($config) {
        if ($config['allowed_file_types']) {
            $config['allowed_file_types'] = json_decode($config['allowed_file_types'], true);
        }
        if ($config['form_config']) {
            $config['form_config'] = json_decode($config['form_config'], true);
        }
    }
    $section['configuration'] = $config;
    
    // Get all available roles for dropdowns
    $rolesStmt = $db->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL ORDER BY role");
    $section['available_roles'] = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    jsonResponse([
        'success' => true,
        'section' => $section
    ]);
}

/**
 * Validate section configuration
 */
function handleValidateSection($db, $sectionId)
{
    $validation = SectionPermissions::validateSectionConfig($sectionId);
    
    jsonResponse([
        'success' => true,
        'validation' => $validation
    ]);
}

/**
 * Update section configuration
 */
function handleUpdateSectionConfig($db, $currentUser, $data = null)
{
    $data = $data ?: $_POST;
    
    if (empty($data['section_id'])) {
        jsonResponse(['error' => 'Section ID required'], 400);
    }
    
    $sectionId = $data['section_id'];
    
    $db->beginTransaction();
    
    try {
        // Update category
        if (isset($data['category_id'])) {
            $stmt = $db->prepare("UPDATE sections SET category_id = :category_id WHERE id = :id");
            $stmt->execute(['category_id' => $data['category_id'] ?: null, 'id' => $sectionId]);
        }
        
        // Update submission permissions
        if (isset($data['submission_permissions'])) {
            // Clear existing
            $db->prepare("DELETE FROM section_submission_permissions WHERE section_id = :id")->execute(['id' => $sectionId]);
            
            // Insert new
            $stmt = $db->prepare("INSERT INTO section_submission_permissions (section_id, role_name, can_submit, allow_anonymous) VALUES (:section_id, :role_name, :can_submit, :allow_anonymous)");
            foreach ($data['submission_permissions'] as $perm) {
                $stmt->execute([
                    'section_id' => $sectionId,
                    'role_name' => $perm['role_name'],
                    'can_submit' => !empty($perm['can_submit']),
                    'allow_anonymous' => !empty($perm['allow_anonymous'])
                ]);
            }
        }
        
        // Update review permissions
        if (isset($data['review_permissions'])) {
            // Clear existing
            $db->prepare("DELETE FROM section_review_permissions WHERE section_id = :id")->execute(['id' => $sectionId]);
            
            // Insert new
            $stmt = $db->prepare("INSERT INTO section_review_permissions (section_id, role_name, can_view, can_edit, can_delete, can_add_notes, can_change_status, can_assign, can_export) VALUES (:section_id, :role_name, :can_view, :can_edit, :can_delete, :can_add_notes, :can_change_status, :can_assign, :can_export)");
            foreach ($data['review_permissions'] as $perm) {
                $stmt->execute([
                    'section_id' => $sectionId,
                    'role_name' => $perm['role_name'],
                    'can_view' => !empty($perm['can_view']),
                    'can_edit' => !empty($perm['can_edit']),
                    'can_delete' => !empty($perm['can_delete']),
                    'can_add_notes' => !empty($perm['can_add_notes']),
                    'can_change_status' => !empty($perm['can_change_status']),
                    'can_assign' => !empty($perm['can_assign']),
                    'can_export' => !empty($perm['can_export'])
                ]);
            }
        }
        
        // Update notification rules
        if (isset($data['notification_rules'])) {
            // Clear existing
            $db->prepare("DELETE FROM section_notification_rules WHERE section_id = :id")->execute(['id' => $sectionId]);
            
            // Insert new
            $stmt = $db->prepare("INSERT INTO section_notification_rules (section_id, role_name, notify_on_submission, notify_on_status_change, notify_on_assignment, notify_on_comment, email_enabled, sms_enabled, is_active) VALUES (:section_id, :role_name, :notify_on_submission, :notify_on_status_change, :notify_on_assignment, :notify_on_comment, :email_enabled, :sms_enabled, TRUE)");
            foreach ($data['notification_rules'] as $rule) {
                $stmt->execute([
                    'section_id' => $sectionId,
                    'role_name' => $rule['role_name'],
                    'notify_on_submission' => !empty($rule['notify_on_submission']),
                    'notify_on_status_change' => !empty($rule['notify_on_status_change']),
                    'notify_on_assignment' => !empty($rule['notify_on_assignment']),
                    'notify_on_comment' => !empty($rule['notify_on_comment']),
                    'email_enabled' => !empty($rule['email_enabled']),
                    'sms_enabled' => !empty($rule['sms_enabled'])
                ]);
            }
        }
        
        // Update guidelines
        if (isset($data['guidelines'])) {
            // Clear existing
            $db->prepare("DELETE FROM section_guidelines WHERE section_id = :id")->execute(['id' => $sectionId]);
            
            // Insert new
            $stmt = $db->prepare("INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active) VALUES (:section_id, :guideline_type, :title, :content, :display_order, TRUE)");
            foreach ($data['guidelines'] as $idx => $guideline) {
                $stmt->execute([
                    'section_id' => $sectionId,
                    'guideline_type' => $guideline['guideline_type'] ?? 'general',
                    'title' => $guideline['title'],
                    'content' => $guideline['content'],
                    'display_order' => $guideline['display_order'] ?? $idx
                ]);
            }
        }
        
        // Update configuration
        if (isset($data['configuration'])) {
            $config = $data['configuration'];
            
            // Check if config exists
            $existsStmt = $db->prepare("SELECT id FROM section_configuration WHERE section_id = :id");
            $existsStmt->execute(['id' => $sectionId]);
            
            if ($existsStmt->fetchColumn()) {
                // Update
                $stmt = $db->prepare("UPDATE section_configuration SET 
                    enable_status_tracking = :enable_status_tracking,
                    enable_priority_levels = :enable_priority_levels,
                    enable_attachments = :enable_attachments,
                    enable_follow_up_notes = :enable_follow_up_notes,
                    enable_assignment = :enable_assignment,
                    max_attachments = :max_attachments,
                    allowed_file_types = :allowed_file_types,
                    form_config = :form_config
                    WHERE section_id = :section_id");
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO section_configuration 
                    (section_id, enable_status_tracking, enable_priority_levels, enable_attachments, enable_follow_up_notes, enable_assignment, max_attachments, allowed_file_types, form_config)
                    VALUES 
                    (:section_id, :enable_status_tracking, :enable_priority_levels, :enable_attachments, :enable_follow_up_notes, :enable_assignment, :max_attachments, :allowed_file_types, :form_config)");
            }
            
            $stmt->execute([
                'section_id' => $sectionId,
                'enable_status_tracking' => !empty($config['enable_status_tracking']),
                'enable_priority_levels' => !empty($config['enable_priority_levels']),
                'enable_attachments' => !empty($config['enable_attachments']),
                'enable_follow_up_notes' => !empty($config['enable_follow_up_notes']),
                'enable_assignment' => !empty($config['enable_assignment']),
                'max_attachments' => $config['max_attachments'] ?? 5,
                'allowed_file_types' => !empty($config['allowed_file_types']) ? json_encode($config['allowed_file_types']) : null,
                'form_config' => !empty($config['form_config']) ? json_encode($config['form_config']) : null
            ]);
        }
        
        $db->commit();
        
        // Audit log
        AuditLogger::log(
            'section_config_updated',
            'sections',
            $sectionId,
            null,
            $currentUser['id'],
            ['updated_fields' => array_keys($data)]
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Section configuration updated successfully'
        ]);
        
    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
