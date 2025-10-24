<?php

namespace Hub;

class SectionRoleAccess
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Check if a user has access to a section based on their roles
     * 
     * @param int $userId User ID
     * @param string $sectionSlug Section slug identifier
     * @return bool True if user has access
     */
    public function hasAccess(int $userId, string $sectionSlug): bool
    {
        // Get user's roles (both primary role and global roles)
        $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return false;
        }

        // Super admins always have access
        if ($user['role'] === 'super_admin') {
            return true;
        }

        // Get all user's roles from user_global_roles
        $userRoles = $this->db->fetchAll("
            SELECT role FROM user_global_roles WHERE user_id = ?
        ", [$userId]);
        
        $allRoles = array_column($userRoles, 'role');
        // Include primary role
        if (!in_array($user['role'], $allRoles)) {
            $allRoles[] = $user['role'];
        }

        // Check if any of the user's roles have access to this section
        foreach ($allRoles as $role) {
            $access = $this->db->fetchOne("
                SELECT COUNT(*) as has_access
                FROM section_role_access sra
                JOIN sections s ON sra.section_id = s.id
                WHERE s.slug = ? AND sra.role = ? AND s.is_active = TRUE
            ", [$sectionSlug, $role]);

            if ($access && $access['has_access'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all sections a user has access to
     * 
     * @param int $userId User ID
     * @return array Array of section objects
     */
    public function getUserSections(int $userId): array
    {
        // Get user's roles
        $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return [];
        }

        // Super admins see all active sections
        if ($user['role'] === 'super_admin') {
            return $this->db->fetchAll("
                SELECT * FROM sections 
                WHERE is_active = TRUE 
                ORDER BY sort_order, display_name
            ");
        }

        // Get all user's roles
        $userRoles = $this->db->fetchAll("
            SELECT role FROM user_global_roles WHERE user_id = ?
        ", [$userId]);
        
        $allRoles = array_column($userRoles, 'role');
        if (!in_array($user['role'], $allRoles)) {
            $allRoles[] = $user['role'];
        }

        if (empty($allRoles)) {
            return [];
        }

        // Get sections where user has role-based access
        $placeholders = implode(',', array_fill(0, count($allRoles), '?'));
        
        return $this->db->fetchAll("
            SELECT DISTINCT s.*
            FROM sections s
            JOIN section_role_access sra ON s.id = sra.section_id
            WHERE sra.role IN ($placeholders)
              AND s.is_active = TRUE
            ORDER BY s.sort_order, s.display_name
        ", $allRoles);
    }

    /**
     * Get all roles that have access to a section
     * 
     * @param int $sectionId Section ID
     * @return array Array of role names
     */
    public function getSectionRoles(int $sectionId): array
    {
        $roles = $this->db->fetchAll("
            SELECT role FROM section_role_access WHERE section_id = ?
        ", [$sectionId]);

        return array_column($roles, 'role');
    }

    /**
     * Grant access to a section for specific roles
     * 
     * @param int $sectionId Section ID
     * @param array $roles Array of role names
     * @param int $grantedBy User ID of who granted access
     * @return bool Success
     */
    public function grantAccess(int $sectionId, array $roles, int $grantedBy): bool
    {
        try {
            $this->db->beginTransaction();

            // Remove existing access
            $this->db->execute("DELETE FROM section_role_access WHERE section_id = ?", [$sectionId]);

            // Add new access for each role
            foreach ($roles as $role) {
                $this->db->execute("
                    INSERT INTO section_role_access (section_id, role, granted_by)
                    VALUES (?, ?, ?)
                ", [$sectionId, $role, $grantedBy]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Failed to grant section access: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a section exists and is active
     * 
     * @param string $sectionSlug Section slug
     * @return bool
     */
    public function sectionExists(string $sectionSlug): bool
    {
        $section = $this->db->fetchOne("
            SELECT id FROM sections WHERE slug = ? AND is_active = TRUE
        ", [$sectionSlug]);

        return $section !== null;
    }
}
