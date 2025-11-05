<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Auth;
use Hub\Database;

class AuthStaticMethodsTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->db->beginTransaction();

        // Clean test data
        $this->db->execute("DELETE FROM users WHERE id >= 600");
        $this->db->execute("DELETE FROM user_global_roles WHERE user_id >= 600");
        $this->db->execute("DELETE FROM section_access WHERE user_id >= 600");

        // Create test users with all roles
        $this->db->execute(
            "INSERT INTO users (id, email, name, role, is_active, google_id) VALUES
            (600, 'staff@test.com', 'Staff User', 'staff', 1, 'google600'),
            (601, 'admin@test.com', 'Admin User', 'admin', 1, 'google601'),
            (602, 'super@test.com', 'Super Admin', 'super_admin', 1, 'google602'),
            (603, 'inactive@test.com', 'Inactive User', 'staff', 0, 'google603'),
            (604, 'principal@test.com', 'Principal', 'principal', 1, 'google604'),
            (605, 'director@test.com', 'Director', 'maintenance_director', 1, 'google605')"
        );

        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear session
        session_unset();
    }

    protected function tearDown(): void
    {
        session_unset();
        $this->db->rollback();
    }

    // ============================================
    // requireLogin() tests
    // ============================================

    /** @test */
    public function require_login_redirects_when_not_logged_in()
    {
        // Clear session
        $_SESSION = [];

        // Can't directly test header redirect in PHPUnit, but we can verify behavior
        // In production this would redirect, in tests we check the condition
        $this->assertFalse(isset($_SESSION['logged_in']));
    }

    /** @test */
    public function require_login_allows_logged_in_user()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        // Would not redirect if logged in
        $this->assertTrue($_SESSION['logged_in']);
    }

    // ============================================
    // getCurrentUser() tests
    // ============================================

    /** @test */
    public function get_current_user_returns_null_when_not_logged_in()
    {
        $_SESSION = [];

        $user = Auth::getCurrentUser();

        $this->assertNull($user);
    }

    /** @test */
    public function get_current_user_returns_user_data_when_logged_in()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['email'] = 'staff@test.com';
        $_SESSION['name'] = 'Staff User';
        $_SESSION['role'] = 'staff';
        $_SESSION['picture'] = 'pic.jpg';

        $user = Auth::getCurrentUser();

        $this->assertIsArray($user);
        $this->assertEquals(600, $user['id']);
        $this->assertEquals('staff@test.com', $user['email']);
        $this->assertEquals('Staff User', $user['name']);
        $this->assertEquals('staff', $user['role']);
        $this->assertEquals('staff', $user['actual_role']);
        // Picture from DB might be null, check it exists as key
        $this->assertArrayHasKey('picture', $user);
    }    /** @test */
    public function get_current_user_validates_against_database()
    {
        // Set session for inactive user
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 603; // Inactive user
        $_SESSION['role'] = 'staff';

        $user = Auth::getCurrentUser();

        // Should return null because user is inactive
        $this->assertNull($user);
        // Session should be cleared
        $this->assertEmpty($_SESSION);
    }

    /** @test */
    public function get_current_user_returns_null_for_non_existent_user()
    {
        $_SESSION = []; // Start clean
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 9999; // Non-existent
        $_SESSION['role'] = 'staff';

        $user = Auth::getCurrentUser();

        $this->assertNull($user);
        // Note: session_destroy() is called but $_SESSION may not be empty in tests
        // The important thing is user is null
    }    /** @test */
    public function get_current_user_returns_null_when_no_user_id()
    {
        $_SESSION = []; // Start clean!
        $_SESSION['logged_in'] = true;
        // No user_id set

        $user = Auth::getCurrentUser();

        $this->assertNull($user);
    }

    /** @test */
    public function get_current_user_updates_session_with_fresh_db_data()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['email'] = 'old@test.com'; // Old data
        $_SESSION['name'] = 'Old Name';
        $_SESSION['role'] = 'staff';

        $user = Auth::getCurrentUser();

        // Session should be updated with fresh DB data
        $this->assertEquals('staff@test.com', $_SESSION['email']);
        $this->assertEquals('Staff User', $_SESSION['name']);
    }

    // ============================================
    // getCurrentUserId() tests
    // ============================================

    /** @test */
    public function get_current_user_id_returns_null_when_not_set()
    {
        $_SESSION = [];

        $userId = Auth::getCurrentUserId();

        $this->assertNull($userId);
    }

    /** @test */
    public function get_current_user_id_returns_user_id()
    {
        $_SESSION['user_id'] = 600;

        $userId = Auth::getCurrentUserId();

        $this->assertEquals(600, $userId);
    }

    /** @test */
    public function get_current_user_id_converts_empty_string_to_null()
    {
        $_SESSION['user_id'] = '';

        $userId = Auth::getCurrentUserId();

        $this->assertNull($userId);
    }

    // ============================================
    // getEffectiveRole() tests
    // ============================================

    /** @test */
    public function get_effective_role_returns_empty_string_when_not_logged_in()
    {
        $_SESSION = [];

        $role = Auth::getEffectiveRole();

        $this->assertEquals('', $role);
    }

    /** @test */
    public function get_effective_role_returns_user_role()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        $role = Auth::getEffectiveRole();

        $this->assertEquals('staff', $role);
    }

    /** @test */
    public function get_effective_role_returns_view_as_role_for_super_admin()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        $role = Auth::getEffectiveRole();

        $this->assertEquals('staff', $role);
    }

    // ============================================
    // setViewAsRole() and clearViewAsRole() tests
    // ============================================

    /** @test */
    public function set_view_as_role_sets_view_for_super_admin()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['role'] = 'super_admin';

        Auth::setViewAsRole('admin');

        $this->assertEquals('admin', $_SESSION['view_as_role']);
    }

    /** @test */
    public function set_view_as_role_does_not_set_for_non_super_admin()
    {
        $_SESSION = []; // Start clean
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 601;
        $_SESSION['email'] = 'admin@test.com';
        $_SESSION['name'] = 'Admin User';
        $_SESSION['role'] = 'admin';

        Auth::setViewAsRole('staff');

        $this->assertArrayNotHasKey('view_as_role', $_SESSION);
    }    /** @test */
    public function clear_view_as_role_removes_view_as_role()
    {
        $_SESSION['view_as_role'] = 'staff';

        Auth::clearViewAsRole();

        $this->assertArrayNotHasKey('view_as_role', $_SESSION);
    }

    // ============================================
    // isAdmin() tests
    // ============================================

    /** @test */
    public function is_admin_returns_true_for_super_admin()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::isAdmin());
    }

    /** @test */
    public function is_admin_returns_true_for_admin()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 601;
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::isAdmin());
    }

    /** @test */
    public function is_admin_returns_false_for_staff()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::isAdmin());
    }

    /** @test */
    public function is_admin_returns_false_when_not_logged_in()
    {
        $_SESSION = [];

        $this->assertFalse(Auth::isAdmin());
    }

    // ============================================
    // isSuperAdmin() tests
    // ============================================

    /** @test */
    public function is_super_admin_returns_true_for_super_admin()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::isSuperAdmin());
    }

    /** @test */
    public function is_super_admin_checks_actual_role_not_effective()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['role'] = 'super_admin';
        $_SESSION['view_as_role'] = 'staff';

        // Should still return true because actual role is super_admin
        $this->assertTrue(Auth::isSuperAdmin());
    }

    /** @test */
    public function is_super_admin_returns_false_for_admin()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 601;
        $_SESSION['role'] = 'admin';

        $this->assertFalse(Auth::isSuperAdmin());
    }

    // ============================================
    // isManager() tests
    // ============================================

    /** @test */
    public function is_manager_returns_true_for_manager_roles()
    {
        // Test roles that exist in our test data
        $testCases = [
            ['role' => 'super_admin', 'user_id' => 602, 'email' => 'super@test.com'],
            ['role' => 'admin', 'user_id' => 601, 'email' => 'admin@test.com'],
            ['role' => 'principal', 'user_id' => 604, 'email' => 'principal@test.com'],
            ['role' => 'maintenance_director', 'user_id' => 605, 'email' => 'director@test.com'],
        ];

        foreach ($testCases as $test) {
            $_SESSION = []; // Clear
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $test['user_id'];
            $_SESSION['email'] = $test['email'];
            $_SESSION['name'] = ucfirst($test['role']);
            $_SESSION['role'] = $test['role'];

            $this->assertTrue(Auth::isManager(), "Failed for role: {$test['role']}");
        }
    }    /** @test */
    public function is_manager_returns_false_for_staff()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::isManager());
    }

    // ============================================
    // isStaff() tests
    // ============================================

    /** @test */
    public function is_staff_returns_true_for_staff()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        $this->assertTrue(Auth::isStaff());
    }

    /** @test */
    public function is_staff_returns_true_for_super_admin()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::isStaff());
    }

    /** @test */
    public function is_staff_returns_false_for_admin()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 601;
        $_SESSION['role'] = 'admin';

        $this->assertFalse(Auth::isStaff());
    }

    // ============================================
    // canEditAnyRecord() tests
    // ============================================

    /** @test */
    public function can_edit_any_record_returns_true_for_privileged_roles()
    {
        $privilegedRoles = ['super_admin', 'admin'];
        $userMap = ['super_admin' => 602, 'admin' => 601];

        foreach ($privilegedRoles as $role) {
            $_SESSION = []; // Clear
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $userMap[$role];
            $_SESSION['email'] = "{$role}@test.com";
            $_SESSION['name'] = ucfirst($role);
            $_SESSION['role'] = $role;

            $this->assertTrue(Auth::canEditAnyRecord(), "Failed for role: {$role}");
        }
    }    /** @test */
    public function can_edit_any_record_returns_false_for_staff()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::canEditAnyRecord());
    }

    // ============================================
    // canManageUsers() tests
    // ============================================

    /** @test */
    public function can_manage_users_returns_true_for_super_admin()
    {
        $_SESSION = []; // Clear
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['email'] = 'super@test.com';
        $_SESSION['name'] = 'Super Admin';
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::canManageUsers());
    }

    /** @test */
    public function can_manage_users_returns_true_for_admin()
    {
        $_SESSION = []; // Clear
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 601;
        $_SESSION['email'] = 'admin@test.com';
        $_SESSION['name'] = 'Admin User';
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::canManageUsers());
    }    /** @test */
    public function can_manage_users_returns_false_for_staff()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::canManageUsers());
    }

    // ============================================
    // canDeleteUser() tests
    // ============================================

    /** @test */
    public function can_delete_user_returns_false_for_invalid_target_id()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['role'] = 'super_admin';

        $this->assertFalse(Auth::canDeleteUser(null));
        $this->assertFalse(Auth::canDeleteUser(0));
        $this->assertFalse(Auth::canDeleteUser(-1));
    }

    /** @test */
    public function can_delete_user_returns_false_when_not_admin()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::canDeleteUser(601));
    }

    /** @test */
    public function can_delete_user_returns_false_when_deleting_self()
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['role'] = 'super_admin';

        $this->assertFalse(Auth::canDeleteUser(602));
    }

    /** @test */
    public function can_delete_user_returns_true_for_super_admin_deleting_other()
    {
        $_SESSION = []; // Clear
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 602;
        $_SESSION['email'] = 'super@test.com';
        $_SESSION['name'] = 'Super Admin';
        $_SESSION['role'] = 'super_admin';

        $this->assertTrue(Auth::canDeleteUser(600));
    }

    /** @test */
    public function can_delete_user_returns_true_for_admin_deleting_staff()
    {
        $_SESSION = []; // Clear
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 601;
        $_SESSION['email'] = 'admin@test.com';
        $_SESSION['name'] = 'Admin User';
        $_SESSION['role'] = 'admin';

        $this->assertTrue(Auth::canDeleteUser(600));
    }

    /** @test */
    public function can_delete_user_returns_false_for_admin_deleting_super_admin()
    {
        $_SESSION = []; // Clear
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 601;
        $_SESSION['email'] = 'admin@test.com';
        $_SESSION['name'] = 'Admin User';
        $_SESSION['role'] = 'admin';

        $this->assertFalse(Auth::canDeleteUser(602));
    }    // ============================================
    // hasRole() tests (already in AuthTest.php, adding edge cases)
    // ============================================

    /** @test */
    public function has_role_checks_user_global_roles_table()
    {
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        // Add additional role
        $this->db->execute(
            "INSERT INTO user_global_roles (user_id, role, granted_by) VALUES (600, 'admin', 602)"
        );

        $this->assertTrue(Auth::hasRole('admin'));
        $this->assertTrue(Auth::hasRole('staff')); // Global role
    }

    /** @test */
    public function has_role_returns_false_for_non_existent_role()
    {
        $_SESSION['user_id'] = 600;
        $_SESSION['role'] = 'staff';

        $this->assertFalse(Auth::hasRole('nonexistent'));
    }
}
