<?php
/**
 * Command Center Submissions API
 *
 * Handles DataTables server-side processing and bulk actions
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Submission;
use Hub\Database;

header('Content-Type: application/json');

// Require authentication
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'];

try {
    $submission = new Submission();
    $db = Database::getInstance();

    // GET: DataTables server-side processing
    if ($method === 'GET') {
        $sectionId = $_GET['section_id'] ?? null;
        
        if (!$sectionId) {
            echo json_encode(['error' => 'Missing section_id']);
            exit;
        }

        // DataTables parameters
        $draw = intval($_GET['draw'] ?? 1);
        $start = intval($_GET['start'] ?? 0);
        $length = intval($_GET['length'] ?? 25);
        $searchValue = $_GET['search']['value'] ?? '';
        
        // Sorting
        $orderColumnIndex = intval($_GET['order'][0]['column'] ?? 6);
        $orderDir = $_GET['order'][0]['dir'] ?? 'desc';
        
        $orderColumns = [
            1 => 'display_id',
            2 => 'status_id',
            3 => 'priority',
            4 => 'submitted_by',
            5 => 'assigned_to',
            6 => 'created_at'
        ];
        
        $orderColumn = $orderColumns[$orderColumnIndex] ?? 'created_at';

        // Build filters
        $filters = [];
        if (!empty($_GET['status_id'])) {
            $filters['status_id'] = intval($_GET['status_id']);
        }
        if (!empty($_GET['priority'])) {
            $filters['priority'] = $_GET['priority'];
        }
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'] . ' 00:00:00';
        }
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'] . ' 23:59:59';
        }

        // Build WHERE clause
        $whereClauses = ['s.section_id = ?', 's.is_active = 1', 's.is_draft = 0'];
        $params = [$sectionId];

        if (!empty($filters['status_id'])) {
            $whereClauses[] = 's.status_id = ?';
            $params[] = $filters['status_id'];
        }

        if (!empty($filters['priority'])) {
            $whereClauses[] = 's.priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['date_from'])) {
            $whereClauses[] = 's.created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereClauses[] = 's.created_at <= ?';
            $params[] = $filters['date_to'];
        }

        // Search
        if (!empty($searchValue)) {
            $whereClauses[] = "(s.display_id LIKE ? OR u1.name LIKE ? OR u2.name LIKE ?)";
            $searchParam = '%' . $searchValue . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $whereClause = implode(' AND ', $whereClauses);

        // Get total count (without filters)
        $totalSql = "SELECT COUNT(*) as total 
                     FROM section_submissions s
                     WHERE s.section_id = ? AND s.is_active = 1 AND s.is_draft = 0";
        $totalResult = $db->fetchOne($totalSql, [$sectionId]);
        $recordsTotal = $totalResult['total'] ?? 0;

        // Get filtered count
        $filteredSql = "SELECT COUNT(*) as total 
                        FROM section_submissions s
                        LEFT JOIN users u1 ON s.submitted_by = u1.id
                        LEFT JOIN users u2 ON s.assigned_to = u2.id
                        WHERE {$whereClause}";
        $filteredResult = $db->fetchOne($filteredSql, $params);
        $recordsFiltered = $filteredResult['total'] ?? 0;

        // Get data
        $dataSql = "SELECT 
                        s.id,
                        s.display_id,
                        s.status_id,
                        s.priority,
                        s.created_at,
                        s.updated_at,
                        st.status_name,
                        st.status_color,
                        st.status_icon,
                        u1.name AS submitted_by_name,
                        u2.name AS assigned_to_name
                    FROM section_submissions s
                    INNER JOIN section_submission_statuses st ON s.status_id = st.id
                    LEFT JOIN users u1 ON s.submitted_by = u1.id
                    LEFT JOIN users u2 ON s.assigned_to = u2.id
                    WHERE {$whereClause}
                    ORDER BY s.{$orderColumn} {$orderDir}
                    LIMIT ? OFFSET ?";

        $params[] = $length;
        $params[] = $start;

        $data = $db->fetchAll($dataSql, $params);

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ]);
    }

    // POST: Bulk actions or create submission
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? null;

        if ($action === 'delete') {
            // Bulk delete
            $ids = $input['ids'] ?? [];
            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => 'No IDs provided']);
                exit;
            }

            $count = 0;
            foreach ($ids as $id) {
                if ($submission->delete($id, $userId)) {
                    $count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Deleted {$count} submission(s)"
            ]);
        }
        elseif ($action === 'bulk_assign') {
            // Bulk assign (coming in Phase 2)
            echo json_encode(['success' => false, 'message' => 'Bulk assign not yet implemented']);
        }
        elseif ($action === 'bulk_status') {
            // Bulk status change (coming in Phase 2)
            echo json_encode(['success' => false, 'message' => 'Bulk status change not yet implemented']);
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
    }

    // PUT: Update submission
    elseif ($method === 'PUT') {
        parse_str(file_get_contents('php://input'), $input);
        $id = $input['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing submission ID']);
            exit;
        }

        unset($input['id']);
        
        if ($submission->update($id, $input, $userId)) {
            echo json_encode(['success' => true, 'message' => 'Submission updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed']);
        }
    }

    else {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
}
