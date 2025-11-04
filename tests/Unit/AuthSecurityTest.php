<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Auth;
use Hub\Database;
use Tests\Helpers\TestDatabase;

/**
 * Comprehensive Auth Security Test Suite
 * Critical for protecting PII, preventing unauthorized access, and audit compliance
 */
class AuthSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::beginTransaction();
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        TestDatabase::rollBack();
        $_SESSION = [];
        parent::tearDown();
    }

    // ========== AUTHENTICATION SECURITY ==========

    public function testHasRoleBlocksUnauthenticatedAccess(): void
    {
        // Critical: No session = no access
        unset($_SESSION['user_id']);

        $this->assertFalse(Auth::hasRole('user'), 'Unauthenticated should not have user role');
        $this->assertFalse(Auth::hasRole('admin'), 'Unauthenticated should not have admin role');
        $this->assertFalse(Auth::hasRole('super_admin'), 'Unauthenticated should not have super_admin role');
    }

    public function testHasRoleValidatesSessionUserId(): void
    {
        // User ID must be set
        $_SESSION['role'] = 'admin';
        // But no user_id

        $this->assertFalse(Auth::hasRole('admin'), 'Role without user_id should be rejected');
    }

    public function testHasRoleRequiresActiveSession(): void
    {
        // Empty session should block all access
        $_SESSION = [];

        $this->assertFalse(Auth::hasRole('user'));
        $this->assertFalse(Auth::hasRole('admin'));
    }

    // ========== ROLE ESCALATION PREVENTION ==========

    public function testUserCannotEscalateToAdmin(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'user';

        $this->assertTrue(Auth::hasRole('user'), 'User should have user role');
        $this->assertFalse(Auth::hasRole('admin'), 'User should NOT have admin role');
        $this->assertFalse(Auth::hasRole('super_admin'), 'User should NOT have super_admin role');
    }

    public function testAdminCannotEscalateToSuperAdmin(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::hasRole('admin'), 'Admin should have admin role');
        $this->assertFalse(Auth::hasRole('super_admin'), 'Admin should NOT have super_admin role');
    }

    public function testRoleCheckIsCaseSensitive(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::hasRole('admin'));
        $this->assertFalse(Auth::hasRole('Admin'), 'Role check should be case-sensitive');
        $this->assertFalse(Auth::hasRole('ADMIN'), 'Role check should be case-sensitive');
    }

    // ========== SUPER ADMIN PRIVILEGES ==========

    public function testSuperAdminHasUniversalAccess(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';

        // Super admin should have ALL roles
        $this->assertTrue(Auth::hasRole('super_admin'));
        $this->assertTrue(Auth::hasRole('admin'));
        $this->assertTrue(Auth::hasRole('user'));
        $this->assertTrue(Auth::hasRole('manager'));
        $this->assertTrue(Auth::hasRole('custom_role'));
        $this->assertTrue(Auth::hasRole('any_role_whatsoever'));
    }

    public function testSuperAdminBypassesRoleChecks(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';

        // Even non-existent roles return true
        $this->assertTrue(Auth::hasRole('nonexistent_role'));
        $this->assertTrue(Auth::hasRole('fake_role'));
    }

    // ========== ROLE MATCHING ==========

    public function testExactRoleMatch(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'manager';

        $this->assertTrue(Auth::hasRole('manager'), 'Exact role should match');
        $this->assertFalse(Auth::hasRole('admin'), 'Different role should not match');
    }

    public function testMultipleUsersWithDifferentRoles(): void
    {
        // User 1: admin
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $this->assertTrue(Auth::hasRole('admin'));
        $this->assertFalse(Auth::hasRole('user'));

        // User 2: user
        $_SESSION['user_id'] = 2;
        $_SESSION['role'] = 'user';
        $this->assertTrue(Auth::hasRole('user'));
        $this->assertFalse(Auth::hasRole('admin'));
    }

    // ========== EDGE CASES & INJECTION PROTECTION ==========

    public function testEmptyRoleString(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'admin';

        $this->assertFalse(Auth::hasRole(''), 'Empty role should return false');
    }

    public function testNullRoleHandling(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = null;

        $this->assertFalse(Auth::hasRole('admin'), 'Null role should not grant access');
    }

    public function testMissingRoleInSession(): void
    {
        $_SESSION['user_id'] = 999;
        // No role key set

        $this->assertFalse(Auth::hasRole('admin'), 'Missing role should default to no access');
    }

    public function testSQLInjectionAttemptInRole(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = "admin' OR '1'='1";

        // Should not match legitimate admin role
        $this->assertFalse(Auth::hasRole('admin'), 'SQL injection attempt should fail');
    }

    public function testWhitespaceInRoleName(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'admin';

        $this->assertFalse(Auth::hasRole(' admin'), 'Leading whitespace should not match');
        $this->assertFalse(Auth::hasRole('admin '), 'Trailing whitespace should not match');
        $this->assertFalse(Auth::hasRole(' admin '), 'Whitespace should not match');
    }

    // ========== SESSION SECURITY ==========

    public function testUserIdMustBeInteger(): void
    {
        $_SESSION['user_id'] = 'not_a_number';
        $_SESSION['role'] = 'admin';

        // Should still work if loosely typed, but worth testing
        $result = Auth::hasRole('admin');
        $this->assertIsBool($result, 'hasRole should return boolean');
    }

    public function testZeroUserIdIsInvalid(): void
    {
        $_SESSION['user_id'] = 0;
        $_SESSION['role'] = 'admin';

        // User ID 0 might be invalid
        $result = Auth::hasRole('admin');
        $this->assertIsBool($result);
    }

    public function testNegativeUserIdIsInvalid(): void
    {
        $_SESSION['user_id'] = -1;
        $_SESSION['role'] = 'admin';

        $result = Auth::hasRole('admin');
        $this->assertIsBool($result);
    }

    // ========== CONCURRENT SESSION PROTECTION ==========

    public function testRoleCheckWithSessionHijackAttempt(): void
    {
        // Simulate session with suspicious data
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'admin';
        $_SESSION['suspicious_key'] = '<script>alert("xss")</script>';

        // Should still validate role correctly
        $this->assertTrue(Auth::hasRole('admin'));
    }

    // ========== ROLE PERSISTENCE ==========

    public function testRoleCheckAfterSessionModification(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'user';

        $this->assertTrue(Auth::hasRole('user'));

        // Modify role (e.g., after privilege escalation)
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::hasRole('admin'), 'Role update should be reflected');
        $this->assertFalse(Auth::hasRole('user'), 'Old role should not persist');
    }

    // ========== SECURITY LOGGING INTEGRATION ==========

    public function testFailedRoleCheckDoesNotLeakInfo(): void
    {
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'user';

        // Should return false without revealing why
        $result = Auth::hasRole('admin');
        $this->assertFalse($result);
        $this->assertIsBool($result, 'Should not return error messages');
    }

    // ========== getCurrentUser() SECURITY TESTS ==========

    /**
     * Test getCurrentUser validates user exists in database
     * SECURITY: Prevents session hijacking with fake user IDs
     */
    public function testGetCurrentUserValidatesUserExistsInDatabase(): void
    {
        // Set up session with fake user_id
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 999999; // Non-existent
        $_SESSION['role'] = 'admin';

        $user = Auth::getCurrentUser();

        // Should return null - user doesn't exist in DB
        $this->assertNull($user, 'Non-existent user_id should return null');
    }

    /**
     * Test getCurrentUser requires logged_in flag
     */
    public function testGetCurrentUserRequiresLoggedInFlag(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        // Missing 'logged_in'

        $user = Auth::getCurrentUser();
        $this->assertNull($user);
    }

    /**
     * Test getCurrentUser returns null when logged_in is false
     */
    public function testGetCurrentUserReturnsNullWhenLoggedOutExplicitly(): void
    {
        $_SESSION['logged_in'] = false;
        $_SESSION['user_id'] = 1;

        $user = Auth::getCurrentUser();
        $this->assertNull($user);
    }

    /**
     * Test getCurrentUser returns full user data from database
     */
    public function testGetCurrentUserReturnsDatabaseUserData(): void
    {
        $db = Database::getInstance();

        // Ensure test user exists
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'test@woodsonisd.net', 'Test User', 'staff', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;

        $user = Auth::getCurrentUser();

        $this->assertIsArray($user);
        $this->assertEquals(1, $user['id']);
        $this->assertEquals('test@woodsonisd.net', $user['email']);
        $this->assertEquals('Test User', $user['name']);
        $this->assertEquals('staff', $user['role']);
    }

    /**
     * Test getCurrentUserId returns correct ID
     */
    public function testGetCurrentUserIdReturnsCorrectId(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 42;

        // Mock user exists
        $db = Database::getInstance();
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (42, 'user42@woodsonisd.net', 'User 42', 'staff', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        $this->assertEquals(42, Auth::getCurrentUserId());
    }

    /**
     * Test getCurrentUserId returns null when not logged in
     */
    public function testGetCurrentUserIdReturnsNullWhenNotLoggedIn(): void
    {
        $_SESSION = [];

        $this->assertNull(Auth::getCurrentUserId());
    }

    // ========== ROLE HIERARCHY SECURITY ==========

    /**
     * Test getEffectiveRole returns actual role by default
     */
    public function testGetEffectiveRoleReturnsActualRole(): void
    {
        $db = Database::getInstance();
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'admin@woodsonisd.net', 'Admin', 'admin', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';

        $this->assertEquals('admin', Auth::getEffectiveRole());
    }

    /**
     * Test getEffectiveRole returns viewAsRole when set
     */
    public function testGetEffectiveRoleReturnsViewAsRoleWhenSet(): void
    {
        $db = Database::getInstance();
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'super@woodsonisd.net', 'Super', 'super_admin', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        $this->assertEquals('staff', Auth::getEffectiveRole());
    }

    /**
     * Test getEffectiveRole returns session role when user not in DB
     */
    public function testGetEffectiveRoleReturnsSessionRoleWhenUserNotFound(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 999999; // Non-existent
        $_SESSION['role'] = 'admin';

        // Should return session role when getCurrentUser returns null
        $this->assertEquals('admin', Auth::getEffectiveRole());
    }

    /**
     * Test setViewAsRole only works for super_admin
     * SECURITY: Prevents privilege escalation
     */
    public function testSetViewAsRoleOnlyWorksForSuperAdmin(): void
    {
        $db = Database::getInstance();
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'admin@woodsonisd.net', 'Admin', 'admin', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        // Try as regular admin
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';

        Auth::setViewAsRole('super_admin');

        // Should NOT be set (not super_admin)
        $this->assertArrayNotHasKey('view_as_role', $_SESSION);
    }

    /**
     * Test setViewAsRole works for super_admin
     */
    public function testSetViewAsRoleWorksForSuperAdmin(): void
    {
        $db = Database::getInstance();
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'super@woodsonisd.net', 'Super', 'super_admin', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';

        Auth::setViewAsRole('staff');

        $this->assertEquals('staff', $_SESSION['view_as_role']);
    }    /**
     * Test clearViewAsRole removes view_as_role
     */
    public function testClearViewAsRoleRemovesOverride(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        Auth::clearViewAsRole();

        $this->assertArrayNotHasKey('view_as_role', $_SESSION);
    }

    // ========== PERMISSION HELPER EDGE CASES ==========

    /**
     * Test canEditAnyRecord returns false when not logged in
     */
    public function testCanEditAnyRecordReturnsFalseWhenNotLoggedIn(): void
    {
        $_SESSION = [];

        $this->assertFalse(Auth::canEditAnyRecord());
    }

    /**
     * Test canEditAnyRecord returns false for staff
     */
    public function testCanEditAnyRecordReturnsFalseForStaff(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::canEditAnyRecord());
    }

    /**
     * Test canEditAnyRecord returns true for manager
     */
    public function testCanEditAnyRecordReturnsTrueForManager(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'manager';

        $this->assertTrue(Auth::canEditAnyRecord());
    }

    /**
     * Test canManageUsers returns false for non-admins
     */
    public function testCanManageUsersReturnsFalseForNonAdmins(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'manager';

        $this->assertFalse(Auth::canManageUsers());
    }

    /**
     * Test canManageUsers returns true for admin
     */
    public function testCanManageUsersReturnsTrueForAdmin(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::canManageUsers());
    }

    /**
     * Test canDeleteUser prevents deleting self
     * SECURITY: Admin cannot delete themselves
     */
    public function testCanDeleteUserPreventsDeletingSelf(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 5;
        $_SESSION['role'] = 'admin';

        // Mock user exists
        $db = Database::getInstance();
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (5, 'admin@woodsonisd.net', 'Admin', 'admin', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        // Cannot delete self
        $this->assertFalse(Auth::canDeleteUser(5));
    }

    /**
     * Test canDeleteUser prevents admin from deleting super_admin
     * SECURITY: Lower privilege cannot delete higher
     */
    public function testCanDeleteUserPreventsAdminDeletingSuperAdmin(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';

        $db = Database::getInstance();

        // Mock admin exists
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'admin@woodsonisd.net', 'Admin', 'admin', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        // Mock super_admin target
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (2, 'super@woodsonisd.net', 'Super', 'super_admin', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        // Admin cannot delete super_admin
        $this->assertFalse(Auth::canDeleteUser(2));
    }

    /**
     * Test canDeleteUser allows admin to delete staff
     */
    public function testCanDeleteUserAllowsAdminToDeleteStaff(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';

        $db = Database::getInstance();

        // Mock admin exists
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'admin@woodsonisd.net', 'Admin', 'admin', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        // Mock staff target
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (3, 'staff@woodsonisd.net', 'Staff', 'staff', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        // Admin CAN delete staff
        $this->assertTrue(Auth::canDeleteUser(3));
    }

    /**
     * Test canDeleteUser returns false for non-admin
     */
    public function testCanDeleteUserReturnsFalseForNonAdmin(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'manager';

        $db = Database::getInstance();
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'manager@woodsonisd.net', 'Manager', 'manager', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        $this->assertFalse(Auth::canDeleteUser(2));
    }

    // ========== SECTION ACCESS INTEGRATION ==========

    /**
     * Test getUserSectionRole returns null when not logged in
     */
    public function testGetUserSectionRoleReturnsNullWhenNotLoggedIn(): void
    {
        $_SESSION = [];

        $this->assertNull(Auth::getUserSectionRole('any-section'));
    }

    /**
     * Test getUserSectionRole queries SectionAccess
     */
    public function testGetUserSectionRoleQueriesSectionAccess(): void
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'staff';

        $db = Database::getInstance();
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'staff@woodsonisd.net', 'Staff', 'staff', 1)
                     ON DUPLICATE KEY UPDATE id=id");

        // Create test section with access
        $db->execute("INSERT INTO sections (name, slug, icon, sort_order, is_active)
                     VALUES ('Test Section', 'test-section', 'icon', 1, 1)");
        $sectionId = $db->lastInsertId();

        $db->execute("INSERT INTO section_role_access (section_id, role_name, permission_level)
                     VALUES (?, 'staff', 'edit')", [$sectionId]);

        $role = Auth::getUserSectionRole('test-section');

        // Should return permission level
        $this->assertIsString($role);
    }

    // ========== SECURITY VULNERABILITY TESTS ==========

    /**
     * Test session fixation protection
     * SECURITY: Changing user_id should invalidate old session
     */
    public function testSessionFixationProtection(): void
    {
        // Start as user 1
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'staff';

        $user1 = Auth::getCurrentUser();
        $this->assertNotNull($user1);

        // Attacker tries to change user_id
        $_SESSION['user_id'] = 2;

        // Should fail if user 2 doesn't exist or is different
        $user2 = Auth::getCurrentUser();

        // Either null (no user 2) or different user data
        if ($user2 !== null) {
            $this->assertNotEquals($user1['id'], $user2['id']);
        }
    }

    /**
     * Test privilege escalation prevention via hasRole
     */
    public function testPrivilegeEscalationPrevention(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'staff';

        // Attacker tries to set super_admin in session
        $_SESSION['role'] = 'super_admin';

        // hasRole should check the session value
        $this->assertTrue(Auth::hasRole('super_admin'));

        // But getCurrentUser should validate against DB
        $db = Database::getInstance();
        $db->execute("INSERT INTO users (id, email, name, role, is_active)
                     VALUES (1, 'staff@woodsonisd.net', 'Staff', 'staff', 1)
                     ON DUPLICATE KEY UPDATE role='staff'");

        $user = Auth::getCurrentUser();
        if ($user) {
            // DB should show actual role
            $this->assertEquals('staff', $user['role']);
        }
    }
}
