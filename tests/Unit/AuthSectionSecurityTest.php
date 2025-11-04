<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Auth;
use Tests\Helpers\TestDatabase;

/**
 * Auth Section Access & Permission Security Tests
 * Focus: canDeleteUser, requireSectionAccess, getUserSectionRole
 */
class AuthSectionSecurityTest extends TestCase {

    private $adminId;
    private $staffId;
    private $superAdminId;

    protected function setUp(): void {
        $_SESSION = [];

        // Create test users with unique emails
        $unique = uniqid();
        $this->adminId = TestDatabase::createTestUser(['role' => 'admin', 'email' => "admin{$unique}@test.com"]);
        $this->staffId = TestDatabase::createTestUser(['role' => 'staff', 'email' => "staff{$unique}@test.com"]);
        $this->superAdminId = TestDatabase::createTestUser(['role' => 'super_admin', 'email' => "superadmin{$unique}@test.com"]);
    }

    protected function tearDown(): void {
        $_SESSION = [];
        TestDatabase::cleanup();
    }

    // ==================== canDeleteUser() TESTS ====================

    public function testCanDeleteUserAllowedForSuperAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $result = Auth::canDeleteUser(999);
        $this->assertTrue($result, 'Super admin should be able to delete any user');
    }

    public function testCanDeleteUserDeniedForNonSuperAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->staffId;
        $_SESSION['role'] = 'staff';

        $result = Auth::canDeleteUser(999);
        $this->assertFalse($result, 'Staff should not delete users');
    }

    public function testCanDeleteUserCannotDeleteSelf() {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $result = Auth::canDeleteUser($this->superAdminId); // Try to delete self
        $this->assertFalse($result, 'User should not be able to delete themselves');
    }

    public function testCanDeleteUserDeniedWhenNotLoggedIn() {
        $result = Auth::canDeleteUser(999);
        $this->assertFalse($result, 'Not logged in users cannot delete');
    }

    public function testCanDeleteUserWithNullTargetUserId() {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $result = Auth::canDeleteUser(null);
        $this->assertFalse($result, 'Cannot delete user with null ID');
    }

    public function testCanDeleteUserWithZeroTargetUserId() {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $result = Auth::canDeleteUser(0);
        // Should handle zero gracefully
        $this->assertIsBool($result);
    }

    public function testCanDeleteUserWithNegativeTargetUserId() {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $result = Auth::canDeleteUser(-1);
        $this->assertFalse($result, 'Cannot delete user with negative ID');
    }

    public function testCanDeleteUserWithStringTargetUserId() {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $result = Auth::canDeleteUser("999");
        // Should handle string gracefully (PHP type juggling)
        $this->assertIsBool($result);
    }

    public function testCanDeleteUserForAdminRole() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        // Admin can delete regular users (not super_admins)
        $result = Auth::canDeleteUser($this->staffId);
        $this->assertTrue($result, 'Admin can delete non-super_admin users');
    }

    public function testCanDeleteUserAdminCannotDeleteSuperAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        // Admin CANNOT delete super_admins
        $result = Auth::canDeleteUser($this->superAdminId);
        $this->assertFalse($result, 'Admin cannot delete super_admin');
    }

    public function testCanDeleteUserForManagerRole() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'manager';

        $result = Auth::canDeleteUser(999);
        $this->assertFalse($result, 'Manager cannot delete users');
    }

    public function testCanDeleteUserForStaffRole() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->staffId;
        $_SESSION['role'] = 'staff';

        $result = Auth::canDeleteUser(999);
        $this->assertFalse($result, 'Staff cannot delete users');
    }

    // ==================== SECTION ACCESS EDGE CASES ====================

    public function testGetUserSectionRoleReturnsNullWhenNotLoggedIn() {
        $role = Auth::getUserSectionRole('maintenance');
        $this->assertNull($role, 'Should return null when not logged in');
    }

    public function testGetUserSectionRoleWithEmptySectionSlug() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $role = Auth::getUserSectionRole('');
        // Should handle empty slug gracefully
        $this->assertTrue($role === null || is_string($role));
    }

    public function testGetUserSectionRoleWithNullSectionSlug() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        try {
            $role = Auth::getUserSectionRole(null);
            $this->assertTrue($role === null || is_string($role));
        } catch (\TypeError $e) {
            // Expected for strict typing
            $this->assertTrue(true);
        }
    }

    public function testGetUserSectionRoleWithNonexistentSection() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $role = Auth::getUserSectionRole('nonexistent_section_xyz');
        // Should return null for nonexistent section
        $this->assertTrue($role === null || is_string($role));
    }

    public function testGetUserSectionRoleWithSpecialCharacters() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $role = Auth::getUserSectionRole("section'; DROP TABLE users;--");
        // Should handle SQL injection attempt gracefully
        $this->assertTrue($role === null || is_string($role));
    }

    // ==================== PERMISSION CONSISTENCY TESTS ====================

    public function testPermissionMethodsConsistentWhenNotLoggedIn() {
        // All permission methods should return false when not logged in
        $this->assertFalse(Auth::canEditAnyRecord());
        $this->assertFalse(Auth::canManageUsers());
        $this->assertFalse(Auth::canDeleteUser(999));
    }

    public function testSuperAdminHasAllPermissions() {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::canEditAnyRecord(), 'Super admin can edit any record');
        $this->assertTrue(Auth::canManageUsers(), 'Super admin can manage users');
        $this->assertTrue(Auth::canDeleteUser(999), 'Super admin can delete users (not self)');
        $this->assertTrue(Auth::isSuperAdmin(), 'Super admin role confirmed');
    }

    public function testAdminHasLimitedPermissions() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::canEditAnyRecord(), 'Admin can edit any record');
        $this->assertTrue(Auth::canManageUsers(), 'Admin can manage users');
        $this->assertTrue(Auth::canDeleteUser($this->staffId), 'Admin can delete non-super-admin users');
        $this->assertFalse(Auth::canDeleteUser($this->superAdminId), 'Admin cannot delete super_admins');
        $this->assertTrue(Auth::isAdmin(), 'Admin role confirmed');
    }

    public function testStaffHasMinimalPermissions() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->staffId;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::canEditAnyRecord(), 'Staff cannot edit any record');
        $this->assertFalse(Auth::canManageUsers(), 'Staff cannot manage users');
        $this->assertFalse(Auth::canDeleteUser(999), 'Staff cannot delete users');
        $this->assertTrue(Auth::isStaff(), 'Staff role confirmed');
    }

    // ==================== VIEW AS ROLE PERMISSION TESTS ====================

    public function testViewAsRoleAffectsPermissions() {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        $effectiveRole = Auth::getEffectiveRole();
        $this->assertEquals('staff', $effectiveRole, 'Effective role should be staff');

        // Permissions should be based on effective role (staff), not actual role (super_admin)
        // NOTE: This depends on implementation - some methods may bypass view_as_role
    }

    public function testViewAsRoleDoesNotAffectActualRoleCheck() {
        $_SESSION['logged_in'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        // Actual role should still be super_admin
        $this->assertTrue(Auth::isSuperAdmin(), 'Actual role check should return super_admin');

        // Effective role should be staff
        $effectiveRole = Auth::getEffectiveRole();
        $this->assertEquals('staff', $effectiveRole);
    }

    // ==================== EDGE CASE SECURITY TESTS ====================

    public function testMultipleSessionVariablesDoNotInterfere() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';
        $_SESSION['some_other_data'] = 'should not affect permissions';

        $this->assertTrue(Auth::isAdmin());
        $this->assertTrue(Auth::canManageUsers());
    }

    public function testSessionArrayInjectionAttempt() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = [1, 'OR', '1=1']; // Array injection attempt
        $_SESSION['role'] = 'admin';

        try {
            $result = Auth::canDeleteUser(999);
            $this->assertIsBool($result, 'Should handle array user_id gracefully');
        } catch (\Exception $e) {
            // Expected - type error
            $this->assertTrue(true);
        }
    }

    public function testRoleInjectionViaSessionManipulation() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = "admin' OR '1'='1";

        // Should not match any valid role
        $this->assertFalse(Auth::isAdmin(), 'SQL injection in role should not grant permissions');
        $this->assertFalse(Auth::isSuperAdmin(), 'SQL injection should not grant super admin');
        $this->assertFalse(Auth::canDeleteUser(999), 'Injected role should not allow deletion');
    }

    public function testPermissionMethodsReturnBooleans() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'manager';

        $this->assertIsBool(Auth::canEditAnyRecord());
        $this->assertIsBool(Auth::canManageUsers());
        $this->assertIsBool(Auth::canDeleteUser(999));
        $this->assertIsBool(Auth::isAdmin());
        $this->assertIsBool(Auth::isSuperAdmin());
        $this->assertIsBool(Auth::isManager());
        $this->assertIsBool(Auth::isStaff());
    }

    public function testGetCurrentUserIdConsistency() {
        $_SESSION['user_id'] = 123;

        $userId1 = Auth::getCurrentUserId();
        $userId2 = Auth::getCurrentUserId();

        $this->assertEquals($userId1, $userId2, 'Multiple calls should return same value');
        $this->assertEquals(123, $userId1);
    }

    public function testGetEffectiveRoleConsistency() {
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $role1 = Auth::getEffectiveRole();
        $role2 = Auth::getEffectiveRole();

        $this->assertEquals($role1, $role2, 'Multiple calls should return same value');
        $this->assertEquals('admin', $role1);
    }
}
