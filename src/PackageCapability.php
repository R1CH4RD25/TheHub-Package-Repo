<?php
namespace Hub;

/**
 * Package Capability Helper Class
 * 
 * Manages explicit capability declarations per package.
 * Provides permission checking, dependency validation, and security issue detection.
 */
class PackageCapability
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all capabilities for a package
     * 
     * @param string $packageSlug
     * @return array Array of capability definitions
     */
    public function getPackageCapabilities(string $packageSlug): array
    {
        $stmt = $this->db->prepare("
            SELECT capability_key, capability_label, capability_description,
                   capability_type, default_roles, dependencies, sort_order
            FROM package_capabilities
            WHERE package_slug = ?
            ORDER BY sort_order, capability_key
        ");
        $stmt->execute([$packageSlug]);

        $capabilities = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $capabilities[] = [
                'key' => $row['capability_key'],
                'label' => $row['capability_label'],
                'description' => $row['capability_description'],
                'type' => $row['capability_type'],
                'default_roles' => json_decode($row['default_roles'] ?? '[]', true),
                'dependencies' => json_decode($row['dependencies'] ?? '[]', true)
            ];
        }

        return $capabilities;
    }

    /**
     * Check if user has capability for package
     * 
     * @param int $userId
     * @param string $packageSlug
     * @param string $capability
     * @return bool
     */
    public function userHasCapability(int $userId, string $packageSlug, string $capability): bool
    {
        // Super admins always have all capabilities
        $userStmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $userRole = $userStmt->fetchColumn();
        
        if ($userRole === 'super_admin') {
            return true;
        }

        // Check role-based capability
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM package_role_capabilities prc
            JOIN users u ON u.role = prc.role
            WHERE u.id = ? AND prc.package_slug = ? AND prc.capability_key = ?
        ");
        $stmt->execute([$userId, $packageSlug, $capability]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get roles with specific capability
     * 
     * @param string $packageSlug
     * @param string $capability
     * @return array Array of role objects
     */
    public function getRolesWithCapability(string $packageSlug, string $capability): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT role
            FROM package_role_capabilities
            WHERE package_slug = ? AND capability_key = ?
        ");
        $stmt->execute([$packageSlug, $capability]);

        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'role');
    }

    /**
     * Get all capabilities assigned to a role for a package
     * 
     * @param string $packageSlug
     * @param string $roleName
     * @return array Array of capability keys
     */
    public function getRoleCapabilities(string $packageSlug, string $roleName): array
    {
        $stmt = $this->db->prepare("
            SELECT capability_key
            FROM package_role_capabilities
            WHERE package_slug = ? AND role = ?
        ");
        $stmt->execute([$packageSlug, $roleName]);

        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'capability_key');
    }

    /**
     * Set role capabilities for package (bulk operation)
     * 
     * Replaces all capabilities for given role+package combination.
     * 
     * @param string $packageSlug
     * @param string $roleName
     * @param array $capabilities Array of capability keys
     * @param int $grantedBy User ID who is making the change
     * @throws \Exception on database error
     */
    public function setRoleCapabilities(string $packageSlug, string $roleName, array $capabilities, int $grantedBy): void
    {
        // Start transaction
        $this->db->beginTransaction();

        try {
            // Remove existing capabilities for this role+package
            $stmt = $this->db->prepare("
                DELETE FROM package_role_capabilities
                WHERE package_slug = ? AND role = ?
            ");
            $stmt->execute([$packageSlug, $roleName]);

            // Insert new capabilities
            if (!empty($capabilities)) {
                $stmt = $this->db->prepare("
                    INSERT INTO package_role_capabilities
                    (package_slug, role, capability_key, granted_by)
                    VALUES (?, ?, ?, ?)
                ");

                foreach ($capabilities as $cap) {
                    $stmt->execute([$packageSlug, $roleName, $cap, $grantedBy]);
                }
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Apply smart defaults from manifest
     * 
     * CRITICAL RULE: NEVER overwrites existing assignments on upgrade!
     * Only applies defaults for NEW capabilities or NEW roles.
     * 
     * @param string $packageSlug
     * @param bool $isNewInstall Whether this is initial install (vs upgrade)
     * @return array List of applied defaults for logging
     */
    public function applySmartDefaults(string $packageSlug, bool $isNewInstall = false): array
    {
        $applied = [];
        $capabilities = $this->getPackageCapabilities($packageSlug);

        foreach ($capabilities as $cap) {
            if (empty($cap['default_roles'])) continue;

            foreach ($cap['default_roles'] as $roleName) {
                // Check if already granted (NEVER overwrite existing assignments)
                $check = $this->db->prepare("
                    SELECT COUNT(*) FROM package_role_capabilities
                    WHERE package_slug = ? AND role = ? AND capability_key = ?
                ");
                $check->execute([$packageSlug, $roleName, $cap['key']]);

                if ($check->fetchColumn() == 0) {
                    // Grant capability
                    $insert = $this->db->prepare("
                        INSERT INTO package_role_capabilities
                        (package_slug, role, capability_key, granted_by)
                        VALUES (?, ?, ?, 1)
                    ");
                    $insert->execute([$packageSlug, $roleName, $cap['key']]);

                    $applied[] = [
                        'role' => $roleName,
                        'capability' => $cap['label']
                    ];
                }
            }
        }

        return $applied;
    }

    /**
     * Detect new capabilities added in package upgrade
     * 
     * Returns array of new capabilities for admin review.
     * 
     * @param string $packageSlug
     * @param string $oldVersion Previous version
     * @param string $newVersion New version
     * @return array New capabilities with their default_roles
     */
    public function detectUpgradeCapabilities(string $packageSlug, string $oldVersion, string $newVersion): array
    {
        $stmt = $this->db->prepare("
            SELECT capability_key, capability_label, capability_description,
                   added_in_version, default_roles
            FROM package_capabilities
            WHERE package_slug = ?
            AND (added_in_version = ? OR added_in_version IS NULL)
        ");
        $stmt->execute([$packageSlug, $newVersion]);

        $newCapabilities = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $newCapabilities[] = [
                'key' => $row['capability_key'],
                'label' => $row['capability_label'],
                'description' => $row['capability_description'],
                'default_roles' => json_decode($row['default_roles'] ?? '[]', true)
            ];
        }

        return $newCapabilities;
    }

    /**
     * Middleware-style enforcement: Require capability or throw error
     * 
     * Usage in API endpoints:
     *   PackageCapability::require($userId, 'travel-requests', 'approve');
     * 
     * If user lacks capability, sends 403 JSON response and exits.
     * 
     * @param int $userId
     * @param string $packageSlug
     * @param string $capabilityKey
     */
    public static function require(int $userId, string $packageSlug, string $capabilityKey): void
    {
        $instance = new self();

        if (!$instance->userHasCapability($userId, $packageSlug, $capabilityKey)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Insufficient permissions',
                'required_capability' => $capabilityKey,
                'package' => $packageSlug
            ]);
            exit;
        }
    }

    /**
     * Validate capability dependencies
     * 
     * Returns warnings if role has capability but missing dependencies.
     * Example: approve=true but view_all=false (can approve what you can't see!)
     * 
     * @param string $packageSlug
     * @return array Array of warning objects
     */
    public function validateDependencies(string $packageSlug): array
    {
        $warnings = [];

        // Get all capabilities with dependencies
        $stmt = $this->db->prepare("
            SELECT capability_key, capability_label, dependencies
            FROM package_capabilities
            WHERE package_slug = ? AND dependencies IS NOT NULL
        ");
        $stmt->execute([$packageSlug]);

        while ($cap = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $deps = json_decode($cap['dependencies'] ?? '[]', true);
            if (empty($deps)) continue;

            // Find roles that have this capability
            $rolesStmt = $this->db->prepare("
                SELECT DISTINCT r.id, r.name
                FROM package_role_capabilities prc
                JOIN roles r ON r.id = prc.role_id
                WHERE prc.package_slug = ? AND prc.capability_key = ?
            ");
            $rolesStmt->execute([$packageSlug, $cap['capability_key']]);

            while ($role = $rolesStmt->fetch(\PDO::FETCH_ASSOC)) {
                // Check if role has all required dependencies
                foreach ($deps as $depKey) {
                    $checkDep = $this->db->prepare("
                        SELECT COUNT(*) FROM package_role_capabilities
                        WHERE package_slug = ? AND role_id = ? AND capability_key = ?
                    ");
                    $checkDep->execute([$packageSlug, $role['id'], $depKey]);

                    if ($checkDep->fetchColumn() == 0) {
                        $warnings[] = [
                            'role' => $role['name'],
                            'role_id' => $role['id'],
                            'capability' => $cap['capability_label'],
                            'missing_dependency' => $depKey,
                            'risk' => "Role can {$cap['capability_label']} but cannot $depKey"
                        ];
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * Detect security red flags (orphan capabilities, impossible states)
     * 
     * Returns issues that admin should review:
     * - Orphan capabilities: Exists but zero roles have it
     * - Invisible access: Role has capabilities but no view permission
     * 
     * @param string $packageSlug
     * @return array Array of issue objects with severity levels
     */
    public function detectSecurityIssues(string $packageSlug): array
    {
        $issues = [];

        // Issue 1: Capability exists but zero roles have it
        $orphanCaps = $this->db->prepare("
            SELECT pc.capability_key, pc.capability_label
            FROM package_capabilities pc
            LEFT JOIN package_role_capabilities prc
                ON prc.package_slug = pc.package_slug
                AND prc.capability_key = pc.capability_key
            WHERE pc.package_slug = ?
            AND prc.id IS NULL
        ");
        $orphanCaps->execute([$packageSlug]);

        while ($cap = $orphanCaps->fetch(\PDO::FETCH_ASSOC)) {
            $issues[] = [
                'type' => 'orphan_capability',
                'severity' => 'warning',
                'capability' => $cap['capability_key'],
                'message' => "Capability '{$cap['capability_label']}' has no roles assigned. Nobody can use this feature."
            ];
        }

        // Issue 2: Role has package access but no 'view' capability
        $invisibleAccess = $this->db->prepare("
            SELECT DISTINCT role, COUNT(capability_key) as cap_count
            FROM package_role_capabilities
            WHERE package_slug = ?
            AND NOT EXISTS (
                SELECT 1 FROM package_role_capabilities prc2
                WHERE prc2.package_slug = package_role_capabilities.package_slug
                AND prc2.role = package_role_capabilities.role
                AND prc2.capability_key IN ('view', 'view_own', 'view_all')
            )
            GROUP BY role
        ");
        $invisibleAccess->execute([$packageSlug]);

        while ($roleRow = $invisibleAccess->fetch(\PDO::FETCH_ASSOC)) {
            $issues[] = [
                'type' => 'invisible_access',
                'severity' => 'error',
                'role' => $roleRow['role'],
                'message' => "Role '{$roleRow['role']}' has {$roleRow['cap_count']} capabilities but no view access. Users will see errors."
            ];
        }

        return $issues;
    }
}
