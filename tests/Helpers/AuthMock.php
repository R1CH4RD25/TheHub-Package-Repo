<?php
namespace Tests\Helpers;

use Hub\Auth;

/**
 * Mock Auth class for testing security-sensitive functionality
 * 
 * This helper allows tests to mock Auth methods without requiring
 * actual authentication or database user lookups.
 */
class AuthMock
{
    private static $mockedRole = null;
    private static $mockedPermissions = [];
    private static $originalAuthClass = null;
    
    /**
     * Enable Auth mocking with specific role
     * 
     * @param string $role Role to mock (super_admin, admin, manager, staff, user)
     * @param array $permissions Additional permissions to grant
     */
    public static function mock(string $role = 'admin', array $permissions = []): void
    {
        self::$mockedRole = $role;
        self::$mockedPermissions = $permissions;
        
        // Store session state
        if (!isset($_SESSION['auth_mock_original_role'])) {
            $_SESSION['auth_mock_original_role'] = $_SESSION['role'] ?? null;
        }
        
        // Set mocked role in session
        $_SESSION['role'] = $role;
    }
    
    /**
     * Restore original Auth behavior
     */
    public static function restore(): void
    {
        self::$mockedRole = null;
        self::$mockedPermissions = [];
        
        // Restore session state
        if (isset($_SESSION['auth_mock_original_role'])) {
            if ($_SESSION['auth_mock_original_role'] !== null) {
                $_SESSION['role'] = $_SESSION['auth_mock_original_role'];
            } else {
                unset($_SESSION['role']);
            }
            unset($_SESSION['auth_mock_original_role']);
        }
    }
    
    /**
     * Check if Auth is currently mocked
     */
    public static function isMocked(): bool
    {
        return self::$mockedRole !== null;
    }
    
    /**
     * Get mocked role
     */
    public static function getMockedRole(): ?string
    {
        return self::$mockedRole;
    }
    
    /**
     * Mock hasRole() check
     */
    public static function hasRole(string $role): bool
    {
        if (!self::isMocked()) {
            // Not mocked, use real Auth if available
            if (method_exists(Auth::class, 'hasRole')) {
                return Auth::hasRole($role);
            }
            // Fallback to session role check
            return isset($_SESSION['role']) && $_SESSION['role'] === $role;
        }
        
        // Role hierarchy for mocked checks
        $hierarchy = [
            'super_admin' => 5,
            'admin' => 4,
            'manager' => 3,
            'staff' => 2,
            'user' => 1
        ];
        
        $currentLevel = $hierarchy[self::$mockedRole] ?? 0;
        $requiredLevel = $hierarchy[$role] ?? 0;
        
        return $currentLevel >= $requiredLevel;
    }
    
    /**
     * Mock hasPermission() check
     */
    public static function hasPermission(string $permission): bool
    {
        if (!self::isMocked()) {
            return false;
        }
        
        // Super admin has all permissions
        if (self::$mockedRole === 'super_admin') {
            return true;
        }
        
        return in_array($permission, self::$mockedPermissions);
    }
    
    /**
     * Grant additional permission
     */
    public static function grantPermission(string $permission): void
    {
        if (!in_array($permission, self::$mockedPermissions)) {
            self::$mockedPermissions[] = $permission;
        }
    }
    
    /**
     * Revoke permission
     */
    public static function revokePermission(string $permission): void
    {
        self::$mockedPermissions = array_filter(
            self::$mockedPermissions,
            fn($p) => $p !== $permission
        );
    }
}
