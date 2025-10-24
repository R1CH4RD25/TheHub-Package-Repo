<?php
/**
 * Audit Logs API
 * Super Admin only - View all system activity
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;

// Only super admins can view audit logs
Auth::requireRole(['super_admin']);

$db = Database::getInstance();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $limit = filter_var($_GET['limit'] ?? 100, FILTER_VALIDATE_INT);
        $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
        $action = $_GET['action'] ?? null;
        $table = $_GET['table'] ?? null;
        $userId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);

        $where = [];
        $params = [];

        if ($action) {
            $where[] = "al.action = ?";
            $params[] = $action;
        }

        if ($table) {
            $where[] = "al.table_name = ?";
            $params[] = $table;
        }

        if ($userId) {
            $where[] = "al.user_id = ?";
            $params[] = $userId;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM audit_log al $whereClause";
            $totalResult = $db->fetchOne($countQuery, $params);
            $total = $totalResult['total'] ?? 0;

            // Get logs
            $query = "
                SELECT 
                    al.*,
                    u.name as user_name,
                    u.email as user_email
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                $whereClause
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;

            $logs = $db->fetchAll($query, $params);

            jsonResponse([
                'logs' => $logs,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            error_log("Error fetching audit logs: " . $e->getMessage());
            jsonResponse(['error' => 'Failed to fetch audit logs'], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
