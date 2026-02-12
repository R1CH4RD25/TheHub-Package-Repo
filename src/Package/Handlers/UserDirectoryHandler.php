<?php

namespace Hub\Package\Handlers;

use Hub\Database;

/**
 * User Directory Query Handler
 *
 * Handles queries for the user-directory sample package.
 * Demonstrates the Layer 3 query pipeline with real DB data.
 *
 * Sprint 1: Direct handler (not going through full enforcement pipeline).
 * Sprint 2: Will be refactored to individual QueryHandlerInterface implementations.
 */
class UserDirectoryHandler
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getPackageId(): string
    {
        return 'com.woodson.user-directory';
    }

    public function getSupportedQueries(): array
    {
        return ['list_users', 'get_user', 'user_stats'];
    }

    public function canHandle(string $queryName): bool
    {
        return in_array($queryName, $this->getSupportedQueries(), true);
    }

    /**
     * Execute a query and return results
     */
    public function execute(string $queryName, array $parameters, array $context): array
    {
        return match ($queryName) {
            'list_users' => $this->listUsers($parameters, $context),
            'get_user' => $this->getUser($parameters),
            'user_stats' => $this->getUserStats(),
            default => ['data' => [], 'error' => 'Unknown query: ' . $queryName],
        };
    }

    /**
     * List users with filtering, sorting, pagination
     */
    private function listUsers(array $params, array $context): array
    {
        $where = ['1=1'];
        $bindings = [];

        // Search filter
        if (!empty($params['search'])) {
            $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
            $search = '%' . $params['search'] . '%';
            $bindings[] = $search;
            $bindings[] = $search;
        }

        // Role filter
        if (!empty($params['role'])) {
            $where[] = 'u.role = ?';
            $bindings[] = $params['role'];
        }

        // Status filter (derived from approved_at)
        if (!empty($params['status'])) {
            if ($params['status'] === 'approved') {
                $where[] = 'u.approved_at IS NOT NULL';
            } elseif ($params['status'] === 'pending') {
                $where[] = 'u.approved_at IS NULL';
            }
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countRow = $this->db->fetchOne(
            "SELECT COUNT(*) as total FROM users u WHERE {$whereClause}",
            $bindings
        );
        $total = (int)($countRow['total'] ?? 0);

        // Sort
        $allowedSorts = ['name', 'email', 'role', 'created_at'];
        $sort = in_array($params['sort'] ?? '', $allowedSorts) ? $params['sort'] : 'name';
        $sortDir = ($params['sortDir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        // Pagination
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(5, (int)($params['pageSize'] ?? 10)));
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->fetchAll(
            "SELECT u.id, u.name, u.email, u.role, u.created_at, u.last_login,
                    u.google_id, u.is_active,
                    IF(u.approved_at IS NOT NULL, 'approved', 'pending') as status
             FROM users u
             WHERE {$whereClause}
             ORDER BY {$sort} {$sortDir}
             LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );

        return [
            'data' => $rows,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Get single user by ID
     */
    private function getUser(array $params): array
    {
        $userId = (int)($params['user_id'] ?? 0);
        if (!$userId) {
            return ['data' => null, 'error' => 'user_id required'];
        }

        $row = $this->db->fetchOne(
            "SELECT id, name, email, role, created_at, last_login, google_id,
                    is_active, IF(approved_at IS NOT NULL, 'approved', 'pending') as status
             FROM users WHERE id = ?",
            [$userId]
        );

        return ['data' => $row];
    }

    /**
     * Get aggregate statistics
     */
    private function getUserStats(): array
    {
        $stats = $this->db->fetchOne(
            "SELECT
                COUNT(*) as total_users,
                SUM(CASE WHEN role IN ('admin', 'super_admin') THEN 1 ELSE 0 END) as admin_count,
                SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count,
                SUM(CASE WHEN approved_at IS NULL THEN 1 ELSE 0 END) as pending_count
             FROM users"
        );

        return ['data' => $stats ?: ['total_users' => 0, 'admin_count' => 0, 'staff_count' => 0, 'pending_count' => 0]];
    }
}
