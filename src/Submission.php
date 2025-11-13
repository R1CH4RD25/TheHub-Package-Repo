<?php
/**
 * Submission - Command Center Submission Management
 *
 * Handles CRUD operations for section submissions across all sections.
 * Core class for the Command Center submission management system.
 *
 * CRITICAL QUERY PATTERN:
 * - ALL queries MUST include `WHERE is_draft = 0` unless explicitly fetching drafts
 * - Draft submissions are EXCLUDED from default views
 * - Use getDrafts() method for draft-specific queries
 *
 * @package Hub
 */

namespace Hub;

class Submission
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get submission by ID
     *
     * @param int $id Submission ID
     * @param bool $includeDrafts Whether to include drafts (default: false)
     * @return array|null Submission data or null if not found
     */
    public function getById($id, $includeDrafts = false)
    {
        $draftClause = $includeDrafts ? '' : 'AND s.is_draft = 0';

        $sql = "SELECT 
                    s.*,
                    sec.name AS section_name,
                    sec.slug AS section_slug,
                    sec.cc_prefix,
                    st.status_name,
                    st.status_color,
                    st.status_icon,
                    u1.name AS submitted_by_name,
                    u2.name AS reviewed_by_name,
                    u3.name AS assigned_to_name
                FROM section_submissions s
                INNER JOIN sections sec ON s.section_id = sec.id
                INNER JOIN section_submission_statuses st ON s.status_id = st.id
                LEFT JOIN users u1 ON s.submitted_by = u1.id
                LEFT JOIN users u2 ON s.reviewed_by = u2.id
                LEFT JOIN users u3 ON s.assigned_to = u3.id
                WHERE s.id = ? 
                  AND s.is_active = 1
                  {$draftClause}";

        $result = $this->db->fetchOne($sql, [$id]);

        if ($result && $result['submission_data']) {
            $result['submission_data'] = json_decode($result['submission_data'], true);
        }

        return $result;
    }

    /**
     * Get submissions for a section
     *
     * @param int $sectionId Section ID
     * @param array $filters Optional filters (status_id, priority, assigned_to, etc.)
     * @param bool $includeDrafts Whether to include drafts (default: false)
     * @param int $limit Limit results (default: 100)
     * @param int $offset Offset for pagination (default: 0)
     * @return array Array of submissions
     */
    public function getBySectionId(
        $sectionId,
        $filters = [],
        $includeDrafts = false,
        $limit = 100,
        $offset = 0
    ) {
        $params = [$sectionId];
        $whereClauses = ['s.section_id = ?', 's.is_active = 1'];

        // Draft filtering
        if (!$includeDrafts) {
            $whereClauses[] = 's.is_draft = 0';
        }

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

        // Assigned to filter
        if (!empty($filters['assigned_to'])) {
            $whereClauses[] = 's.assigned_to = ?';
            $params[] = $filters['assigned_to'];
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $whereClauses[] = 's.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $whereClauses[] = 's.created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $whereClauses);

        $sql = "SELECT 
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
                ORDER BY s.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Create a new submission
     *
     * @param array $data Submission data
     * @return int|false Submission ID or false on failure
     */
    public function create($data)
    {
        // Validate required fields
        $required = ['section_id', 'status_id', 'submission_data'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Encode JSON data
        if (is_array($data['submission_data'])) {
            $data['submission_data'] = json_encode($data['submission_data']);
        }

        // Generate display_id if cc_prefix is set
        if (!empty($data['cc_prefix'])) {
            $data['display_id'] = $this->generateDisplayId($data['cc_prefix'], $data['section_id']);
            unset($data['cc_prefix']); // Don't store this in submissions table
        }

        // Set defaults
        $data['tenant_id'] = $data['tenant_id'] ?? 1;
        $data['priority'] = $data['priority'] ?? 'normal';
        $data['is_draft'] = $data['is_draft'] ?? 0;

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO section_submissions (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $this->db->execute($sql, array_values($data));

        $submissionId = $this->db->getConnection()->lastInsertId();

        // Log creation in history
        $this->addHistory($submissionId, $data['submitted_by'] ?? null, 'created', null, 'Submission created', 'New submission received');

        return $submissionId;
    }

    /**
     * Update submission
     *
     * @param int $id Submission ID
     * @param array $data Updated data
     * @param int|null $userId User making the change
     * @return bool Success status
     */
    public function update($id, $data, $userId = null)
    {
        // Get current submission for history tracking
        $current = $this->getById($id, true); // Include drafts for updates
        if (!$current) {
            return false;
        }

        // Track changes for history
        $changes = [];

        // Encode JSON data if needed
        if (isset($data['submission_data']) && is_array($data['submission_data'])) {
            $data['submission_data'] = json_encode($data['submission_data']);
        }

        // Build UPDATE query
        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            if ($column !== 'id') {
                $setClauses[] = "{$column} = ?";
                $params[] = $value;

                // Track significant changes
                if (in_array($column, ['status_id', 'priority', 'assigned_to']) && $current[$column] != $value) {
                    $changes[$column] = [
                        'old' => $current[$column],
                        'new' => $value
                    ];
                }
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $params[] = $id;

        $sql = "UPDATE section_submissions 
                SET " . implode(', ', $setClauses) . "
                WHERE id = ? AND is_active = 1";

        $this->db->execute($sql, $params);

        // Log changes in history
        foreach ($changes as $field => $change) {
            $action = $field . '_change';
            $this->addHistory($id, $userId, $action, $change['old'], $change['new'], ucfirst($field) . ' updated');
        }

        return true;
    }

    /**
     * Change submission status
     *
     * @param int $id Submission ID
     * @param int $statusId New status ID
     * @param int|null $userId User making the change
     * @param string|null $notes Optional notes
     * @return bool Success status
     */
    public function changeStatus($id, $statusId, $userId = null, $notes = null)
    {
        $current = $this->getById($id, true);
        if (!$current) {
            return false;
        }

        $sql = "UPDATE section_submissions 
                SET status_id = ?,
                    reviewed_at = NOW(),
                    reviewed_by = ?
                WHERE id = ? AND is_active = 1";

        $this->db->execute($sql, [$statusId, $userId, $id]);

        // Log status change
        $this->addHistory(
            $id,
            $userId,
            'status_change',
            $current['status_id'],
            $statusId,
            $notes ?? 'Status changed'
        );

        return true;
    }

    /**
     * Soft delete submission
     *
     * @param int $id Submission ID
     * @param int|null $userId User making the change
     * @return bool Success status
     */
    public function delete($id, $userId = null)
    {
        $sql = "UPDATE section_submissions 
                SET is_active = 0
                WHERE id = ?";

        $this->db->execute($sql, [$id]);

        $this->addHistory($id, $userId, 'deleted', 1, 0, 'Submission deleted', 'critical');

        return true;
    }

    /**
     * Get submission comments
     *
     * @param int $id Submission ID
     * @param bool $includeInternal Whether to include internal comments
     * @return array Array of comments
     */
    public function getComments($id, $includeInternal = false)
    {
        $internalClause = $includeInternal ? '' : 'AND c.is_internal = 0';

        $sql = "SELECT 
                    c.*,
                    u.name AS user_name
                FROM section_submission_comments c
                INNER JOIN users u ON c.user_id = u.id
                WHERE c.submission_id = ?
                  AND c.is_active = 1
                  {$internalClause}
                ORDER BY c.created_at ASC";

        return $this->db->fetchAll($sql, [$id]);
    }

    /**
     * Add comment to submission
     *
     * @param int $id Submission ID
     * @param int $userId User ID
     * @param string $commentText Comment text
     * @param bool $isInternal Whether comment is internal
     * @param int|null $parentCommentId Parent comment ID for threading
     * @return int|false Comment ID or false on failure
     */
    public function addComment($id, $userId, $commentText, $isInternal = false, $parentCommentId = null)
    {
        $sql = "INSERT INTO section_submission_comments 
                (submission_id, user_id, comment_text, is_internal, parent_comment_id, tenant_id)
                VALUES (?, ?, ?, ?, ?, 1)";

        $this->db->execute($sql, [$id, $userId, $commentText, $isInternal ? 1 : 0, $parentCommentId]);

        $commentId = $this->db->getConnection()->lastInsertId();

        // Log comment in history
        $action = $isInternal ? 'internal_comment' : 'comment';
        $this->addHistory($id, $userId, $action, null, null, 'Comment added');

        return $commentId;
    }

    /**
     * Get submission attachments
     *
     * @param int $id Submission ID
     * @return array Array of attachments
     */
    public function getAttachments($id)
    {
        $sql = "SELECT 
                    a.*,
                    u.name AS uploaded_by_name
                FROM section_submission_attachments a
                INNER JOIN users u ON a.uploaded_by = u.id
                WHERE a.submission_id = ?
                  AND a.is_active = 1
                ORDER BY a.created_at DESC";

        return $this->db->fetchAll($sql, [$id]);
    }

    /**
     * Get submission history
     *
     * @param int $id Submission ID
     * @param int $limit Limit results (default: 50)
     * @return array Array of history entries
     */
    public function getHistory($id, $limit = 50)
    {
        $sql = "SELECT 
                    h.*,
                    u.name AS user_name
                FROM section_submission_history h
                INNER JOIN users u ON h.user_id = u.id
                WHERE h.submission_id = ?
                ORDER BY h.created_at DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, [$id, $limit]);
    }

    /**
     * Add history entry
     *
     * @param int $submissionId Submission ID
     * @param int|null $userId User ID
     * @param string $action Action type
     * @param mixed $oldValue Old value
     * @param mixed $newValue New value
     * @param string|null $notes Optional notes
     * @param string $severity Severity level (info, warning, critical)
     * @return bool Success status
     */
    private function addHistory(
        $submissionId,
        $userId,
        $action,
        $oldValue,
        $newValue,
        $notes = null,
        $severity = 'info'
    ) {
        // If no user ID, try to get current user
        if (!$userId && isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }

        // Skip if still no user (system action)
        if (!$userId) {
            return false;
        }

        $sql = "INSERT INTO section_submission_history 
                (submission_id, user_id, action, old_value, new_value, notes, severity, tenant_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

        $this->db->execute($sql, [
            $submissionId,
            $userId,
            $action,
            $oldValue,
            $newValue,
            $notes,
            $severity
        ]);

        return true;
    }

    /**
     * Generate unique display_id
     *
     * @param string $prefix Prefix (e.g., 'BR', 'VR')
     * @param int $sectionId Section ID
     * @return string Display ID (e.g., 'BR-2024-001')
     */
    private function generateDisplayId($prefix, $sectionId)
    {
        $year = date('Y');

        // Get next sequence number for this section and year
        $sql = "SELECT COUNT(*) as count 
                FROM section_submissions 
                WHERE section_id = ? 
                  AND display_id LIKE ?
                  AND YEAR(created_at) = ?";

        $result = $this->db->fetchOne($sql, [$sectionId, "{$prefix}-{$year}-%", $year]);
        $nextNum = ($result['count'] ?? 0) + 1;

        return sprintf('%s-%s-%03d', $prefix, $year, $nextNum);
    }

    /**
     * Get default status ID (typically "Submitted")
     *
     * @return int|null Default status ID
     */
    public static function getDefaultStatusId()
    {
        $db = Database::getInstance();

        $sql = "SELECT id FROM section_submission_statuses 
                WHERE status_name = 'Submitted'
                  AND section_id IS NULL
                  AND is_active = 1
                LIMIT 1";

        $result = $db->fetchOne($sql);

        return $result['id'] ?? null;
    }
}
