<?php
/**
 * Section Submissions API
 * 
 * Handles form submissions for package-based dynamic sections.
 */

require_once __DIR__ . '/../bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;

header('Content-Type: application/json');
Auth::requireLogin();

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'submit';
$user = Auth::getUser();

// POST - Submit Form
if ($method === 'POST' && $action === 'submit') {
    
    if (!verifyCsrfToken()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Invalid CSRF token']));
    }
    
    $sectionId = (int)($_POST['section_id'] ?? 0);
    
    if (!$sectionId) {
        die(json_encode(['success' => false, 'error' => 'Section ID required']));
    }
    
    // Get section
    $section = $db->fetchOne("SELECT * FROM sections WHERE id = ? AND is_active = 1", [$sectionId]);
    
    if (!$section) {
        http_response_code(404);
        die(json_encode(['success' => false, 'error' => 'Section not found']));
    }
    
    // Check access
    $sectionAccess = new \Hub\SectionRoleAccess();
    if (!$sectionAccess->hasAccess($user['id'], $section['slug'])) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Access denied']));
    }
    
    // Get fields
    $fields = $db->fetchAll(
        "SELECT * FROM section_field_definitions WHERE section_id = ? ORDER BY sort_order",
        [$sectionId]
    );
    
    // Validate
    $errors = [];
    $submissionData = [];
    
    foreach ($fields as $field) {
        $name = $field['field_name'];
        $required = (bool)$field['is_required'];
        $value = $_POST[$name] ?? null;
        
        // Handle multi-select
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        
        // Check required
        if ($required && empty($value)) {
            $errors[$name] = $field['field_label'] . ' is required';
        }
        
        $submissionData[$name] = $value;
    }
    
    if (!empty($errors)) {
        die(json_encode([
            'success' => false,
            'error' => 'Please correct the errors',
            'errors' => $errors
        ]));
    }
    
    // Save
    try {
        $db->beginTransaction();
        
        $submissionId = $db->insert('section_data', [
            'section_id' => $sectionId,
            'submitted_by' => $user['id'],
            'data' => json_encode($submissionData),
            'submitted_at' => date('Y-m-d H:i:s')
        ]);
        
        AuditLogger::log('section_submission', 'create', [
            'section_id' => $sectionId,
            'submission_id' => $submissionId
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Submission recorded successfully!',
            'submission_id' => $submissionId
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Submission error: " . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to save. Please try again.'
        ]);
    }
    
    exit;
}

// Invalid action
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
