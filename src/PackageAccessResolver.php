<?php

namespace Hub;

/**
 * Package Access Resolver
 *
 * Resolves a user's effective package role and permissions based on:
 * 1. User's system role (super_admin, admin, staff, etc.)
 * 2. The package's policy.globalRoleMapping (system role → package role)
 * 3. The package's policy.roles (viewer, editor, importer with inheritance)
 *
 * Role Inheritance Example:
 *   importer inherits editor inherits viewer
 *   → importer gets ALL permissions from viewer + editor + importer
 *
 * Usage:
 *   $resolver = new PackageAccessResolver();
 *   $role = $resolver->getUserPackageRole($userId, 'district-student-directory');
 *   $perms = $resolver->getUserPermissions($userId, 'district-student-directory');
 *   $canAccess = $resolver->canAccessPage($userId, 'district-student-directory', 'import');
 */
class PackageAccessResolver
{
    private \PDO $db;

    /** Cache: slug → policy array */
    private array $policyCache = [];

    /** Cache: userId:slug → resolved role */
    private array $roleCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get the user's effective package role for a given package.
     *
     * Resolution order:
     * 1. super_admin always gets the highest-tier role
     * 2. globalRoleMapping maps the user's system role to a package role
     * 3. Falls back to policy.defaultRole (usually "viewer")
     *
     * @return string|null The package role key (viewer, editor, importer) or null if no access
     */
    public function getUserPackageRole(int $userId, string $sectionSlug): ?string
    {
        $cacheKey = "{$userId}:{$sectionSlug}";
        if (isset($this->roleCache[$cacheKey])) {
            return $this->roleCache[$cacheKey];
        }

        $policy = $this->getPolicy($sectionSlug);
        if (!$policy) {
            return null;
        }

        // Get user's system role
        $userRole = $this->getUserSystemRole($userId);
        if (!$userRole) {
            return null;
        }

        // super_admin always gets the highest-tier role
        if ($userRole === 'super_admin') {
            $role = $this->getHighestRole($policy['roles'] ?? []);
            $this->roleCache[$cacheKey] = $role;
            return $role;
        }

        // Check globalRoleMapping
        $mapping = $policy['globalRoleMapping'] ?? [];
        if (isset($mapping[$userRole])) {
            $role = $mapping[$userRole];
            // Validate the role exists in policy.roles
            if (isset($policy['roles'][$role])) {
                $this->roleCache[$cacheKey] = $role;
                return $role;
            }
        }

        // Fall back to defaultRole
        $defaultRole = $policy['defaultRole'] ?? null;
        if ($defaultRole && isset($policy['roles'][$defaultRole])) {
            $this->roleCache[$cacheKey] = $defaultRole;
            return $defaultRole;
        }

        $this->roleCache[$cacheKey] = null;
        return null;
    }

    /**
     * Get all permissions (queries + mutations) the user has for a package,
     * resolved through the inheritance chain.
     *
     * @return array{queries: string[], mutations: string[]}
     */
    public function getUserPermissions(int $userId, string $sectionSlug): array
    {
        $roleKey = $this->getUserPackageRole($userId, $sectionSlug);
        if (!$roleKey) {
            return ['queries' => [], 'mutations' => []];
        }

        $policy = $this->getPolicy($sectionSlug);
        return $this->resolveRolePermissions($policy['roles'] ?? [], $roleKey);
    }

    /**
     * Check if a user can access a specific page within a package.
     *
     * A page is accessible if the user's permissions include at least one
     * of the queries or mutations referenced by the page's components.
     * Pages with no query/mutation requirements are accessible to any role.
     */
    public function canAccessPage(int $userId, string $sectionSlug, string $pageKey): bool
    {
        $policy = $this->getPolicy($sectionSlug);
        if (!$policy) {
            return false;
        }

        $userRole = $this->getUserPackageRole($userId, $sectionSlug);
        if (!$userRole) {
            return false;
        }

        // Get the page definition
        $caps = $this->getCapabilities($sectionSlug);
        $pages = $caps['presentation']['pages'] ?? [];
        $page = $pages[$pageKey] ?? null;

        if (!$page) {
            return false;
        }

        // If page has explicit access roles, check those
        $pageAccess = $page['access'] ?? [];
        if (!empty($pageAccess) && !in_array($userRole, $pageAccess)) {
            return false;
        }

        // Get required queries/mutations from the page's components
        $requiredQueries = [];
        $requiredMutations = [];
        foreach ($page['components'] ?? [] as $component) {
            if (!empty($component['dataQuery'])) {
                $requiredQueries[] = $component['dataQuery'];
            }
            if (!empty($component['query'])) {
                $requiredQueries[] = $component['query'];
            }
            if (!empty($component['mutation'])) {
                $requiredMutations[] = $component['mutation'];
            }
            // Also check config.mutation (v3 form components store it nested)
            if (!empty($component['config']['mutation'])) {
                $requiredMutations[] = $component['config']['mutation'];
            }
            // Check row actions for mutations
            foreach ($component['rowActions'] ?? [] as $action) {
                if (!empty($action['mutation'])) {
                    $requiredMutations[] = $action['mutation'];
                }
            }
        }

        // If no specific permissions needed, page is accessible to any role
        if (empty($requiredQueries) && empty($requiredMutations)) {
            return true;
        }

        // Check if user has at least one of the required permissions
        $userPerms = $this->getUserPermissions($userId, $sectionSlug);

        foreach ($requiredQueries as $q) {
            if (in_array($q, $userPerms['queries'])) {
                return true;
            }
        }
        foreach ($requiredMutations as $m) {
            if (in_array($m, $userPerms['mutations'])) {
                return true;
            }
        }

        // User has none of the required query/mutation permissions
        return false;
    }

    /**
     * Get all pages the user can access for a package, with role metadata.
     *
     * @return array Array of page definitions with 'accessible' flag and 'requiredRole'
     */
    public function getAccessiblePages(int $userId, string $sectionSlug): array
    {
        $caps = $this->getCapabilities($sectionSlug);
        $pages = $caps['presentation']['pages'] ?? [];
        $policy = $this->getPolicy($sectionSlug);
        $userRole = $this->getUserPackageRole($userId, $sectionSlug);
        $userPerms = $this->getUserPermissions($userId, $sectionSlug);

        $result = [];
        foreach ($pages as $pageKey => $page) {
            $accessible = $this->canAccessPage($userId, $sectionSlug, $pageKey);

            // Determine what role this page requires (for UI hints)
            $requiredRole = $this->inferPageRole($page, $policy['roles'] ?? []);

            $result[$pageKey] = array_merge($page, [
                'key' => $pageKey,
                'accessible' => $accessible,
                'requiredRole' => $requiredRole,
                'userRole' => $userRole,
            ]);
        }

        return $result;
    }

    /**
     * Check if user has a specific mutation permission.
     */
    public function canMutate(int $userId, string $sectionSlug, string $mutation): bool
    {
        $perms = $this->getUserPermissions($userId, $sectionSlug);
        return in_array($mutation, $perms['mutations']);
    }

    /**
     * Check if user has a specific query permission.
     */
    public function canQuery(int $userId, string $sectionSlug, string $query): bool
    {
        $perms = $this->getUserPermissions($userId, $sectionSlug);
        return in_array($query, $perms['queries']);
    }

    // ─── Private Helpers ────────────────────────────────────────────

    /**
     * Resolve all permissions for a role, walking the inheritance chain.
     *
     * e.g., importer inherits editor inherits viewer
     * → collects importer queries/mutations + editor queries/mutations + viewer queries/mutations
     *
     * @return array{queries: string[], mutations: string[]}
     */
    private function resolveRolePermissions(array $roles, string $roleKey, array $visited = []): array
    {
        if (in_array($roleKey, $visited)) {
            return ['queries' => [], 'mutations' => []]; // Prevent circular inheritance
        }
        $visited[] = $roleKey;

        $role = $roles[$roleKey] ?? null;
        if (!$role) {
            return ['queries' => [], 'mutations' => []];
        }

        $queries = $role['queries'] ?? [];
        $mutations = $role['mutations'] ?? [];

        // Walk the inheritance chain
        if (!empty($role['inherits'])) {
            $inherited = $this->resolveRolePermissions($roles, $role['inherits'], $visited);
            $queries = array_merge($queries, $inherited['queries']);
            $mutations = array_merge($mutations, $inherited['mutations']);
        }

        return [
            'queries' => array_unique(array_values($queries)),
            'mutations' => array_unique(array_values($mutations)),
        ];
    }

    /**
     * Determine the highest-tier role defined in the package (for super_admin).
     */
    private function getHighestRole(array $roles): ?string
    {
        // The last defined role in an ordered array is typically the highest
        // Or we can check the inherits chain — the role that inherits from another
        // and is not inherited by anyone is the highest
        $inherited = [];
        foreach ($roles as $key => $def) {
            if (!empty($def['inherits'])) {
                $inherited[] = $def['inherits'];
            }
        }

        // Roles that are NOT inherited by anything = top-tier
        foreach (array_reverse(array_keys($roles)) as $key) {
            if (!in_array($key, $inherited)) {
                return $key;
            }
        }

        // Fallback: last defined role
        $keys = array_keys($roles);
        return end($keys) ?: null;
    }

    /**
     * Infer the minimum required role for a page based on its component queries/mutations.
     */
    private function inferPageRole(array $page, array $roles): ?string
    {
        $requiredQueries = [];
        $requiredMutations = [];

        foreach ($page['components'] ?? [] as $comp) {
            if (!empty($comp['dataQuery'])) $requiredQueries[] = $comp['dataQuery'];
            if (!empty($comp['query'])) $requiredQueries[] = $comp['query'];
            if (!empty($comp['mutation'])) $requiredMutations[] = $comp['mutation'];
            if (!empty($comp['config']['mutation'])) $requiredMutations[] = $comp['config']['mutation'];
            foreach ($comp['rowActions'] ?? [] as $action) {
                if (!empty($action['mutation'])) $requiredMutations[] = $action['mutation'];
            }
        }

        if (empty($requiredQueries) && empty($requiredMutations)) {
            return null; // No specific role needed
        }

        // Find the lowest-tier role that has at least one of the required permissions
        foreach ($roles as $roleKey => $roleDef) {
            $rolePerms = $this->resolveRolePermissions($roles, $roleKey);
            foreach ($requiredQueries as $q) {
                if (in_array($q, $rolePerms['queries'])) return $roleKey;
            }
            foreach ($requiredMutations as $m) {
                if (in_array($m, $rolePerms['mutations'])) return $roleKey;
            }
        }

        return null;
    }

    /**
     * Get the policy section from capabilities JSON (cached).
     */
    private function getPolicy(string $sectionSlug): ?array
    {
        $caps = $this->getCapabilities($sectionSlug);
        return $caps['policy'] ?? null;
    }

    /**
     * Get the full capabilities JSON for a section (cached).
     */
    private function getCapabilities(string $sectionSlug): ?array
    {
        if (isset($this->policyCache[$sectionSlug])) {
            return $this->policyCache[$sectionSlug];
        }

        $stmt = $this->db->prepare(
            "SELECT capabilities_json FROM sections WHERE slug = :slug AND is_active = 1"
        );
        $stmt->execute(['slug' => $sectionSlug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || !$row['capabilities_json']) {
            $this->policyCache[$sectionSlug] = null;
            return null;
        }

        $caps = json_decode($row['capabilities_json'], true);
        $this->policyCache[$sectionSlug] = $caps;
        return $caps;
    }

    /**
     * Get the user's system role.
     */
    private function getUserSystemRole(int $userId): ?string
    {
        // Use Auth's effective role if available (handles super-admin "view as")
        if (class_exists('\Hub\Auth') && Auth::getCurrentUserId() === $userId) {
            return Auth::getEffectiveRole();
        }

        // Fallback: query directly
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = :id AND is_active = 1");
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['role'] ?? null;
    }

    /**
     * Sync package_roles from capabilities JSON for a section.
     *
     * Call this during installation or to repair missing role data.
     * Populates package_roles and package_role_mappings tables.
     */
    public static function syncRolesFromCapabilities(int $sectionId, int $syncedBy): array
    {
        $db = Database::getInstance();

        // Get capabilities JSON
        $section = $db->fetchOne("SELECT slug, capabilities_json FROM sections WHERE id = ?", [$sectionId]);
        if (!$section || !$section['capabilities_json']) {
            return ['error' => 'No capabilities JSON for section'];
        }

        $caps = json_decode($section['capabilities_json'], true);
        $policy = $caps['policy'] ?? [];
        $roles = $policy['roles'] ?? [];
        $mapping = $policy['globalRoleMapping'] ?? [];

        if (empty($roles)) {
            return ['error' => 'No roles defined in policy'];
        }

        // Clear existing
        $db->execute("DELETE FROM package_role_mappings WHERE package_id = ?", [$sectionId]);
        $db->execute("DELETE FROM package_roles WHERE package_id = ?", [$sectionId]);

        $tierLevel = 1;
        $roleIdMap = [];

        foreach ($roles as $roleKey => $roleDef) {
            $permissions = json_encode([
                'queries' => $roleDef['queries'] ?? [],
                'mutations' => $roleDef['mutations'] ?? [],
                'inherits' => $roleDef['inherits'] ?? null,
            ]);

            $roleId = $db->insert('package_roles', [
                'package_id' => $sectionId,
                'role_key' => $roleKey,
                'role_name' => $roleDef['label'] ?? ucfirst($roleKey),
                'tier_level' => $tierLevel,
                'description' => $roleDef['description'] ?? null,
                'permissions' => $permissions,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $roleIdMap[$roleKey] = $roleId;
            $tierLevel++;
        }

        // Create org_role mappings from globalRoleMapping hints
        $mappingsCreated = 0;
        foreach ($mapping as $globalRoleName => $packageRoleKey) {
            if (!isset($roleIdMap[$packageRoleKey])) continue;

            // Try to match org_role by name
            $orgRole = $db->fetchOne(
                "SELECT id FROM org_roles WHERE LOWER(name) = LOWER(?) AND is_active = 1",
                [$globalRoleName]
            );

            if ($orgRole) {
                try {
                    $db->insert('package_role_mappings', [
                        'package_id' => $sectionId,
                        'package_role_id' => $roleIdMap[$packageRoleKey],
                        'org_role_id' => $orgRole['id'],
                        'mapped_by' => $syncedBy,
                        'mapped_at' => date('Y-m-d H:i:s'),
                    ]);
                    $mappingsCreated++;
                } catch (\Exception $e) {
                    // Skip duplicates
                }
            }
        }

        return [
            'roles_created' => count($roleIdMap),
            'mappings_created' => $mappingsCreated,
            'role_ids' => $roleIdMap,
        ];
    }
}
