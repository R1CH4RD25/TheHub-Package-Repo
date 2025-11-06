<?php
/**
 * Comprehensive Auth Test Suite
 * Target: Boost Auth coverage from 8.54% to 70%+
 *
 * Priority Methods to Test:
 * 1. Google OAuth flow (getAuthUrl, handleCallback)
 * 2. Domain lock validation
 * 3. Role checks (requireRole, isAdmin, isSuperAdmin, etc.)
 * 4. Session management (requireLogin, getCurrentUser, getEffectiveRole)
 * 5. Permission checks (canEditAnyRecord, canManageUsers, canDeleteUser)
 * 6. Section access (requireSectionAccess, getUserSectionRole)
 * 7. View-as feature (setViewAsRole, clearViewAsRole, getEffectiveRole)
 */

namespace Tests\Unit;

use Hub\Auth;
use Hub\Database;
use Hub\OAuthClient;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(Auth::class)]
class AuthComprehensiveTest extends TestCase
{
    private static Database $db;
    private array $testUsers = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$db = Database::getInstance();

        // Create tables
        self::$db->execute("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                google_id VARCHAR(255) UNIQUE NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                role ENUM('staff', 'manager', 'admin', 'super_admin') DEFAULT 'staff',
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                INDEX idx_email (email),
                INDEX idx_google_id (google_id)
            ) ENGINE=InnoDB
        ");

        self::$db->execute("
            CREATE TABLE IF NOT EXISTS user_global_roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                role VARCHAR(50) NOT NULL,
                granted_by INT NULL,
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_role (user_id, role),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");

        self::$db->execute("
            CREATE TABLE IF NOT EXISTS section_role_access (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                section_slug VARCHAR(100) NOT NULL,
                role ENUM('viewer', 'editor', 'admin') NOT NULL,
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_section (user_id, section_slug),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }

    public static function tearDownAfterClass(): void
    {
        self::$db->execute("SET FOREIGN_KEY_CHECKS = 0");
        self::$db->execute("DROP TABLE IF EXISTS section_role_access");
        self::$db->execute("DROP TABLE IF EXISTS user_global_roles");
        self::$db->execute("DROP TABLE IF EXISTS users");
        self::$db->execute("SET FOREIGN_KEY_CHECKS = 1");

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Clean data
        self::$db->execute("DELETE FROM section_role_access");
        self::$db->execute("DELETE FROM user_global_roles");
        self::$db->execute("DELETE FROM users");

        // Reset session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        $_ENV['ALLOWED_DOMAINS'] = 'woodsonisd.net';
        $_ENV['REQUIRE_DOMAIN_MATCH'] = 'false';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    // ========== OAuth Flow Tests ==========

    public function testGetAuthUrlIncludesDomainHint(): void
    {
        $_ENV['ALLOWED_DOMAINS'] = 'woodsonisd.net,example.com';
        $_ENV['GOOGLE_CLIENT_ID'] = 'test_client_id';
        $_ENV['GOOGLE_REDIRECT_URI'] = 'https://test.com/callback';

        $auth = new Auth();
        $url = $auth->getAuthUrl();

        $this->assertStringContainsString('hd=woodsonisd.net', $url);
        $this->assertStringContainsString('client_id=test_client_id', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('scope=', $url);
    }

    public function testGetAuthUrlWithoutDomainConfiguration(): void
    {
        $_ENV['ALLOWED_DOMAINS'] = '';
        $_ENV['GOOGLE_CLIENT_ID'] = 'test_client_id';

        $auth = new Auth();
        $url = $auth->getAuthUrl();

        $this->assertStringNotContainsString('hd=', $url);
        $this->assertStringContainsString('client_id=test_client_id', $url);
    }

    // ========== Static Method Tests ==========

    public function testGetCurrentUserReturnsNullWhenNotLoggedIn(): void
    {
        $_SESSION = [];
        $this->assertNull(Auth::getCurrentUser());
    }

    public function testGetCurrentUserReturnsUserData(): void
    {
        $userId = $this->createTestUser('user@woodsonisd.net', 'staff');
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $userId;

        $user = Auth::getCurrentUser();

        $this->assertNotNull($user);
        $this->assertEquals($userId, $user['id']);
        $this->assertEquals('user@woodsonisd.net', $user['email']);
        $this->assertEquals('staff', $user['role']);
    }

    public function testGetCurrentUserIdReturnsNullWhenNotLoggedIn(): void
    {
        $_SESSION = [];
        $this->assertNull(Auth::getCurrentUserId());
    }

    public function testGetCurrentUserIdReturnsId(): void
    {
        $userId = $this->createTestUser('user@woodsonisd.net', 'staff');
        $_SESSION['user_id'] = $userId;

        $this->assertEquals($userId, Auth::getCurrentUserId());
    }

    // ========== Role Check Tests ==========

    public function testIsAdminReturnsTrueForAdmin(): void
    {
        $userId = $this->createTestUser('admin@woodsonisd.net', 'admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::isAdmin());
    }

    public function testIsAdminReturnsFalseForStaff(): void
    {
        $userId = $this->createTestUser('staff@woodsonisd.net', 'staff');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::isAdmin());
    }

    public function testIsSuperAdminReturnsTrueForSuperAdmin(): void
    {
        $userId = $this->createTestUser('super@woodsonisd.net', 'super_admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::isSuperAdmin());
    }

    public function testIsSuperAdminReturnsFalseForAdmin(): void
    {
        $userId = $this->createTestUser('admin@woodsonisd.net', 'admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'admin';

        $this->assertFalse(Auth::isSuperAdmin());
    }

    public function testIsManagerReturnsTrueForManager(): void
    {
        $userId = $this->createTestUser('manager@woodsonisd.net', 'manager');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'manager';

        $this->assertTrue(Auth::isManager());
    }

    public function testIsStaffReturnsTrueForStaff(): void
    {
        $userId = $this->createTestUser('staff@woodsonisd.net', 'staff');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'staff';

        $this->assertTrue(Auth::isStaff());
    }

    // ========== Permission Tests ==========

    public function testCanEditAnyRecordTrueForAdmin(): void
    {
        $userId = $this->createTestUser('admin@woodsonisd.net', 'admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::canEditAnyRecord());
    }

    public function testCanEditAnyRecordFalseForStaff(): void
    {
        $userId = $this->createTestUser('staff@woodsonisd.net', 'staff');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::canEditAnyRecord());
    }

    public function testCanManageUsersTrueForAdmin(): void
    {
        $userId = $this->createTestUser('admin@woodsonisd.net', 'admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::canManageUsers());
    }

    public function testCanManageUsersFalseForStaff(): void
    {
        $userId = $this->createTestUser('staff@woodsonisd.net', 'staff');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::canManageUsers());
    }

    public function testCanDeleteUserBlocksSelfDeletion(): void
    {
        $userId = $this->createTestUser('admin@woodsonisd.net', 'admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'admin';

        $this->assertFalse(Auth::canDeleteUser($userId));
    }

    public function testCanDeleteUserAllowsDeletingOthers(): void
    {
        $adminId = $this->createTestUser('admin@woodsonisd.net', 'admin');
        $targetId = $this->createTestUser('target@woodsonisd.net', 'staff');

        $_SESSION['user_id'] = $adminId;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::canDeleteUser($targetId));
    }

    // ========== View As Feature Tests ==========

    public function testSetViewAsRoleOnlyWorksForSuperAdmin(): void
    {
        $userId = $this->createTestUser('super@woodsonisd.net', 'super_admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'super_admin';

        Auth::setViewAsRole('staff');

        $this->assertEquals('staff', $_SESSION['view_as_role']);
    }

    public function testGetEffectiveRoleReturnsViewAsWhenSet(): void
    {
        $userId = $this->createTestUser('super@woodsonisd.net', 'super_admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        $this->assertEquals('staff', Auth::getEffectiveRole());
    }

    public function testGetEffectiveRoleReturnsActualRoleWhenNoViewAs(): void
    {
        $userId = $this->createTestUser('admin@woodsonisd.net', 'admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'admin';

        $this->assertEquals('admin', Auth::getEffectiveRole());
    }

    public function testClearViewAsRoleRemovesViewAs(): void
    {
        $userId = $this->createTestUser('super@woodsonisd.net', 'super_admin');
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        Auth::clearViewAsRole();

        $this->assertArrayNotHasKey('view_as_role', $_SESSION);
    }

    // ========== Logout Tests ==========

    public function testLogoutClearsSession(): void
    {
        $userId = $this->createTestUser('user@woodsonisd.net', 'staff');
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'staff';

        Auth::logout();

        $this->assertArrayNotHasKey('logged_in', $_SESSION);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertArrayNotHasKey('role', $_SESSION);
    }

    // ========== Helper Methods ==========

    private function createTestUser(string $email, string $role): int
    {
        return self::$db->insert('users', [
            'email' => $email,
            'name' => 'Test User',
            'role' => $role,
            'is_active' => 1
        ]);
    }
}
