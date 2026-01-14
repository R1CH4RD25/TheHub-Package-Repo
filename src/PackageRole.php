<?php

namespace Hub;

use PDO;

/**
 * Package Role Management
 * 
 * Handles package-defined roles that are immutable and shipped with packages.
 * These define what permissions exist within each package.
 */
class PackageRole
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create package roles from manifest during package installation
     * 
     * @param int $packageId
     * @param array $rolesData Array of role definitions from manifest
     * @return bool
     */
    public function createFromManifest(int $packageId, array $rolesData): bool
    {
        if (empty($rolesData)) {
            throw new \InvalidArgumentException("Package must define at least one role");
        }

        $stmt = $this->db->prepare(
            "INSERT INTO package_roles (package_id, role_key, role_name, tier_level, description, permissions)
             VALUES (:package_id, :role_key, :role_name, :tier_level, :description, :permissions)"
        );

        foreach ($rolesData as $role) {
            $this->validateRole($role);

            $stmt->execute([
                'package_id' => $packageId,
                'role_key' => $role['role_key'],
                'role_name' => $role['role_name'],
                'tier_level' => $role['tier_level'],
                'description' => $role['description'] ?? null,
                'permissions' => json_encode($role['permissions'])
            ]);
        }

        return true;
    }

    /**
     * Validate role structure from manifest
     */
    private function validateRole(array $role): void
    {
        $required = ['role_key', 'role_name', 'tier_level', 'permissions'];
        foreach ($required as $field) {
            if (!isset($role[$field])) {
                throw new \InvalidArgumentException("Role missing required field: {$field}");
            }
        }

        if (!is_array($role['permissions']) || empty($role['permissions'])) {
            throw new \InvalidArgumentException("Role must have at least one permission");
        }

        if (!is_int($role['tier_level']) || $role['tier_level'] < 1) {
            throw new \InvalidArgumentException("tier_level must be a positive integer");
        }
    }

    /**
     * Get all roles for a package
     */
    public function getByPackage(int $packageId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM package_roles WHERE package_id = :package_id ORDER BY tier_level, role_name"
        );
        $stmt->execute(['package_id' => $packageId]);
        
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON permissions
        foreach ($roles as &$role) {
            $role['permissions'] = json_decode($role['permissions'], true);
        }
        
        return $roles;
    }

    /**
     * Get a specific package role
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM package_roles WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($role) {
            $role['permissions'] = json_decode($role['permissions'], true);
        }
        
        return $role ?: null;
    }

    /**
     * Get all permissions available in a package (union of all role permissions)
     */
    public function getPackagePermissions(int $packageId): array
    {
        $roles = $this->getByPackage($packageId);
        $allPermissions = [];
        
        foreach ($roles as $role) {
            $allPermissions = array_merge($allPermissions, $role['permissions']);
        }
        
        return array_unique(array_values($allPermissions));
    }

    /**
     * Delete all roles for a package (during uninstallation)
     */
    public function deleteByPackage(int $packageId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM package_roles WHERE package_id = :package_id");
        return $stmt->execute(['package_id' => $packageId]);
    }

    /**
     * Map organization role(s) to a package role
     */
    public function mapOrgRoles(int $packageId, int $packageRoleId, array $orgRoleIds, int $mappedBy): bool
    {
        // Remove existing mappings for this package role
        $this->unmapOrgRoles($packageRoleId);

        if (empty($orgRoleIds)) {
            return true;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO package_role_mappings (package_id, package_role_id, org_role_id, mapped_by)
             VALUES (:package_id, :package_role_id, :org_role_id, :mapped_by)"
        );

        foreach ($orgRoleIds as $orgRoleId) {
            $stmt->execute([
                'package_id' => $packageId,
                'package_role_id' => $packageRoleId,
                'org_role_id' => $orgRoleId,
                'mapped_by' => $mappedBy
            ]);
        }

        return true;
    }

    /**
     * Remove all organization role mappings for a package role
     */
    public function unmapOrgRoles(int $packageRoleId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM package_role_mappings WHERE package_role_id = :package_role_id");
        return $stmt->execute(['package_role_id' => $packageRoleId]);
    }

    /**
     * Get organization roles mapped to a package role
     */
    public function getMappedOrgRoles(int $packageRoleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT or.* FROM org_roles or
             JOIN package_role_mappings prm ON or.id = prm.org_role_id
             WHERE prm.package_role_id = :package_role_id AND or.is_active = 1
             ORDER BY or.name"
        );
        $stmt->execute(['package_role_id' => $packageRoleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all role mappings for a package (for admin UI)
     */
    public function getPackageMappings(int $packageId): array
    {
        $stmt = $this->db->prepare(
            "SELECT pr.id AS package_role_id, pr.role_key, pr.role_name, pr.tier_level,
                    or.id AS org_role_id, or.name AS org_role_name
             FROM package_roles pr
             LEFT JOIN package_role_mappings prm ON pr.id = prm.package_role_id
             LEFT JOIN org_roles or ON prm.org_role_id = or.id AND or.is_active = 1
             WHERE pr.package_id = :package_id
             ORDER BY pr.tier_level, pr.role_name, or.name"
        );
        $stmt->execute(['package_id' => $packageId]);
        
        // Group by package role
        $mappings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $roleId = $row['package_role_id'];
            if (!isset($mappings[$roleId])) {
                $mappings[$roleId] = [
                    'id' => $roleId,
                    'role_key' => $row['role_key'],
                    'role_name' => $row['role_name'],
                    'tier_level' => $row['tier_level'],
                    'org_roles' => []
                ];
            }
            
            if ($row['org_role_id']) {
                $mappings[$roleId]['org_roles'][] = [
                    'id' => $row['org_role_id'],
                    'name' => $row['org_role_name']
                ];
            }
        }
        
        return array_values($mappings);
    }
}
