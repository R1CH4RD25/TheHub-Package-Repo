<?php

namespace Hub;

use PDO;

/**
 * Package Access Control
 * 
 * Central permission checking system for the three-tier architecture.
 * Handles Hub, Management, and Admin section access based on package roles.
 */
class PackageAccess
{
    private PDO $db;
    private static ?array $permissionCache = null;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Check if user has a specific permission in a package
     * 
     * @param int $userId
     * @param int $packageId
     * @param string $permission e.g., 'hub.submit_form', 'management.export_data'
     * @return bool
     */
    public static function hasPermission(int $userId, int $packageId, string $permission): bool
    {
        // Super admin bypass
        if (Auth::isSuperAdmin($userId)) {
            return true;
        }

        $permissions = self::getUserPermissions($userId, $packageId);
        return in_array($permission, $permissions);
    }

    /**
     * Get all permissions for a user in a package (union of all their roles)
     * 
     * @param int $userId
     * @param int $packageId
     * @return array Array of permission strings
     */
    public static function getUserPermissions(int $userId, int $packageId): array
    {
        $cacheKey = "{$userId}:{$packageId}";
        if (isset(self::$permissionCache[$cacheKey])) {
            return self::$permissionCache[$cacheKey];
        }

        $db = Database::getInstance()->getConnection();

        // Get all package roles the user has access to via their org roles
        $stmt = $db->prepare(
            "SELECT DISTINCT pr.permissions
             FROM user_org_roles uor
             JOIN package_role_mappings prm ON uor.org_role_id = prm.org_role_id
             JOIN package_roles pr ON prm.package_role_id = pr.id
             WHERE uor.user_id = :user_id AND prm.package_id = :package_id"
        );
        
        $stmt->execute([
            'user_id' => $userId,
            'package_id' => $packageId
        ]);

        // Union all permissions
        $allPermissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions = json_decode($row['permissions'], true);
            $allPermissions = array_merge($allPermissions, $permissions);
        }

        $allPermissions = array_unique(array_values($allPermissions));
        self::$permissionCache[$cacheKey] = $allPermissions;

        return $allPermissions;
    }

    /**
     * Check if user has ANY permission in a specific section
     * Used to determine if section links should be shown
     * 
     * @param int $userId
     * @param string $section 'hub', 'management', or 'admin'
     * @return bool
     */
    public static function hasAnySectionAccess(int $userId, string $section): bool
    {
        // Super admin always has access
        if (Auth::isSuperAdmin($userId)) {
            return true;
        }

        // Admin section is based on global role, not package permissions
        if ($section === 'admin') {
            $role = Auth::getEffectiveRole($userId);
            return in_array($role, ['super_admin', 'admin']);
        }

        $db = Database::getInstance()->getConnection();

        // Check if user has any permission starting with section prefix
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM user_org_roles uor
             JOIN package_role_mappings prm ON uor.org_role_id = prm.org_role_id
             JOIN package_roles pr ON prm.package_role_id = pr.id
             WHERE uor.user_id = :user_id
             AND JSON_SEARCH(pr.permissions, 'one', :pattern) IS NOT NULL"
        );

        $stmt->execute([
            'user_id' => $userId,
            'pattern' => $section . '.%'
        ]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get all packages where user has any access
     * 
     * @param int $userId
     * @param string|null $section Filter by section (hub, management, admin)
     * @return array Array of package data
     */
    public static function getUserPackages(int $userId, ?string $section = null): array
    {
        $db = Database::getInstance()->getConnection();

        $sql = "SELECT DISTINCT p.*
                FROM packages p
                JOIN package_role_mappings prm ON p.id = prm.package_id
                JOIN user_org_roles uor ON prm.org_role_id = uor.org_role_id";

        if ($section && $section !== 'admin') {
            $sql .= " JOIN package_roles pr ON prm.package_role_id = pr.id";
        }

        $sql .= " WHERE uor.user_id = :user_id AND p.is_active = 1";

        if ($section && $section !== 'admin') {
            $sql .= " AND JSON_SEARCH(pr.permissions, 'one', :pattern) IS NOT NULL";
        }

        $sql .= " ORDER BY p.name";

        $stmt = $db->prepare($sql);
        $params = ['user_id' => $userId];
        
        if ($section && $section !== 'admin') {
            $params['pattern'] = $section . '.%';
        }

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Hub items visible to a user for a package
     * 
     * @param int $userId
     * @param int $packageId
     * @return array
     */
    public static function getHubItems(int $userId, int $packageId): array
    {
        $permissions = self::getUserPermissions($userId, $packageId);

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT * FROM package_hub_items
             WHERE package_id = :package_id AND is_active = 1
             ORDER BY sort_order, title"
        );
        $stmt->execute(['package_id' => $packageId]);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            // Check if user has required permission (if specified)
            if (!$item['required_permission'] || in_array($item['required_permission'], $permissions)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Get Management tabs visible to a user for a package
     * 
     * @param int $userId
     * @param int $packageId
     * @return array
     */
    public static function getManagementTabs(int $userId, int $packageId): array
    {
        $permissions = self::getUserPermissions($userId, $packageId);

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "SELECT * FROM package_management_tabs
             WHERE package_id = :package_id AND is_active = 1
             ORDER BY sort_order, label"
        );
        $stmt->execute(['package_id' => $packageId]);

        $tabs = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tab) {
            // Check if user has required permission (if specified)
            if (!$tab['required_permission'] || in_array($tab['required_permission'], $permissions)) {
                $tabs[] = $tab;
            }
        }

        return $tabs;
    }

    /**
     * Clear permission cache (call after role changes)
     */
    public static function clearCache(?int $userId = null, ?int $packageId = null): void
    {
        if ($userId && $packageId) {
            unset(self::$permissionCache["{$userId}:{$packageId}"]);
        } else {
            self::$permissionCache = null;
        }
    }

    /**
     * Check if user can perform an action (alias for hasPermission for readability)
     * 
     * @param int $userId
     * @param int $packageId
     * @param string $action 'submit_form', 'export_data', etc.
     * @param string $section 'hub', 'management', 'admin'
     * @return bool
     */
    public static function can(int $userId, int $packageId, string $action, string $section = 'hub'): bool
    {
        return self::hasPermission($userId, $packageId, "{$section}.{$action}");
    }
}
