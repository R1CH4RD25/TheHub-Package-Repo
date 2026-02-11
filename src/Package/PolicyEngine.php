<?php

namespace Hub\Package;

use Hub\User;

/**
 * Policy Engine v0 (Minimal)
 * 
 * Handles role-based access control and permission checks.
 * Provides hooks for scope and rate limiting (to be implemented in Sprint 2).
 * 
 * Version: v0 - Basic RBAC only (Sprint 0)
 * Future: v1 - Advanced scope, masking, rate limits (Sprint 2)
 * 
 * @see PACKAGE_ARCHITECTURE_SPEC.md §12 (Enforcement Pipelines)
 * @see PACKAGE_IMPLEMENTATION_GAMEPLAN.md §3.2 (Sprint 0 Day 2)
 */
class PolicyEngine
{
    /** @var array Role hierarchy (higher = more access) */
    private const ROLE_HIERARCHY = [
        'guest' => 0,
        'standard' => 10,
        'manager' => 20,
        'admin' => 30,
        'super_admin' => 40
    ];

    /** @var array Default permissions by role */
    private const DEFAULT_PERMISSIONS = [
        'guest' => [],
        'standard' => ['*.view'],
        'manager' => ['*.view', '*.create', '*.edit'],
        'admin' => ['*.view', '*.create', '*.edit', '*.delete', '*.manage'],
        'super_admin' => ['*']
    ];

    /**
     * Check if user can access a page
     * 
     * Step 1 in enforcement pipeline (MUST be called before any query/mutation).
     * 
     * @param User $user Current user
     * @param array $pageConfig Page configuration from package JSON:
     *   [
     *     'id' => 'page-id',
     *     'requiredRole' => 'manager',     // Optional: minimum role required
     *     'requiredPermission' => 'students.manage',  // Optional: specific permission
     *   ]
     * 
     * @return bool True if user can access page
     */
    public function canAccessPage(User $user, array $pageConfig): bool
    {
        // Check role requirement
        if (isset($pageConfig['requiredRole'])) {
            if (!$this->hasRole($user, $pageConfig['requiredRole'])) {
                return false;
            }
        }

        // Check permission requirement
        if (isset($pageConfig['requiredPermission'])) {
            if (!$this->check($user, $pageConfig['requiredPermission'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has a specific permission
     * 
     * Supports wildcard matching:
     * - 'students.view' matches 'students.view'
     * - 'students.*' matches 'students.view', 'students.edit', etc.
     * - '*' matches everything (super admin)
     * 
     * @param User $user Current user
     * @param string $permission Permission to check (e.g., 'students.manage')
     * 
     * @return bool True if user has permission
     */
    public function check(User $user, string $permission): bool
    {
        $userRole = $this->getUserRole($user);
        $userPermissions = $this->getUserPermissions($user, $userRole);

        // Check exact match first
        if (in_array($permission, $userPermissions)) {
            return true;
        }

        // Check wildcard permissions
        foreach ($userPermissions as $userPerm) {
            if ($this->matchesWildcard($permission, $userPerm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has a specific role or higher
     * 
     * @param User $user Current user
     * @param string $requiredRole Required role (e.g., 'manager')
     * 
     * @return bool True if user has role or higher
     */
    public function hasRole(User $user, string $requiredRole): bool
    {
        $userRole = $this->getUserRole($user);
        
        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$requiredRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Get scope filters for user (HOOK for Sprint 2)
     * 
     * Returns WHERE clause conditions to apply row-level security.
     * Currently returns empty array (no filtering).
     * 
     * Sprint 2 will implement:
     * - Department-based scoping
     * - User-based scoping (only see own records)
     * - Custom scope rules from package policies{}
     * 
     * @param User $user Current user
     * @param array $scopeConfig Scope configuration from package policies{}
     * 
     * @return array Scope filters (empty for v0):
     *   [
     *     ['column' => 'department_id', 'operator' => '=', 'value' => 123],
     *     ['column' => 'created_by', 'operator' => '=', 'value' => $userId]
     *   ]
     */
    public function getScopeFilters(User $user, array $scopeConfig = []): array
    {
        // v0: No scope filtering (Sprint 0)
        // v1: Implement department, user, custom scopes (Sprint 2)
        return [];
    }

    /**
     * Get field masking rules for user (HOOK for Sprint 2)
     * 
     * Returns field keys that should be masked based on data classification.
     * Currently returns empty array (no masking).
     * 
     * Sprint 2 will implement:
     * - Mask 'secret' fields for non-admin users
     * - Mask 'confidential' fields based on permissions
     * - Redact fields based on data classification
     * 
     * @param User $user Current user
     * @param array $dataClassifications Field key => classification map from PackageValidator
     * 
     * @return array Field keys to mask (empty for v0):
     *   ['password', 'ssn', 'credit_card']
     */
    public function getFieldMasks(User $user, array $dataClassifications = []): array
    {
        // v0: No field masking (Sprint 0)
        // v1: Implement masking based on dataClass (Sprint 2)
        return [];
    }

    /**
     * Check rate limit for user (HOOK for Sprint 2)
     * 
     * Returns whether user has exceeded rate limit for action.
     * Currently always returns false (no rate limiting).
     * 
     * Sprint 2 will implement:
     * - Per-user rate limits
     * - Per-package rate limits
     * - Per-mutation rate limits
     * 
     * @param User $user Current user
     * @param string $action Action identifier (e.g., 'package.com.woodson.student-directory.mutation.resetPassword')
     * @param array $limits Rate limit configuration
     * 
     * @return bool True if rate limit exceeded (always false for v0)
     */
    public function isRateLimited(User $user, string $action, array $limits = []): bool
    {
        // v0: No rate limiting (Sprint 0)
        // v1: Implement rate limits (Sprint 2)
        return false;
    }

    /**
     * Get user's role
     * 
     * @param User $user Current user
     * 
     * @return string User's role (guest, standard, manager, admin, super_admin)
     */
    private function getUserRole(User $user): string
    {
        // Map Hub roles to policy engine roles
        $hubRole = $user->role ?? 'standard';

        $roleMap = [
            'super_admin' => 'super_admin',
            'admin' => 'admin',
            'manager' => 'manager',
            'standard' => 'standard',
            'guest' => 'guest'
        ];

        return $roleMap[$hubRole] ?? 'standard';
    }

    /**
     * Get user's permissions
     * 
     * @param User $user Current user
     * @param string $userRole User's role
     * 
     * @return array Permission strings
     */
    private function getUserPermissions(User $user, string $userRole): array
    {
        // Start with default permissions for role
        $permissions = self::DEFAULT_PERMISSIONS[$userRole] ?? [];

        // Future: Add custom permissions from user_permissions table
        // Future: Add permissions from user groups

        return $permissions;
    }

    /**
     * Check if permission matches wildcard pattern
     * 
     * @param string $permission Permission to check (e.g., 'students.view')
     * @param string $pattern Wildcard pattern (e.g., 'students.*' or '*')
     * 
     * @return bool True if permission matches pattern
     */
    private function matchesWildcard(string $permission, string $pattern): bool
    {
        // Exact match for '*'
        if ($pattern === '*') {
            return true;
        }

        // Convert wildcard to regex
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        
        return (bool) preg_match($regex, $permission);
    }

    /**
     * Get role level for sorting/comparison
     * 
     * @param string $role Role name
     * 
     * @return int Role level (higher = more access)
     */
    public function getRoleLevel(string $role): int
    {
        return self::ROLE_HIERARCHY[$role] ?? 0;
    }

    /**
     * Get all available roles
     * 
     * @return array Role names
     */
    public function getAvailableRoles(): array
    {
        return array_keys(self::ROLE_HIERARCHY);
    }

    /**
     * Get default permissions for a role
     * 
     * @param string $role Role name
     * 
     * @return array Permission strings
     */
    public function getDefaultPermissionsForRole(string $role): array
    {
        return self::DEFAULT_PERMISSIONS[$role] ?? [];
    }
}
