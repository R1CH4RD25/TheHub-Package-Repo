<?php

/**
 * ManagementCenter - High-level orchestration for submission management
 *
 * Provides dashboard stats, cross-section queries, and administrative
 * functions for the Management interface.
 *
 * @package Hub
 */

namespace Hub;

class ManagementCenter
{
    private $db;
    private $submission;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->submission = new Submission();
    }

    /**
     * Get dashboard overview statistics
     *
     * @param int|null $sectionId Optional: Filter by section
     * @return array Dashboard stats
     */
    public function getDashboardStats($sectionId = null)
    {
        $sectionClause = $sectionId ? 'AND s.section_id = ?' : '';
        $params = $sectionId ? [$sectionId] : [];

        // Total submissions (excluding drafts)
        $sql = "SELECT COUNT(*) as total
                FROM section_submissions s
                WHERE s.is_active = 1
                  AND s.is_draft = 0
                  {$sectionClause}";

        $total = $this->db->fetchOne($sql, $params)['total'] ?? 0;

        // Submissions by status
        $sql = "SELECT
                    st.status_name,
                    st.status_color,
                    st.status_icon,
                    COUNT(s.id) as count
                FROM section_submission_statuses st
                LEFT JOIN section_submissions s ON st.id = s.status_id
                    AND s.is_active = 1
                    AND s.is_draft = 0
                    {$sectionClause}
                WHERE st.is_active = 1
                  AND st.section_id IS NULL
                GROUP BY st.id, st.status_name, st.status_color, st.status_icon
                ORDER BY st.sort_order";

        $byStatus = $this->db->fetchAll($sql, $params);

        // Priority breakdown
        $sql = "SELECT
                    priority,
                    COUNT(*) as count
                FROM section_submissions
                WHERE is_active = 1
                  AND is_draft = 0
                  {$sectionClause}
                GROUP BY priority";

        $byPriority = $this->db->fetchAll($sql, $params);

        // Recent activity (last 7 days)
        $sql = "SELECT COUNT(*) as count
                FROM section_submissions
                WHERE is_active = 1
                  AND is_draft = 0
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  {$sectionClause}";

        $recentCount = $this->db->fetchOne($sql, $params)['count'] ?? 0;

        // Unassigned submissions
        $sql = "SELECT COUNT(*) as count
                FROM section_submissions
                WHERE is_active = 1
                  AND is_draft = 0
                  AND assigned_to IS NULL
                  {$sectionClause}";

        $unassignedCount = $this->db->fetchOne($sql, $params)['count'] ?? 0;

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'recent_7_days' => $recentCount,
            'unassigned' => $unassignedCount
        ];
    }

    /**
     * Get sections with submission counts
     *
     * @param int|null $userId Optional: Filter by sections user has access to
     * @return array Array of sections with counts
     */
    public function getSectionsWithCounts($userId = null)
    {
        // Build access filter
        $accessJoin = '';
        $accessWhere = '';
        $params = [];

        if ($userId) {
            $accessJoin = "INNER JOIN section_access sa ON s.id = sa.section_id";
            $accessWhere = "AND sa.user_id = ?";
            $params[] = $userId;
        }

        $sql = "SELECT
                    s.id,
                    s.name,
                    s.slug,
                    s.mgmt_prefix,
                    s.icon,
                    COUNT(DISTINCT CASE WHEN sub.is_draft = 0 THEN sub.id END) as submission_count,
                    COUNT(DISTINCT CASE WHEN sub.is_draft = 0 AND sub.status_id = 1 THEN sub.id END) as pending_count
                FROM sections s
                {$accessJoin}
                LEFT JOIN section_submissions sub ON s.id = sub.section_id
                    AND sub.is_active = 1
                WHERE s.is_active = 1
                  {$accessWhere}
                GROUP BY s.id, s.name, s.slug, s.mgmt_prefix, s.icon
                HAVING submission_count > 0
                ORDER BY pending_count DESC, s.name ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get recent submissions across all sections
     *
     * @param int $limit Number of submissions to retrieve
     * @param int|null $userId Optional: Filter by sections user has access to
     * @return array Array of recent submissions
     */
    public function getRecentSubmissions($limit = 10, $userId = null)
    {
        // Build access filter
        $accessJoin = '';
        $params = [];

        if ($userId) {
            $accessJoin = "INNER JOIN section_access sa ON s.section_id = sa.section_id
                           AND sa.user_id = ?";
            $params[] = $userId;
        }
        $params[] = $limit;

        $sql = "SELECT
                    s.id,
                    s.display_id,
                    s.created_at,
                    s.priority,
                    sec.name AS section_name,
                    sec.slug AS section_slug,
                    st.status_name,
                    st.status_color,
                    u.name AS submitted_by_name
                FROM section_submissions s
                INNER JOIN sections sec ON s.section_id = sec.id
                INNER JOIN section_submission_statuses st ON s.status_id = st.id
                LEFT JOIN users u ON s.submitted_by = u.id
                {$accessJoin}
                WHERE s.is_active = 1
                  AND s.is_draft = 0
                ORDER BY s.created_at DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Search submissions across all sections
     *
     * @param string $query Search query
     * @param array $filters Optional filters (section_id, status_id, date_from, date_to)
     * @param int $limit Limit results
     * @return array Array of matching submissions
     */
    public function searchSubmissions($query, $filters = [], $limit = 50)
    {
        $whereClauses = ['s.is_active = 1', 's.is_draft = 0'];
        $params = [];

        // Section filter
        if (!empty($filters['section_id'])) {
            $whereClauses[] = 's.section_id = ?';
            $params[] = $filters['section_id'];
        }

        // Status filter
        if (!empty($filters['status_id'])) {
            $whereClauses[] = 's.status_id = ?';
            $params[] = $filters['status_id'];
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $whereClauses[] = 's.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $whereClauses[] = 's.created_at <= ?';
            $params[] = $filters['date_to'];
        }

        // Search in display_id or JSON data
        if (!empty($query)) {
            $whereClauses[] = "(s.display_id LIKE ? OR s.submission_data LIKE ?)";
            $searchQuery = '%' . $query . '%';
            $params[] = $searchQuery;
            $params[] = $searchQuery;
        }

        $whereClause = implode(' AND ', $whereClauses);
        $params[] = $limit;

        $sql = "SELECT
                    s.id,
                    s.display_id,
                    s.created_at,
                    s.priority,
                    sec.name AS section_name,
                    sec.slug AS section_slug,
                    st.status_name,
                    st.status_color,
                    u.name AS submitted_by_name
                FROM section_submissions s
                INNER JOIN sections sec ON s.section_id = sec.id
                INNER JOIN section_submission_statuses st ON s.status_id = st.id
                LEFT JOIN users u ON s.submitted_by = u.id
                WHERE {$whereClause}
                ORDER BY s.created_at DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get submissions assigned to user
     *
     * @param int $userId User ID
     * @param array $filters Optional filters (status_id, priority)
     * @param int $limit Limit results
     * @return array Array of assigned submissions
     */
    public function getAssignedToUser($userId, $filters = [], $limit = 50)
    {
        $whereClauses = [
            's.is_active = 1',
            's.is_draft = 0',
            's.assigned_to = ?'
        ];
        $params = [$userId];

        // Status filter
        if (!empty($filters['status_id'])) {
            $whereClauses[] = 's.status_id = ?';
            $params[] = $filters['status_id'];
        }

        // Priority filter
        if (!empty($filters['priority'])) {
            $whereClauses[] = 's.priority = ?';
            $params[] = $filters['priority'];
        }

        $whereClause = implode(' AND ', $whereClauses);
        $params[] = $limit;

        $sql = "SELECT
                    s.id,
                    s.display_id,
                    s.created_at,
                    s.priority,
                    s.due_date,
                    sec.name AS section_name,
                    sec.slug AS section_slug,
                    st.status_name,
                    st.status_color,
                    u.name AS submitted_by_name
                FROM section_submissions s
                INNER JOIN sections sec ON s.section_id = sec.id
                INNER JOIN section_submission_statuses st ON s.status_id = st.id
                LEFT JOIN users u ON s.submitted_by = u.id
                WHERE {$whereClause}
                ORDER BY
                    CASE s.priority
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'normal' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    s.created_at ASC
                LIMIT ?";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get activity feed across all submissions
     *
     * @param int $limit Number of activities to retrieve
     * @param int|null $sectionId Optional: Filter by section
     * @return array Array of recent activities
     */
    public function getActivityFeed($limit = 20, $sectionId = null)
    {
        $sectionClause = $sectionId ? 'AND s.section_id = ?' : '';
        $params = $sectionId ? [$sectionId] : [];
        $params[] = $limit;

        $sql = "SELECT
                    h.id,
                    h.action,
                    h.severity,
                    h.old_value,
                    h.new_value,
                    h.notes,
                    h.created_at,
                    s.id AS submission_id,
                    s.display_id,
                    sec.name AS section_name,
                    sec.slug AS section_slug,
                    u.name AS user_name
                FROM section_submission_history h
                INNER JOIN section_submissions s ON h.submission_id = s.id
                INNER JOIN sections sec ON s.section_id = sec.id
                INNER JOIN users u ON h.user_id = u.id
                WHERE s.is_active = 1
                  AND s.is_draft = 0
                  {$sectionClause}
                ORDER BY h.created_at DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get all available statuses (global + section-specific)
     *
     * @param int|null $sectionId Optional: Include section-specific statuses
     * @return array Array of statuses
     */
    public function getStatuses($sectionId = null)
    {
        $sectionClause = $sectionId
            ? '(section_id IS NULL OR section_id = ?)'
            : 'section_id IS NULL';

        $params = $sectionId ? [$sectionId] : [];

        $sql = "SELECT
                    id,
                    status_name,
                    status_color,
                    status_icon,
                    sort_order,
                    section_id
                FROM section_submission_statuses
                WHERE is_active = 1
                  AND {$sectionClause}
                ORDER BY sort_order ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Bulk assign submissions to user
     *
     * @param array $submissionIds Array of submission IDs
     * @param int $userId User ID to assign to
     * @param int|null $actorId User performing the assignment
     * @return bool Success status
     */
    public function bulkAssign($submissionIds, $userId, $actorId = null)
    {
        if (empty($submissionIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
        $params = $submissionIds;
        $params[] = $userId;

        $sql = "UPDATE section_submissions
                SET assigned_to = ?
                WHERE id IN ({$placeholders})
                  AND is_active = 1";

        $this->db->execute($sql, $params);

        // Log each assignment
        foreach ($submissionIds as $submissionId) {
            $this->submission->update($submissionId, ['assigned_to' => $userId], $actorId);
        }

        return true;
    }

    /**
     * Bulk change status
     *
     * @param array $submissionIds Array of submission IDs
     * @param int $statusId Status ID to set
     * @param int|null $actorId User performing the change
     * @return bool Success status
     */
    public function bulkChangeStatus($submissionIds, $statusId, $actorId = null)
    {
        if (empty($submissionIds)) {
            return false;
        }

        foreach ($submissionIds as $submissionId) {
            $this->submission->changeStatus($submissionId, $statusId, $actorId);
        }

        return true;
    }

    /**
     * Get installed packages accessible to a user (role-based)
     *
     * Uses section_role_access to determine which installed packages
     * the user's role grants access to. Returns package data enriched
     * with page definitions from capabilities_json.
     *
     * @param int $userId User ID
     * @param string $userRole User's effective role
     * @return array Array of packages with page info
     */
    public function getInstalledPackagesForUser(int $userId, string $userRole): array
    {
        // Super admins see all installed packages
        if ($userRole === 'super_admin') {
            $sql = "SELECT
                        s.id as section_id,
                        s.name,
                        s.slug,
                        s.display_name,
                        s.icon,
                        s.base_url,
                        s.is_dynamic,
                        s.capabilities_json,
                        si.package_id,
                        si.installed_version,
                        si.status as install_status
                    FROM section_installations si
                    JOIN sections s ON si.section_id = s.id
                    WHERE si.status = 'installed'
                      AND s.is_active = 1
                    ORDER BY s.sort_order, s.display_name, s.name";

            $rows = $this->db->fetchAll($sql);
        } else {
            // Get user's roles (primary + global roles)
            $userRoles = $this->db->fetchAll(
                "SELECT role FROM user_global_roles WHERE user_id = ?",
                [$userId]
            );
            $allRoles = array_column($userRoles, 'role');
            if (!in_array($userRole, $allRoles)) {
                $allRoles[] = $userRole;
            }

            if (empty($allRoles)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($allRoles), '?'));

            $sql = "SELECT DISTINCT
                        s.id as section_id,
                        s.name,
                        s.slug,
                        s.display_name,
                        s.icon,
                        s.base_url,
                        s.is_dynamic,
                        s.capabilities_json,
                        si.package_id,
                        si.installed_version,
                        si.status as install_status
                    FROM section_installations si
                    JOIN sections s ON si.section_id = s.id
                    JOIN section_role_access sra ON s.id = sra.section_id
                    WHERE si.status = 'installed'
                      AND s.is_active = 1
                      AND sra.role IN ({$placeholders})
                    ORDER BY s.sort_order, s.display_name, s.name";

            $rows = $this->db->fetchAll($sql, $allRoles);
        }

        // Enrich with pages from capabilities_json
        $packages = [];
        foreach ($rows as $row) {
            $pages = [];
            if (!empty($row['capabilities_json'])) {
                $caps = json_decode($row['capabilities_json'], true);
                if ($caps) {
                    $rawPages = $caps['presentation']['pages'] ?? [];
                    foreach ($rawPages as $key => $page) {
                        $pages[] = [
                            'id' => $page['id'] ?? $key,
                            'title' => $page['title'] ?? ucfirst($key),
                            'route' => $page['route'] ?? '/',
                            'icon' => $page['icon'] ?? null,
                            'access' => $page['access'] ?? [],
                        ];
                    }
                }
            }

            $displayName = $row['display_name'] ?: $row['name'];
            // Clean up package-style names (e.g., "district.student-directory" → "Student Directory")
            if (strpos($displayName, '.') !== false || strpos($displayName, '-') !== false) {
                $parts = explode('.', $displayName);
                $last = end($parts);
                $displayName = ucwords(str_replace('-', ' ', $last));
            }

            $packages[] = [
                'section_id' => $row['section_id'],
                'package_id' => $row['package_id'],
                'name' => $displayName,
                'slug' => $row['slug'],
                'icon' => $row['icon'] ?? 'bi-box',
                'base_url' => $row['base_url'] ?? '/p/' . $row['package_id'],
                'version' => $row['installed_version'],
                'is_dynamic' => (bool) $row['is_dynamic'],
                'pages' => $pages,
            ];
        }

        return $packages;
    }

    /**
     * Check if a user has access to any installed package
     *
     * @param int $userId User ID
     * @param string $userRole User's effective role
     * @return bool True if user has access to management
     */
    public function userHasManagementAccess(int $userId, string $userRole): bool
    {
        // Super admins always have access
        if ($userRole === 'super_admin') {
            return true;
        }

        // Admin always has access
        if ($userRole === 'admin') {
            return true;
        }

        // Check if user's role(s) have access to any installed section
        $userRoles = $this->db->fetchAll(
            "SELECT role FROM user_global_roles WHERE user_id = ?",
            [$userId]
        );
        $allRoles = array_column($userRoles, 'role');
        if (!in_array($userRole, $allRoles)) {
            $allRoles[] = $userRole;
        }

        if (empty($allRoles)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($allRoles), '?'));

        $count = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt
             FROM section_installations si
             JOIN sections s ON si.section_id = s.id
             JOIN section_role_access sra ON s.id = sra.section_id
             WHERE si.status = 'installed'
               AND s.is_active = 1
               AND sra.role IN ({$placeholders})",
            $allRoles
        );

        return ($count['cnt'] ?? 0) > 0;
    }
}
