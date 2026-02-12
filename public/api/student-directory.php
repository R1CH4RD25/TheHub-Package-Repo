<?php

/**
 * Student Directory API Endpoint
 *
 * Dedicated endpoint for all student directory operations.
 * Routes queries, mutations, exports, and imports.
 *
 * Endpoints:
 *   GET  /api/student-directory.php?action=list|view|stats|grades|gradYears|importHistory|search
 *   POST /api/student-directory.php?action=create|update|resetPassword|bulkDelete|printCards
 *   POST /api/student-directory.php?action=importPreview|importProcess
 *   GET  /api/student-directory.php?action=exportStandard|exportGoogle
 *
 * Security:
 * - Authentication required for all actions
 * - Editor role (admin/super_admin + Technology Google group) for mutations
 * - Importer role for import operations
 * - CSRF token required for POST requests
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Package\StudentDirectory\StudentQueryHandler;
use Hub\Package\StudentDirectory\StudentMutationHandler;
use Hub\Package\StudentDirectory\ImportHandler;
use Hub\Package\StudentDirectory\ExportHandler;
use Hub\Package\StudentDirectory\StudentDatabase;

header('Content-Type: application/json');

// Require authentication
$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required', 'status' => 401]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action parameter', 'status' => 400]);
    exit;
}

/**
 * Check if user has editor access (Technology Google group, admin@woodsonisd.net, admin/super_admin role)
 */
function hasEditorAccess(array $user): bool
{
    $role = $user['role'] ?? '';
    if (in_array($role, ['admin', 'super_admin'])) {
        return true;
    }

    // Check Google group membership if available
    $email = $user['email'] ?? '';
    if ($email === 'admin@woodsonisd.net') {
        return true;
    }

    // Check session for Google group membership
    $groups = $_SESSION['user_google_groups'] ?? [];
    foreach ($groups as $group) {
        $groupEmail = is_array($group) ? ($group['email'] ?? '') : $group;
        if (str_contains(strtolower($groupEmail), 'technology')) {
            return true;
        }
        if (str_contains(strtolower($groupEmail), 'administration')) {
            return true;
        }
    }

    return false;
}

/**
 * Check if user has import/export access (Technology group only)
 */
function hasImporterAccess(array $user): bool
{
    $role = $user['role'] ?? '';
    if (in_array($role, ['admin', 'super_admin'])) {
        return true;
    }

    $groups = $_SESSION['user_google_groups'] ?? [];
    foreach ($groups as $group) {
        $groupEmail = is_array($group) ? ($group['email'] ?? '') : $group;
        if (str_contains(strtolower($groupEmail), 'technology')) {
            return true;
        }
    }

    return false;
}

try {
    // ─── GET Actions (Queries) ────────────────────────────────────
    if ($method === 'GET') {
        switch ($action) {
            case 'list':
                $result = StudentQueryHandler::listStudents($_GET);
                echo json_encode(['success' => true, ...$result]);
                break;

            case 'view':
                $studentId = $_GET['student_id'] ?? '';
                if (!$studentId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing student_id']);
                    break;
                }
                $student = StudentQueryHandler::getStudent($studentId, hasEditorAccess($user));
                if (!$student) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Student not found']);
                    break;
                }
                echo json_encode(['success' => true, 'data' => $student]);
                break;

            case 'stats':
                $stats = StudentQueryHandler::getStats();
                echo json_encode(['success' => true, 'data' => $stats]);
                break;

            case 'grades':
                $grades = StudentQueryHandler::getGrades();
                echo json_encode(['success' => true, 'data' => $grades]);
                break;

            case 'gradYears':
                $years = StudentQueryHandler::getGraduationYears();
                echo json_encode(['success' => true, 'data' => $years]);
                break;

            case 'importHistory':
                if (!hasImporterAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Import access required']);
                    break;
                }
                $history = StudentQueryHandler::getImportHistory();
                echo json_encode(['success' => true, 'data' => $history]);
                break;

            case 'search':
                $query = trim($_GET['q'] ?? '');
                if (!$query) {
                    echo json_encode(['success' => true, 'data' => []]);
                    break;
                }
                $results = StudentQueryHandler::searchStudents($query);
                echo json_encode(['success' => true, 'data' => $results]);
                break;

            case 'exportStandard':
                if (!hasImporterAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Export access required']);
                    break;
                }
                $result = ExportHandler::exportStandardCsv($_GET);
                if (!$result['success']) {
                    http_response_code(404);
                    echo json_encode($result);
                    break;
                }
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
                echo $result['csv'];
                break;

            case 'exportGoogle':
                if (!hasImporterAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Export access required']);
                    break;
                }
                $result = ExportHandler::exportGoogleCsv($_GET);
                if (!$result['success']) {
                    http_response_code(404);
                    echo json_encode($result);
                    break;
                }
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
                echo $result['csv'];
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => "Unknown GET action: {$action}"]);
        }
        exit;
    }

    // ─── POST Actions (Mutations) ─────────────────────────────────
    if ($method === 'POST') {
        // CSRF validation for all POST requests
        $csrfToken = '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true) ?? [];
            $csrfToken = $body['csrfToken'] ?? $body['csrf_token'] ?? '';
        } elseif (str_contains($contentType, 'multipart/form-data')) {
            // For file uploads, CSRF comes from form field
            $body = $_POST;
            $csrfToken = $_POST['csrfToken'] ?? $_POST['csrf_token'] ?? '';
        } else {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true) ?? [];
            $csrfToken = $body['csrfToken'] ?? $body['csrf_token'] ?? '';
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF token validation failed', 'status' => 403]);
            exit;
        }

        switch ($action) {
            case 'create':
                if (!hasEditorAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Editor access required']);
                    break;
                }
                $result = StudentMutationHandler::createStudent($body);
                echo json_encode($result);
                break;

            case 'update':
                if (!hasEditorAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Editor access required']);
                    break;
                }
                $studentId = $body['student_id'] ?? '';
                if (!$studentId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing student_id']);
                    break;
                }
                $result = StudentMutationHandler::updateStudent($studentId, $body);
                echo json_encode($result);
                break;

            case 'resetPassword':
                if (!hasEditorAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Editor access required']);
                    break;
                }
                $studentId = $body['student_id'] ?? '';
                if (!$studentId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing student_id']);
                    break;
                }
                $result = StudentMutationHandler::resetPassword($studentId);
                echo json_encode($result);
                break;

            case 'bulkDelete':
                if (!hasEditorAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Editor access required']);
                    break;
                }
                $ids = $body['student_ids'] ?? [];
                if (empty($ids)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing student_ids array']);
                    break;
                }
                $result = StudentMutationHandler::bulkDelete($ids);
                echo json_encode($result);
                break;

            case 'printCards':
                if (!hasEditorAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Editor access required']);
                    break;
                }
                $ids = $body['student_ids'] ?? [];
                $grade = $body['grade'] ?? null;
                $result = StudentMutationHandler::printCards($ids, $grade);
                if ($result['success'] && isset($result['html'])) {
                    header('Content-Type: text/html');
                    echo $result['html'];
                } else {
                    echo json_encode($result);
                }
                break;

            case 'importPreview':
                if (!hasImporterAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Import access required']);
                    break;
                }

                if (!isset($_FILES['file'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No file uploaded']);
                    break;
                }

                $file = $_FILES['file'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['error' => 'File upload error: ' . $file['error']]);
                    break;
                }

                // Validate file type
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext !== 'csv') {
                    http_response_code(400);
                    echo json_encode(['error' => 'Only CSV files are supported']);
                    break;
                }

                // Move to temp location with session tracking
                $tempDir = sys_get_temp_dir() . '/student-imports';
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                $tempFile = $tempDir . '/' . session_id() . '_' . time() . '.csv';
                move_uploaded_file($file['tmp_name'], $tempFile);

                // Store temp file path in session for process step
                $_SESSION['student_import_file'] = $tempFile;

                $result = ImportHandler::parseForPreview($tempFile);
                echo json_encode($result);
                break;

            case 'importProcess':
                if (!hasImporterAccess($user)) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Import access required']);
                    break;
                }

                $tempFile = $_SESSION['student_import_file'] ?? '';
                if (!$tempFile || !file_exists($tempFile)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No import file in session. Upload first.']);
                    break;
                }

                $fieldMapping = $body['field_mapping'] ?? [];
                $mode = $body['mode'] ?? 'append';
                $userId = $user['id'] ?? null;

                $result = ImportHandler::processImport($tempFile, $fieldMapping, $mode, $userId);

                // Clean up temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                unset($_SESSION['student_import_file']);

                echo json_encode($result);
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => "Unknown POST action: {$action}"]);
        }
        exit;
    }

    // Other methods not allowed
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (\Exception $e) {
    error_log("[StudentDirectory] Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred',
        'status' => 500,
    ]);
}
