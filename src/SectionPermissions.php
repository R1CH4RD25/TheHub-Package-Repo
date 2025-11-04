<?php
/**
 * Section Permissions Manager
 * Handles granular permission checking for sections (submit, view, edit, etc.)
 */

namespace Hub;

use PDO;

class SectionPermissions
{
    private static $db;

    /**
     * Initialize database connection
     */
    private static function getDb()
    {
        if (!self::$db) {
            self::$db = Database::getInstance()->getConnection();
        }
        return self::$db;
    }

    /**
     * Check if a user can submit to a section
     *
     * @param int $userId User ID (null for anonymous)
     * @param string $sectionSlug Section slug
     * @param bool $isAnonymous Whether this is an anonymous submission
     * @return bool
     */
    public static function canSubmit($userId, $sectionSlug, $isAnonymous = false)
    {
        $db = self::getDb();

        // Get user's roles
        $roles = $userId ? self::getUserRoles($userId) : ['anonymous'];

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($roles), '?'));

        // Check if any of the user's roles have submit permission
        $sql = "SELECT ssp.can_submit, ssp.allow_anonymous
                FROM section_submission_permissions ssp
                JOIN sections s ON ssp.section_id = s.id
                WHERE s.slug = ?
                AND ssp.role_name IN ($placeholders)
                AND ssp.can_submit = TRUE";

        $stmt = $db->prepare($sql);
        $params = array_merge([$sectionSlug], $roles);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        // If anonymous submission, check if allowed
        if ($isAnonymous && !$result['allow_anonymous']) {
            return false;
        }

        return true;
    }

    /**
     * Check if a user can view/review submissions in a section
     *
     * @param int $userId User ID
     * @param string $sectionSlug Section slug
     * @return bool
     */
    public static function canReview($userId, $sectionSlug)
    {
        $db = self::getDb();
        $roles = self::getUserRoles($userId);

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($roles), '?'));

        $sql = "SELECT srp.can_view
                FROM section_review_permissions srp
                JOIN sections s ON srp.section_id = s.id
                WHERE s.slug = ?
                AND srp.role_name IN ($placeholders)
                AND srp.can_view = TRUE
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $params = array_merge([$sectionSlug], $roles);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Get detailed review permissions for a user
     *
     * @param int $userId User ID
     * @param string $sectionSlug Section slug
     * @return array Permissions array
     */
    public static function getReviewPermissions($userId, $sectionSlug)
    {
        $db = self::getDb();
        $roles = self::getUserRoles($userId);

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($roles), '?'));

        // Get the highest level of permissions across all roles
        $sql = "SELECT
                    MAX(srp.can_view) as can_view,
                    MAX(srp.can_edit) as can_edit,
                    MAX(srp.can_delete) as can_delete,
                    MAX(srp.can_add_notes) as can_add_notes,
                    MAX(srp.can_change_status) as can_change_status,
                    MAX(srp.can_assign) as can_assign,
                    MAX(srp.can_export) as can_export
                FROM section_review_permissions srp
                JOIN sections s ON srp.section_id = s.id
                WHERE s.slug = ?
                AND srp.role_name IN ($placeholders)";

        $stmt = $db->prepare($sql);
        $params = array_merge([$sectionSlug], $roles);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: [
            'can_view' => false,
            'can_edit' => false,
            'can_delete' => false,
            'can_add_notes' => false,
            'can_change_status' => false,
            'can_assign' => false,
            'can_export' => false
        ];
    }

    /**
     * Get section category information
     *
     * @param string $sectionSlug Section slug
     * @return array|null Category info
     */
    public static function getSectionCategory($sectionSlug)
    {
        $db = self::getDb();

        $sql = "SELECT sc.*
                FROM section_categories sc
                JOIN sections s ON s.category_id = sc.id
                WHERE s.slug = :slug";

        $stmt = $db->prepare($sql);
        $stmt->execute(['slug' => $sectionSlug]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /**
     * Get section configuration
     *
     * @param string $sectionSlug Section slug
     * @return array|null Configuration
     */
    public static function getSectionConfig($sectionSlug)
    {
        $db = self::getDb();

        $sql = "SELECT sconf.*
                FROM section_configuration sconf
                JOIN sections s ON sconf.section_id = s.id
                WHERE s.slug = :slug";

        $stmt = $db->prepare($sql);
        $stmt->execute(['slug' => $sectionSlug]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return null if not found
        if ($config === false) {
            return null;
        }

        if ($config && $config['allowed_file_types']) {
            $config['allowed_file_types'] = json_decode($config['allowed_file_types'], true);
        }
        if ($config && $config['form_config']) {
            $config['form_config'] = json_decode($config['form_config'], true);
        }

        return $config;
    }    /**
     * Get guidelines for a section
     *
     * @param string $sectionSlug Section slug
     * @param string $type Guidelines type (submission, review, general)
     * @return array Guidelines
     */
    public static function getGuidelines($sectionSlug, $type = null)
    {
        $db = self::getDb();

        $sql = "SELECT sg.*
                FROM section_guidelines sg
                JOIN sections s ON sg.section_id = s.id
                WHERE s.slug = :slug
                AND sg.is_active = TRUE";

        $params = ['slug' => $sectionSlug];

        if ($type) {
            $sql .= " AND sg.guideline_type = :type";
            $params['type'] = $type;
        }

        $sql .= " ORDER BY sg.display_order ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get users who should be notified for a section event
     *
     * @param string $sectionSlug Section slug
     * @param string $eventType Event type (submission, status_change, assignment, comment)
     * @return array Users with their notification preferences
     */
    public static function getNotificationRecipients($sectionSlug, $eventType = 'submission')
    {
        $db = self::getDb();

        // Map event type to column name
        $eventColumn = match($eventType) {
            'submission' => 'notify_on_submission',
            'status_change' => 'notify_on_status_change',
            'assignment' => 'notify_on_assignment',
            'comment' => 'notify_on_comment',
            default => 'notify_on_submission'
        };

        // Get all users with roles that should be notified
        $sql = "SELECT DISTINCT
                    u.id,
                    u.email,
                    u.name,
                    u.phone,
                    u.alt_email,
                    u.preferred_contact_method,
                    snr.role_name,
                    snr.email_enabled as notify_email,
                    snr.sms_enabled as notify_sms
                FROM section_notification_rules snr
                JOIN sections s ON snr.section_id = s.id
                JOIN users u ON u.role = snr.role_name
                WHERE s.slug = :slug
                AND snr.{$eventColumn} = TRUE
                AND snr.is_active = TRUE
                AND u.is_active = TRUE";

        $stmt = $db->prepare($sql);
        $stmt->execute(['slug' => $sectionSlug]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all roles for a user (including global roles)
     *
     * @param int $userId User ID
     * @return array Array of role names
     */
    private static function getUserRoles($userId)
    {
        $db = self::getDb();

        // Get user's primary role
        $stmt = $db->prepare("SELECT role FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $primaryRole = $stmt->fetchColumn();

        $roles = $primaryRole ? [$primaryRole] : [];

        // Get additional global roles
        $stmt = $db->prepare("SELECT role FROM user_global_roles WHERE user_id = :id");
        $stmt->execute(['id' => $userId]);
        $globalRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $roles = array_merge($roles, $globalRoles);

        return array_unique($roles);
    }

    /**
     * Check if a section requires a specific category feature
     *
     * @param string $sectionSlug Section slug
     * @param string $feature Feature name (forms, submissions, notifications, guidelines)
     * @return bool
     */
    public static function requiresFeature($sectionSlug, $feature)
    {
        $category = self::getSectionCategory($sectionSlug);

        if (!$category) {
            return false;
        }

        $column = 'requires_' . $feature;
        return isset($category[$column]) && $category[$column];
    }

    /**
     * Validate section configuration completeness
     *
     * @param int $sectionId Section ID
     * @return array Validation results
     */
    public static function validateSectionConfig($sectionId)
    {
        $db = self::getDb();
        $errors = [];
        $warnings = [];

        // Get section and category
        $sql = "SELECT s.slug, s.display_name, sc.*
                FROM sections s
                LEFT JOIN section_categories sc ON s.category_id = sc.id
                WHERE s.id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $sectionId]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$section) {
            return ['errors' => ['Section not found'], 'warnings' => []];
        }

        // Check if category is set
        if (!$section['name']) {
            $errors[] = "Section category not set";
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Check submission permissions if required
        if ($section['requires_submissions']) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM section_submission_permissions WHERE section_id = :id AND can_submit = TRUE");
            $stmt->execute(['id' => $sectionId]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = "No submission permissions configured (required for {$section['display_name']} category)";
            }
        }

        // Check review permissions if forms are required
        if ($section['requires_forms']) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM section_review_permissions WHERE section_id = :id AND can_view = TRUE");
            $stmt->execute(['id' => $sectionId]);
            if ($stmt->fetchColumn() == 0) {
                $errors[] = "No review permissions configured (required for {$section['display_name']} category)";
            }
        }

        // Check notification rules if required
        if ($section['requires_notifications']) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM section_notification_rules WHERE section_id = :id AND is_active = TRUE");
            $stmt->execute(['id' => $sectionId]);
            if ($stmt->fetchColumn() == 0) {
                $warnings[] = "No notification rules configured (recommended for {$section['display_name']} category)";
            }
        }

        // Check guidelines if required
        if ($section['requires_guidelines']) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM section_guidelines WHERE section_id = :id AND is_active = TRUE");
            $stmt->execute(['id' => $sectionId]);
            if ($stmt->fetchColumn() == 0) {
                $warnings[] = "No guidelines configured (recommended for {$section['display_name']} category)";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
