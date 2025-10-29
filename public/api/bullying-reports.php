<?php
/**
 * Bullying Reports API
 * 
 * POST /api/bullying-reports.php - Submit new report (anyone can submit)
 * GET /api/bullying-reports.php - View reports (counselor/principal/admin only)
 * GET /api/bullying-reports.php?id=123 - View specific report (counselor/principal/admin only)
 * PUT /api/bullying-reports.php - Update report (counselor/principal/admin only)
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;
use Hub\SectionPermissions;
use Hub\NotificationService;

header('Content-Type: application/json');

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // SUBMIT NEW REPORT - No authentication required (public form)
            handleSubmitReport($db);
            break;
            
        case 'GET':
            // VIEW REPORTS - Check review permissions
            Auth::requireLogin();
            
            $currentUser = Auth::getCurrentUser();
            if (!SectionPermissions::canReview($currentUser['id'], 'bullying-report')) {
                jsonResponse(['error' => 'You do not have permission to view reports'], 403);
            }
            
            if (isset($_GET['id'])) {
                handleGetReport($db, $_GET['id']);
            } else {
                handleGetReports($db);
            }
            break;
            
        case 'PUT':
        case 'PATCH':
            // UPDATE REPORT - Check edit permissions
            Auth::requireLogin();
            
            $currentUser = Auth::getCurrentUser();
            $perms = SectionPermissions::getReviewPermissions($currentUser['id'], 'bullying-report');
            
            if (!$perms['can_edit']) {
                jsonResponse(['error' => 'You do not have permission to edit reports'], 403);
            }
            
            handleUpdateReport($db, $perms);
            break;
            
        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (\Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

/**
 * Handle report submission (public access)
 */
function handleSubmitReport($db) {
    // CSRF protection
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }
    
    // Validate required fields
    $requiredFields = ['incident_date', 'incident_location', 'incident_type', 'incident_description'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(['error' => "Missing required field: $field"], 400);
        }
    }
    
    // Prepare data
    $data = [
        'reporter_user_id' => $_POST['reporter_user_id'] ?? null,
        'reporter_name' => $_POST['is_anonymous'] ? null : ($_POST['reporter_name'] ?? null),
        'reporter_email' => $_POST['is_anonymous'] ? null : ($_POST['reporter_email'] ?? null),
        'reporter_phone' => $_POST['is_anonymous'] ? null : ($_POST['reporter_phone'] ?? null),
        'is_anonymous' => !empty($_POST['is_anonymous']),
        'reporter_relationship' => $_POST['reporter_relationship'] ?? null,
        'incident_date' => $_POST['incident_date'],
        'incident_time' => $_POST['incident_time'] ?? null,
        'incident_location' => $_POST['incident_location'],
        'victim_name' => $_POST['victim_name'] ?? null,
        'victim_grade' => $_POST['victim_grade'] ?? null,
        'bully_name' => $_POST['bully_name'] ?? null,
        'bully_grade' => $_POST['bully_grade'] ?? null,
        'incident_description' => $_POST['incident_description'],
        'incident_type' => $_POST['incident_type'],
        'witnesses' => $_POST['witnesses'] ?? null,
        'previous_incidents' => !empty($_POST['previous_incidents']),
        'previous_incidents_details' => $_POST['previous_incidents_details'] ?? null,
        'status' => 'submitted',
        'priority' => determinePriority($_POST['incident_type'], $_POST['incident_description'])
    ];
    
    // Insert report
    $sql = "INSERT INTO bullying_reports (
        reporter_user_id, reporter_name, reporter_email, reporter_phone, is_anonymous,
        reporter_relationship, incident_date, incident_time, incident_location,
        victim_name, victim_grade, bully_name, bully_grade,
        incident_description, incident_type, witnesses,
        previous_incidents, previous_incidents_details,
        status, priority
    ) VALUES (
        :reporter_user_id, :reporter_name, :reporter_email, :reporter_phone, :is_anonymous,
        :reporter_relationship, :incident_date, :incident_time, :incident_location,
        :victim_name, :victim_grade, :bully_name, :bully_grade,
        :incident_description, :incident_type, :witnesses,
        :previous_incidents, :previous_incidents_details,
        :status, :priority
    )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($data);
    $reportId = $db->lastInsertId();
    
    // Audit log
    AuditLogger::log(
        'bullying_report_submitted',
        'bullying_reports',
        $reportId,
        null,
        $data['reporter_user_id'] ?? null,
        [
            'type' => $data['incident_type'],
            'priority' => $data['priority'],
            'anonymous' => $data['is_anonymous']
        ]
    );
    
    // Send notifications using new notification system
    $notificationData = [
        'section_name' => 'Bullying Report',
        'report_id' => $reportId,
        'priority' => $data['priority'],
        'details' => 'Type: ' . ucfirst($data['incident_type']) . "\n" .
                    'Location: ' . $data['incident_location'] . "\n" .
                    'Date: ' . $data['incident_date']
    ];
    
    NotificationService::notifySection('bullying-report', 'submission', $notificationData);
    
    jsonResponse([
        'success' => true,
        'report_id' => $reportId,
        'message' => 'Report submitted successfully. Thank you for helping us create a safer environment.'
    ]);
}

/**
 * Get all reports with filters (restricted access)
 */
function handleGetReports($db) {
    $where = [];
    $params = [];
    
    // Apply filters
    if (!empty($_GET['status'])) {
        $where[] = 'br.status = :status';
        $params['status'] = $_GET['status'];
    }
    
    if (!empty($_GET['priority'])) {
        $where[] = 'br.priority = :priority';
        $params['priority'] = $_GET['priority'];
    }
    
    if (!empty($_GET['type'])) {
        $where[] = 'br.incident_type = :type';
        $params['type'] = $_GET['type'];
    }
    
    if (!empty($_GET['date_from'])) {
        $where[] = 'br.incident_date >= :date_from';
        $params['date_from'] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $where[] = 'br.incident_date <= :date_to';
        $params['date_to'] = $_GET['date_to'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT 
        br.*,
        CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_to_name
    FROM bullying_reports br
    LEFT JOIN users assigned ON br.assigned_to = assigned.id
    $whereClause
    ORDER BY 
        CASE br.priority 
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        br.submitted_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    
    // Get stats
    $statsStmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high
    FROM bullying_reports");
    $stats = $statsStmt->fetch();
    
    jsonResponse([
        'success' => true,
        'reports' => $reports,
        'stats' => $stats
    ]);
}

/**
 * Get single report (restricted access)
 */
function handleGetReport($db, $reportId) {
    $sql = "SELECT 
        br.*,
        CONCAT(reporter_user.first_name, ' ', reporter_user.last_name) as reporter_user_name,
        CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_to_name,
        CONCAT(resolved.first_name, ' ', resolved.last_name) as resolved_by_name
    FROM bullying_reports br
    LEFT JOIN users reporter_user ON br.reporter_user_id = reporter_user.id
    LEFT JOIN users assigned ON br.assigned_to = assigned.id
    LEFT JOIN users resolved ON br.resolved_by = resolved.id
    WHERE br.id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $reportId]);
    $report = $stmt->fetch();
    
    if (!$report) {
        jsonResponse(['error' => 'Report not found'], 404);
    }
    
    // Get notes
    $notesStmt = $db->prepare("SELECT 
        brn.*,
        CONCAT(u.first_name, ' ', u.last_name) as author_name
    FROM bullying_report_notes brn
    JOIN users u ON brn.author_id = u.id
    WHERE brn.report_id = :report_id
    ORDER BY brn.created_at DESC");
    $notesStmt->execute(['report_id' => $reportId]);
    $report['notes'] = $notesStmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'report' => $report
    ]);
}

/**
 * Update report (restricted access with permission check)
 */
function handleUpdateReport($db, $perms) {
    parse_str(file_get_contents('php://input'), $_PUT);
    
    if (empty($_PUT['id'])) {
        jsonResponse(['error' => 'Report ID required'], 400);
    }
    
    $currentUser = Auth::getCurrentUser();
    $reportId = $_PUT['id'];
    
    // Build update query dynamically based on provided fields and permissions
    $updateFields = [];
    $params = ['id' => $reportId];
    
    // Allow fields based on permissions
    $allowedFields = [];
    if ($perms['can_change_status']) {
        $allowedFields = array_merge($allowedFields, ['status', 'priority']);
    }
    if ($perms['can_assign']) {
        $allowedFields[] = 'assigned_to';
    }
    if ($perms['can_edit']) {
        $allowedFields = array_merge($allowedFields, ['action_taken', 'resolution_notes', 'follow_up_required', 'follow_up_date']);
    }
    
    foreach ($allowedFields as $field) {
        if (isset($_PUT[$field])) {
            $updateFields[] = "$field = :$field";
            $params[$field] = $_PUT[$field];
        }
    }
    
    if (empty($updateFields)) {
        jsonResponse(['error' => 'No fields to update'], 400);
    }
    
    // If marking as resolved, set resolved_at and resolved_by
    if (isset($_PUT['status']) && $_PUT['status'] === 'resolved') {
        $updateFields[] = "resolved_at = NOW()";
        $updateFields[] = "resolved_by = :resolved_by";
        $params['resolved_by'] = $currentUser['id'];
    }
    
    $sql = "UPDATE bullying_reports SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Audit log
    AuditLogger::log(
        'bullying_report_updated',
        'bullying_reports',
        $reportId,
        null,
        $currentUser['id'],
        $params
    );
    
    jsonResponse([
        'success' => true,
        'message' => 'Report updated successfully'
    ]);
}

/**
 * Determine priority based on incident type and description
 */
function determinePriority($type, $description) {
    // Keywords that indicate urgent/high priority
    $urgentKeywords = ['weapon', 'gun', 'knife', 'suicide', 'hurt', 'injury', 'blood', 'threat to life'];
    $highKeywords = ['repeated', 'ongoing', 'every day', 'continues', 'won\'t stop', 'severe'];
    
    $descLower = strtolower($description);
    
    // Check for urgent keywords
    foreach ($urgentKeywords as $keyword) {
        if (strpos($descLower, $keyword) !== false) {
            return 'urgent';
        }
    }
    
    // Physical bullying is typically high priority
    if ($type === 'physical') {
        return 'high';
    }
    
    // Check for high priority keywords
    foreach ($highKeywords as $keyword) {
        if (strpos($descLower, $keyword) !== false) {
            return 'high';
        }
    }
    
    // Default to medium
    return 'medium';
}

/**
 * Send notifications to counselor, principal, admin
 */
function sendNotifications($db, $reportId, $reportData) {
    // Get notification roles from section_notification_roles
    $stmt = $db->query("
        SELECT DISTINCT snr.notify_role
        FROM section_notification_roles snr
        JOIN sections s ON snr.section_id = s.id
        WHERE s.slug = 'bullying-report' 
        AND snr.action_type = 'submission'
        AND snr.is_active = TRUE
    ");
    $notifyRoles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    
    if (empty($notifyRoles)) {
        return; // No roles configured to notify
    }
    
    // Get users with those roles
    $placeholders = implode(',', array_fill(0, count($notifyRoles), '?'));
    $userStmt = $db->prepare("
        SELECT DISTINCT email, CONCAT(first_name, ' ', last_name) as name
        FROM users 
        WHERE role IN ($placeholders)
        AND is_active = TRUE
    ");
    $userStmt->execute($notifyRoles);
    $recipients = $userStmt->fetchAll();
    
    if (empty($recipients)) {
        return;
    }
    
    // TODO: Implement actual email sending via NotificationService
    // For now, just log that notifications would be sent
    foreach ($recipients as $recipient) {
        error_log("Would send bullying report notification to: {$recipient['email']}");
    }
}
