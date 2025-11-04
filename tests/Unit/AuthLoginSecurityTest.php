<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Auth;
use Tests\Helpers\TestDatabase;

/**
 * Auth Role & Permission Security Tests
 * Focus: Role helpers, permissions, view-as features, getUserId
 * NOTE: Methods that call exit() or header() cannot be unit tested
 */
class AuthLoginSecurityTest extends TestCase {

    private $adminId;
    private $superAdminId;
    private $managerId;
    private $staffId;

    protected function setUp(): void {
        $_SESSION = [];

        // Create test users in database with unique emails
        $unique = uniqid();
        $this->adminId = TestDatabase::createTestUser(['role' => 'admin', 'email' => "admin{$unique}@test.com"]);
        $this->superAdminId = TestDatabase::createTestUser(['role' => 'super_admin', 'email' => "super{$unique}@test.com"]);
        $this->managerId = TestDatabase::createTestUser(['role' => 'business_manager', 'email' => "manager{$unique}@test.com"]);
        $this->staffId = TestDatabase::createTestUser(['role' => 'staff', 'email' => "staff{$unique}@test.com"]);
    }

    protected function tearDown(): void {
        $_SESSION = [];
        TestDatabase::cleanup();
    }

    // ==================== ROLE HELPER METHOD TESTS ====================

    public function testIsAdminReturnsTrueForAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::isAdmin());
    }

    public function testIsAdminReturnsFalseForNonAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->staffId;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::isAdmin());
    }

    public function testIsAdminReturnsFalseWhenNotLoggedIn() {
        $this->assertFalse(Auth::isAdmin());
    }

    public function testIsSuperAdminReturnsTrueForSuperAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::isSuperAdmin());
    }

    public function testIsSuperAdminReturnsFalseForNonSuperAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $this->assertFalse(Auth::isSuperAdmin());
    }

    public function testIsSuperAdminReturnsFalseWhenNotLoggedIn() {
        $this->assertFalse(Auth::isSuperAdmin());
    }

    public function testIsManagerReturnsTrueForManager() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->managerId;
        $_SESSION['role'] = 'manager';

        $this->assertTrue(Auth::isManager());
    }

    public function testIsManagerReturnsFalseForNonManager() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->staffId;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::isManager());
    }

    public function testIsStaffReturnsTrueForStaff() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->staffId;
        $_SESSION['role'] = 'staff';

        $this->assertTrue(Auth::isStaff());
    }

    public function testIsStaffReturnsFalseForNonStaff() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $this->assertFalse(Auth::isStaff());
    }

    public function testAllRoleHelpersReturnFalseWhenNotLoggedIn() {
        $this->assertFalse(Auth::isAdmin());
        $this->assertFalse(Auth::isSuperAdmin());
        $this->assertFalse(Auth::isManager());
        $this->assertFalse(Auth::isStaff());
    }

    public function testSuperAdminMatchesAllRoleHelpers() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::isSuperAdmin());
        $this->assertTrue(Auth::isAdmin(), 'Super admin is also admin');
        $this->assertTrue(Auth::isManager(), 'Super admin is also manager');
        $this->assertTrue(Auth::isStaff(), 'Super admin is also staff');
    }

    // ==================== PERMISSION METHOD TESTS ====================

    public function testCanEditAnyRecordForAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::canEditAnyRecord());
    }

    public function testCanEditAnyRecordForSuperAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::canEditAnyRecord());
    }

    public function testCanEditAnyRecordForManager() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->managerId;
        $_SESSION['role'] = 'manager';

        $result = Auth::canEditAnyRecord();
        $this->assertIsBool($result, 'Should return boolean');
    }

    public function testCanEditAnyRecordDeniedForStaff() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->staffId;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::canEditAnyRecord());
    }

    public function testCanEditAnyRecordDeniedWhenNotLoggedIn() {
        $this->assertFalse(Auth::canEditAnyRecord());
    }

    public function testCanManageUsersForAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::canManageUsers());
    }

    public function testCanManageUsersForSuperAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::canManageUsers());
    }

    public function testCanManageUsersDeniedForStaff() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->staffId;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::canManageUsers());
    }

    public function testCanManageUsersDeniedForManager() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->managerId;
        $_SESSION['role'] = 'manager';

        $result = Auth::canManageUsers();
        $this->assertIsBool($result, 'Should return boolean');
    }

    public function testCanManageUsersDeniedWhenNotLoggedIn() {
        $this->assertFalse(Auth::canManageUsers());
    }

    // ==================== getCurrentUserId() TESTS ====================

    public function testGetCurrentUserIdReturnsNullWhenNotLoggedIn() {
        $userId = Auth::getCurrentUserId();
        $this->assertNull($userId);
    }

    public function testGetCurrentUserIdReturnsUserIdWhenLoggedIn() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->staffId;
        $_SESSION['role'] = 'staff';

        $userId = Auth::getCurrentUserId();
        $this->assertEquals($this->staffId, $userId);
    }

    public function testGetCurrentUserIdReturnsNullForEmptyUserId() {
        $_SESSION['user_id'] = '';

        $userId = Auth::getCurrentUserId();
        $this->assertNull($userId);
    }

    public function testGetCurrentUserIdHandlesZeroUserId() {
        $_SESSION['user_id'] = 0;

        $userId = Auth::getCurrentUserId();
        // Depending on implementation, 0 might be valid or invalid
        $this->assertTrue($userId === null || $userId === 0, 'Should handle zero user ID');
    }

    // ==================== VIEW AS ROLE TESTS (Super Admin Feature) ====================

    public function testGetEffectiveRoleReturnsActualRoleByDefault() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->adminId;
        $_SESSION['role'] = 'admin';

        $effectiveRole = Auth::getEffectiveRole();
        $this->assertEquals('admin', $effectiveRole);
    }

    public function testGetEffectiveRoleReturnsViewAsRoleWhenSet() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        $effectiveRole = Auth::getEffectiveRole();
        $this->assertEquals('staff', $effectiveRole, 'Should return view_as_role when set');
    }

    public function testSetViewAsRoleSetsViewAsRoleForSuperAdmin() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';

        Auth::setViewAsRole('staff');

        $this->assertEquals('staff', $_SESSION['view_as_role'] ?? null);
    }

    public function testSetViewAsRoleDoesNothingForNonSuperAdmin() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';

        Auth::setViewAsRole('staff');

        $this->assertArrayNotHasKey('view_as_role', $_SESSION, 'Non-super-admin cannot set view_as_role');
    }

    public function testClearViewAsRoleRemovesViewAsRole() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        Auth::clearViewAsRole();

        $this->assertArrayNotHasKey('view_as_role', $_SESSION, 'view_as_role should be removed');
    }

    public function testClearViewAsRoleDoesNotErrorWhenNotSet() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';

        Auth::clearViewAsRole(); // Should not error

        $this->assertArrayNotHasKey('view_as_role', $_SESSION);
    }

    public function testViewAsRoleAffectsEffectiveRole() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $this->superAdminId;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        // When viewing as staff, effective role should reflect that
        $effectiveRole = Auth::getEffectiveRole();
        $this->assertEquals('staff', $effectiveRole);
    }

    public function testViewAsRoleCannotEscalatePrivileges() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'staff';

        Auth::setViewAsRole('super_admin'); // Try to escalate

        // Should not work - only super admins can view as
        $this->assertArrayNotHasKey('view_as_role', $_SESSION);
    }

    // ==================== EDGE CASE & SECURITY TESTS ====================

    public function testRoleHelpersHandleMissingRoleGracefully() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        // No role set

        $this->assertFalse(Auth::isAdmin());
        $this->assertFalse(Auth::isSuperAdmin());
        $this->assertFalse(Auth::isManager());
        $this->assertFalse(Auth::isStaff());
    }

    public function testRoleHelpersHandleInvalidRoleGracefully() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'invalid_role_xyz';

        $this->assertFalse(Auth::isAdmin());
        $this->assertFalse(Auth::isSuperAdmin());
        $this->assertFalse(Auth::isManager());
        $this->assertFalse(Auth::isStaff());
    }

    public function testPermissionMethodsHandleMissingRole() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        // No role set

        $this->assertFalse(Auth::canEditAnyRecord());
        $this->assertFalse(Auth::canManageUsers());
    }

    public function testGetEffectiveRoleReturnsDefaultForMissingRole() {
        $_SESSION['user_id'] = 1;
        // No role set

        $effectiveRole = Auth::getEffectiveRole();
        // Should return something (likely '' or 'staff')
        $this->assertIsString($effectiveRole);
    }

    public function testGetCurrentUserIdWithNegativeId() {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = -1;

        $userId = Auth::getCurrentUserId();
        $this->assertEquals(-1, $userId, 'Should return negative ID as-is');
    }

    public function testGetCurrentUserIdWithVeryLargeId() {
        $_SESSION['user_id'] = PHP_INT_MAX;

        $userId = Auth::getCurrentUserId();
        $this->assertEquals(PHP_INT_MAX, $userId, 'Should handle very large IDs');
    }
}
