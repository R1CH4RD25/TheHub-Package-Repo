<?php

namespace Hub;

use PDO;

/**
 * SectionAccess - Manages per-user, per-section access control
 * 
 * This class handles the new multi-section architecture where users can have
 * different roles in different sections of the platform.
 */
class SectionAccess
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all sections a user has access to
     * Super admins automatically get access to all active sections
     * 
     * @param int $userId User ID
     * @return array Array of section details with user's role in each
     */
    public function getUserSections($userId)
    {
        // Check if user is super admin (gets access to everything)
        $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        
        if ($user && $user['role'] === 'super_admin') {
            // Super admins see all active sections with super_admin role
            return $this->db->fetchAll("
                SELECT 
                    id as section_id,
                    name as section_name,
                    slug as section_slug,
                    display_name as section_display_name,
                    description,
                    icon as section_icon,
                    base_url as section_base_url,
                    'super_admin' as role,
                    sort_order
                FROM sections 
                WHERE is_active = TRUE 
                ORDER BY sort_order, display_name
            ");
        } else {
            // Regular users only see sections they have explicit access to
            return $this->db->fetchAll("
                SELECT * FROM section_access_view 
                WHERE user_id = ? AND section_is_active = TRUE 
                ORDER BY section_slug
            ", [$userId]);
        }
    }

    /**
     * Check if user has access to a specific section with minimum role
     * 
     * @param int $userId User ID
     * @param string $sectionSlug Section slug (e.g., 'fuel-travel')
     * @param string $minimumRole Minimum required role (default: 'staff')
     * @return bool True if user has access with sufficient role
     */
    public function hasAccess($userId, $sectionSlug, $minimumRole = 'staff')
    {
        // Check if user is super admin
        $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        
        if ($user && $user['role'] === 'super_admin') {
            return true; // Super admins have access to everything
        }

        // Check section access
        $access = $this->db->fetchOne("
            SELECT sa.role 
            FROM section_access sa
            JOIN sections s ON sa.section_id = s.id
            WHERE sa.user_id = ? AND s.slug = ? AND s.is_active = TRUE
        ", [$userId, $sectionSlug]);

        if (!$access) {
            return false; // No access granted
        }

        // Check if user's role meets minimum requirement
        return $this->compareRoles($access['role'], $minimumRole) >= 0;
    }

    /**
     * Get user's role in a specific section
     * 
     * @param int $userId User ID
     * @param string $sectionSlug Section slug
     * @return string|null User's role in the section, or null if no access
     */
    public function getUserRole($userId, $sectionSlug)
    {
        // Check if user is super admin
        $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        
        if ($user && $user['role'] === 'super_admin') {
            return 'super_admin';
        }

        $access = $this->db->fetchOne("
            SELECT sa.role 
            FROM section_access sa
            JOIN sections s ON sa.section_id = s.id
            WHERE sa.user_id = ? AND s.slug = ?
        ", [$userId, $sectionSlug]);

        return $access ? $access['role'] : null;
    }

    /**
     * Grant user access to a section with specific role
     * 
     * @param int $userId User ID to grant access
     * @param string $sectionSlug Section slug
     * @param string $role Role to grant
     * @param int $grantedBy User ID granting access
     * @return bool Success
     */
    public function grantAccess($userId, $sectionSlug, $role, $grantedBy)
    {
        // Get section ID
        $section = $this->db->fetchOne("SELECT id FROM sections WHERE slug = ?", [$sectionSlug]);

        if (!$section) {
            throw new \Exception("Section not found: $sectionSlug");
        }

        // Insert or update access
        $this->db->execute("
            INSERT INTO section_access (user_id, section_id, role, granted_by) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE role = ?, granted_by = ?, updated_at = CURRENT_TIMESTAMP
        ", [$userId, $section['id'], $role, $grantedBy, $role, $grantedBy]);
        
        return true;
    }

    /**
     * Revoke user's access to a section
     * 
     * @param int $userId User ID
     * @param string $sectionSlug Section slug
     * @return bool Success
     */
    public function revokeAccess($userId, $sectionSlug)
    {
        $this->db->execute("
            DELETE sa FROM section_access sa
            JOIN sections s ON sa.section_id = s.id
            WHERE sa.user_id = ? AND s.slug = ?
        ", [$userId, $sectionSlug]);
        
        return true;
    }

    /**
     * Update user's role in a section (without changing granted_by)
     * 
     * @param int $userId User ID
     * @param string $sectionSlug Section slug
     * @param string $newRole New role to assign
     * @return bool Success
     */
    public function updateRole($userId, $sectionSlug, $newRole)
    {
        $this->db->execute("
            UPDATE section_access sa
            JOIN sections s ON sa.section_id = s.id
            SET sa.role = ?
            WHERE sa.user_id = ? AND s.slug = ?
        ", [$newRole, $userId, $sectionSlug]);
        
        return true;
    }

    /**
     * Get all sections (for admin UI)
     * 
     * @return array All sections
     */
    public function getAllSections()
    {
        return $this->db->fetchAll("SELECT * FROM sections ORDER BY sort_order, display_name");
    }

    /**
     * Get all users with their section access (for admin UI)
     * 
     * @param string $sectionSlug Optional: filter by section
     * @return array Users with section access details
     */
    public function getUsersWithAccess($sectionSlug = null)
    {
        if ($sectionSlug) {
            return $this->db->fetchAll("
                SELECT * FROM section_access_view 
                WHERE section_slug = ?
                ORDER BY user_name
            ", [$sectionSlug]);
        } else {
            return $this->db->fetchAll("SELECT * FROM section_access_view ORDER BY user_name, section_slug");
        }
    }

    /**
     * Compare two roles to determine hierarchy
     * Returns: 1 if role1 > role2, 0 if equal, -1 if role1 < role2
     * 
     * @param string $role1 First role
     * @param string $role2 Second role
     * @return int Comparison result
     */
    private function compareRoles($role1, $role2)
    {
        $hierarchy = [
            'staff' => 1,
            'maintenance' => 2,
            'maintenance_director' => 3,
            'manager' => 4,
            'admin' => 5,
            'super_admin' => 6
        ];

        $level1 = $hierarchy[$role1] ?? 0;
        $level2 = $hierarchy[$role2] ?? 0;

        if ($level1 > $level2) return 1;
        if ($level1 < $level2) return -1;
        return 0;
    }
}
