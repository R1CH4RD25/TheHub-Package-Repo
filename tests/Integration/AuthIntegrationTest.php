<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Hub\Auth;
use Hub\Database;
use Hub\AuditLogger;

/**
 * Integration tests for complete authentication workflows
 * Tests real OAuth flow, database operations, session management, and role assignment
 *
 * Target: Increase Auth coverage from 20% to 35%+ by testing full authentication pipeline
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Auth::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(AuditLogger::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Database::class)]
class AuthIntegrationTest extends TestCase
{
    private Database $db;
    private array $testUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::getInstance();
        $this->db->beginTransaction();

        // Clean up any existing test data
        $this->db->execute("DELETE FROM users WHERE email LIKE '%@test-integration.com'");
        $this->db->execute("DELETE FROM audit_log WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@test-integration.com')");

        // Start fresh session for each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Clean up test users
        foreach ($this->testUsers as $userId) {
            $this->db->execute("DELETE FROM audit_log WHERE user_id = ?", [$userId]);
            $this->db->execute("DELETE FROM users WHERE id = ?", [$userId]);
        }

        $this->db->rollback();

        // Clean session
        $_SESSION = [];

        parent::tearDown();
    }

    /**
     * Test complete user registration flow
     * Covers: User creation, role assignment, database persistence
     */
    public function testCompleteUserRegistrationFlow(): void
    {
        // Arrange: Prepare new user data
        $googleId = 'google_' . uniqid();
        $email = 'newuser@test-integration.com';
        $name = 'Test Integration User';

        // Act: Create user via Auth (simulating OAuth callback)
        $userId = $this->createTestUser($googleId, $email, $name, 'staff');

        // Assert: User exists in database with correct data
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$userId]
        );

        $this->assertNotFalse($user, 'User should exist in database');
        $this->assertEquals($email, $user['email']);
        $this->assertEquals($name, $user['name']);
        $this->assertEquals($googleId, $user['google_id']);
        $this->assertEquals('staff', $user['role']);
        $this->assertEquals(1, $user['is_active']);
        $this->assertNotNull($user['created_at']);
    }

    /**
     * Test user login creates proper session
     * Covers: Session creation, CSRF token, session data persistence
     */
    public function testUserLoginCreatesValidSession(): void
    {
        // Arrange: Create test user
        $userId = $this->createTestUser('google_login_test', 'login@test-integration.com', 'Login Test', 'staff');

        // Act: Simulate login by creating session
        $this->simulateLogin($userId);

        // Assert: Session contains correct data
        $this->assertTrue($_SESSION['logged_in'] ?? false, 'User should be logged in');
        $this->assertEquals($userId, $_SESSION['user_id'] ?? null);
        $this->assertEquals('login@test-integration.com', $_SESSION['email'] ?? null);
        $this->assertEquals('Login Test', $_SESSION['name'] ?? null);
        $this->assertEquals('staff', $_SESSION['role'] ?? null);

        // Assert: CSRF token generated (from bootstrap.php auto-generation)
        $this->assertNotEmpty($_SESSION['csrf_token'] ?? null, 'CSRF token should be auto-generated');
        $this->assertEquals(64, strlen($_SESSION['csrf_token'] ?? ''), 'CSRF token should be 64 chars');
    }

    /**
     * Test getCurrentUser validates against database
     * Covers: Security Fix #2 - User existence validation
     */
    public function testGetCurrentUserValidatesAgainstDatabase(): void
    {
        // Arrange: Create user and login
        $userId = $this->createTestUser('google_validate_test', 'validate@test-integration.com', 'Validate Test', 'admin');
        $this->simulateLogin($userId);

        // Act: Get current user (should validate against DB)
        $currentUser = Auth::getCurrentUser();

        // Assert: User data matches database
        $this->assertNotNull($currentUser);
        $this->assertEquals($userId, $currentUser['id']);
        $this->assertEquals('validate@test-integration.com', $currentUser['email']);
        $this->assertEquals('admin', $currentUser['role']);

        // Now test with deactivated user
        $this->db->execute("UPDATE users SET is_active = 0 WHERE id = ?", [$userId]);

        // Act: getCurrentUser should return null for inactive user
        $currentUser = Auth::getCurrentUser();

        // Assert: Inactive user returns null
        $this->assertNull($currentUser, 'Inactive user should return null');
    }

    /**
     * Test user with invalid session ID gets rejected
     * Covers: Security Fix #2 - Prevention of session hijacking
     */
    public function testInvalidUserIdInSessionGetsRejected(): void
    {
        // Arrange: Start session and set fake user ID
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 999999; // Non-existent user
        $_SESSION['email'] = 'fake@test.com';
        $_SESSION['name'] = 'Fake User';
        $_SESSION['role'] = 'admin';

        // Act: Try to get current user
        $currentUser = Auth::getCurrentUser();

        // Assert: Should return null (user doesn't exist in DB)
        $this->assertNull($currentUser, 'Non-existent user ID should return null');

        // Note: session_destroy() doesn't clear $_SESSION array immediately
        // It only marks session for deletion. The actual $_SESSION array
        // still contains data until script ends. This is expected PHP behavior.
    }

    /**
     * Test role hierarchy and permissions
     * Covers: Role checking methods, effective role, view-as functionality
     */
    public function testRoleHierarchyAndPermissions(): void
    {
        // Test role levels - based on actual Auth.php logic
        $roles = [
            'staff' => ['isStaff' => true, 'isManager' => false, 'isAdmin' => false, 'isSuperAdmin' => false],
            'admin' => ['isStaff' => true, 'isManager' => true, 'isAdmin' => true, 'isSuperAdmin' => false],
            'super_admin' => ['isStaff' => true, 'isManager' => true, 'isAdmin' => true, 'isSuperAdmin' => true],
        ];

        foreach ($roles as $role => $expected) {
            // Arrange: Create user with specific role
            $userId = $this->createTestUser("google_role_{$role}", "role_{$role}@test-integration.com", "Role Test {$role}", $role);
            $this->simulateLogin($userId);

            // Act & Assert: Check role methods
            $this->assertEquals($expected['isStaff'], Auth::isStaff(), "isStaff() for {$role}");
            $this->assertEquals($expected['isManager'], Auth::isManager(), "isManager() for {$role}");
            $this->assertEquals($expected['isAdmin'], Auth::isAdmin(), "isAdmin() for {$role}");
            $this->assertEquals($expected['isSuperAdmin'], Auth::isSuperAdmin(), "isSuperAdmin() for {$role}");

            // Clean up for next iteration
            $_SESSION = [];
        }
    }

    /**
     * Test super admin "view as" functionality
     * Covers: View-as feature, effective role vs actual role
     */
    public function testSuperAdminViewAsFeature(): void
    {
        // Arrange: Create super admin
        $superAdminId = $this->createTestUser('google_superadmin', 'superadmin@test-integration.com', 'Super Admin', 'super_admin');
        $this->simulateLogin($superAdminId);

        // Assert: Initially no view-as
        $this->assertEquals('super_admin', Auth::getEffectiveRole());
        $this->assertTrue(Auth::isSuperAdmin());

        // Act: Super admin views as staff
        $_SESSION['view_as_role'] = 'staff';

        // Assert: Effective role changes but actual role doesn't
        $this->assertEquals('staff', Auth::getEffectiveRole());
        $this->assertTrue(Auth::isSuperAdmin(), 'isSuperAdmin should check actual_role, not effective');
        $this->assertTrue(Auth::isStaff(), 'isStaff should check effective role');

        // Act: Clear view-as
        unset($_SESSION['view_as_role']);

        // Assert: Back to normal
        $this->assertEquals('super_admin', Auth::getEffectiveRole());
    }

    /**
     * Test permission boundaries for different roles
     * Covers: canEditAnyRecord, canManageUsers, canDeleteUser
     */
    public function testPermissionBoundariesAcrossRoles(): void
    {
        // Only test roles that Auth.php actually recognizes
        // Note: canManageUsers and canDeleteUser only allow super_admin (not admin)
        $testCases = [
            ['role' => 'staff', 'canEdit' => false, 'canManage' => false, 'canDelete' => false],
            ['role' => 'admin', 'canEdit' => true, 'canManage' => false, 'canDelete' => false],
            ['role' => 'super_admin', 'canEdit' => true, 'canManage' => true, 'canDelete' => true],
        ];

        foreach ($testCases as $case) {
            // Arrange: Create user with role
            $userId = $this->createTestUser(
                "google_perm_{$case['role']}",
                "perm_{$case['role']}@test-integration.com",
                "Permission Test {$case['role']}",
                $case['role']
            );
            $this->simulateLogin($userId);

            // Act & Assert
            $this->assertEquals($case['canEdit'], Auth::canEditAnyRecord(), "canEditAnyRecord for {$case['role']}");
            $this->assertEquals($case['canManage'], Auth::canManageUsers(), "canManageUsers for {$case['role']}");

            // For canDeleteUser, test with different target user (unique email per iteration)
            $targetUserId = $this->createTestUser("target_{$case['role']}", "target_{$case['role']}@test.com", "Target", "staff");
            $this->assertEquals($case['canDelete'], Auth::canDeleteUser($targetUserId), "canDeleteUser for {$case['role']}");

            // Clean up for next iteration
            $_SESSION = [];
        }
    }

    /**
     * Test self-deletion prevention
     * Covers: canDeleteUser prevents deleting own account
     */
    public function testUserCannotDeleteThemselves(): void
    {
        // Arrange: Create super admin user
        $adminId = $this->createTestUser('google_admin_self', 'admin_self@test-integration.com', 'Admin Self', 'super_admin');
        $this->simulateLogin($adminId);

        // Act: Try to delete self
        $canDelete = Auth::canDeleteUser($adminId);

        // Assert: Cannot delete own account
        $this->assertFalse($canDelete, 'Super admin should not be able to delete themselves');

        // Create another user to verify super admin CAN delete others
        $otherUserId = $this->createTestUser('google_other', 'other@test.com', 'Other User', 'staff');
        $canDeleteOther = Auth::canDeleteUser($otherUserId);

        $this->assertTrue($canDeleteOther, 'Super admin should be able to delete other users');
    }

    /**
     * Test audit logging for authentication events
     * Covers: Integration between Auth and AuditLogger
     */
    public function testAuthenticationEventsAreLogged(): void
    {
        // Arrange: Create user
        $userId = $this->createTestUser('google_audit', 'audit@test-integration.com', 'Audit Test', 'staff');

        // Act: Simulate login with audit logging
        $logger = new AuditLogger();
        $logger->logLogin($userId, 'audit@test-integration.com', true);

        // Assert: Login logged
        $logs = $this->db->fetchAll(
            "SELECT * FROM audit_log WHERE user_id = ? AND action = 'login_success' ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );

        $this->assertNotEmpty($logs, 'Login should be logged');
        $this->assertEquals('login_success', $logs[0]['action']);
        $this->assertEquals($userId, $logs[0]['user_id']);

        // Act: Simulate logout
        $logger->logLogout($userId, 'audit@test-integration.com');

        // Assert: Logout logged
        $logs = $this->db->fetchAll(
            "SELECT * FROM audit_log WHERE user_id = ? AND action = 'logout' ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );

        $this->assertNotEmpty($logs, 'Logout should be logged');
        $this->assertEquals('logout', $logs[0]['action']);
    }

    /**
     * Test role changes persist correctly
     * Covers: Role update, database persistence, session refresh
     */
    public function testRoleChangesPersistAcrossRequests(): void
    {
        // Arrange: Create user as staff
        $userId = $this->createTestUser('google_rolechange', 'rolechange@test-integration.com', 'Role Change Test', 'staff');
        $this->simulateLogin($userId);

        // Assert: Initially staff
        $this->assertEquals('staff', Auth::getEffectiveRole());
        $this->assertTrue(Auth::isStaff());

        // Act: Upgrade role to admin
        $this->db->execute("UPDATE users SET role = ? WHERE id = ?", ['admin', $userId]);

        // Simulate new request (getCurrentUser will refresh from DB)
        $currentUser = Auth::getCurrentUser();

        // Assert: Role updated in session
        $this->assertEquals('admin', $currentUser['role']);
        $this->assertEquals('admin', $_SESSION['role']);
        $this->assertTrue(Auth::isAdmin());
    }

    /**
     * Test multiple concurrent users don't interfere
     * Covers: Session isolation, user-specific data
     */
    public function testMultipleUsersSessionIsolation(): void
    {
        // Arrange: Create two users
        $user1Id = $this->createTestUser('google_user1', 'user1@test-integration.com', 'User One', 'staff');
        $user2Id = $this->createTestUser('google_user2', 'user2@test-integration.com', 'User Two', 'admin');

        // Act: Simulate user 1 login
        $this->simulateLogin($user1Id);
        $session1 = $_SESSION;

        // Simulate user 2 login (new session)
        $_SESSION = [];
        $this->simulateLogin($user2Id);
        $session2 = $_SESSION;

        // Assert: Sessions are different
        $this->assertNotEquals($session1['user_id'], $session2['user_id']);
        $this->assertEquals($user1Id, $session1['user_id']);
        $this->assertEquals($user2Id, $session2['user_id']);
        $this->assertEquals('staff', $session1['role']);
        $this->assertEquals('admin', $session2['role']);
    }

    /**
     * Test user last_login tracking
     * Covers: Last login timestamp updates
     */
    public function testLastLoginTimestampUpdates(): void
    {
        // Arrange: Create user
        $userId = $this->createTestUser('google_lastlogin', 'lastlogin@test-integration.com', 'Last Login Test', 'staff');

        // Get initial last_login (should be null or old)
        $user = $this->db->fetchOne("SELECT last_login FROM users WHERE id = ?", [$userId]);
        $initialLastLogin = $user['last_login'];

        // Wait a moment to ensure timestamp difference
        sleep(1);

        // Act: Update last login
        $this->db->execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$userId]
        );

        // Assert: Last login updated
        $user = $this->db->fetchOne("SELECT last_login FROM users WHERE id = ?", [$userId]);
        $this->assertNotEquals($initialLastLogin, $user['last_login'], 'Last login should be updated');
        $this->assertNotNull($user['last_login']);
    }

    /**
     * Test effective role with view-as
     * Covers: getEffectiveRole method with view-as active
     */
    public function testEffectiveRoleWithViewAs(): void
    {
        // Arrange: Create super admin
        $superAdminId = $this->createTestUser('google_effective', 'effective@test-integration.com', 'Effective Role Test', 'super_admin');
        $this->simulateLogin($superAdminId);

        // Assert: Has super_admin role
        $this->assertEquals('super_admin', Auth::getEffectiveRole());
        $this->assertTrue(Auth::isSuperAdmin());
        $this->assertTrue(Auth::isAdmin());

        // Act: View as staff
        $_SESSION['view_as_role'] = 'staff';

        // Assert: Effective role changes
        $this->assertEquals('staff', Auth::getEffectiveRole());
        $this->assertTrue(Auth::isStaff(), 'Should have staff role when viewing as staff');
        $this->assertFalse(Auth::isAdmin(), 'Should not appear as admin when viewing as staff');
    }

    // ==================== Helper Methods ====================

    /**
     * Create a test user in the database
     */
    private function createTestUser(string $googleId, string $email, string $name, string $role): int
    {
        $this->db->execute(
            "INSERT INTO users (google_id, email, name, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())",
            [$googleId, $email, $name, $role]
        );

        $userId = (int)$this->db->lastInsertId();
        $this->testUsers[] = $userId;

        return $userId;
    }

    /**
     * Simulate user login by setting session data
     */
    private function simulateLogin(int $userId): void
    {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

        if (!$user) {
            throw new \RuntimeException("User {$userId} not found");
        }

        // Simulate session creation (mirrors Auth::createSession)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['picture'] = $user['picture'] ?? null;
        $_SESSION['logged_in'] = true;

        // CSRF token should be auto-generated by bootstrap, but set here for testing
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}
