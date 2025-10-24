<?php

namespace Hub;

class Module
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all active modules
     */
    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT * FROM modules WHERE is_active = TRUE ORDER BY sort_order ASC, display_name ASC"
        );
    }

    /**
     * Get modules accessible to a specific user
     */
    public function getUserModules($userId)
    {
        // Super admins get access to all modules
        $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        
        if ($user && $user['role'] === 'super_admin') {
            return $this->db->fetchAll(
                "SELECT m.*, 'super_admin' as user_role 
                 FROM modules m 
                 WHERE m.is_active = TRUE 
                 ORDER BY m.sort_order ASC, m.display_name ASC"
            );
        }

        // Regular users get their assigned modules
        return $this->db->fetchAll(
            "SELECT m.*, uma.role as user_role, uma.granted_at
             FROM user_module_access uma
             JOIN modules m ON uma.module_id = m.id
             WHERE uma.user_id = ? 
               AND uma.is_active = TRUE 
               AND m.is_active = TRUE
             ORDER BY m.sort_order ASC, m.display_name ASC",
            [$userId]
        );
    }

    /**
     * Check if user has access to a specific module
     */
    public function hasAccess($userId, $moduleSlug, $minimumRole = null)
    {
        // Super admins have access to everything
        $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
        if ($user && $user['role'] === 'super_admin') {
            return true;
        }

        $sql = "SELECT uma.role
                FROM user_module_access uma
                JOIN modules m ON uma.module_id = m.id
                WHERE uma.user_id = ? 
                  AND m.slug = ? 
                  AND uma.is_active = TRUE 
                  AND m.is_active = TRUE";
        
        $access = $this->db->fetchOne($sql, [$userId, $moduleSlug]);
        
        if (!$access) {
            return false;
        }

        // If no minimum role specified, just having access is enough
        if (!$minimumRole) {
            return true;
        }

        // Check role hierarchy
        $roleHierarchy = ['staff' => 1, 'manager' => 2, 'admin' => 3, 'super_admin' => 4];
        $userRoleLevel = $roleHierarchy[$access['role']] ?? 0;
        $minimumRoleLevel = $roleHierarchy[$minimumRole] ?? 0;

        return $userRoleLevel >= $minimumRoleLevel;
    }

    /**
     * Grant user access to a module
     */
    public function grantAccess($userId, $moduleId, $role, $grantedBy)
    {
        return $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by) 
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role), granted_by = VALUES(granted_by), is_active = TRUE",
            [$userId, $moduleId, $role, $grantedBy]
        );
    }

    /**
     * Revoke user access to a module
     */
    public function revokeAccess($userId, $moduleId)
    {
        return $this->db->execute(
            "UPDATE user_module_access SET is_active = FALSE WHERE user_id = ? AND module_id = ?",
            [$userId, $moduleId]
        );
    }

    /**
     * Get by slug
     */
    public function getBySlug($slug)
    {
        return $this->db->fetchOne("SELECT * FROM modules WHERE slug = ? AND is_active = TRUE", [$slug]);
    }

    /**
     * Create a new module (super admin only)
     */
    public function create($data)
    {
        return $this->db->execute(
            "INSERT INTO modules (name, display_name, description, icon, slug, base_url, sort_order) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['display_name'],
                $data['description'] ?? null,
                $data['icon'] ?? '📦',
                $data['slug'],
                $data['base_url'],
                $data['sort_order'] ?? 99
            ]
        );
    }

    /**
     * Get all user access for a specific module (for admin management)
     */
    public function getModuleUsers($moduleId)
    {
        return $this->db->fetchAll(
            "SELECT * FROM user_module_access_view WHERE module_id = ? ORDER BY user_name ASC",
            [$moduleId]
        );
    }
}
