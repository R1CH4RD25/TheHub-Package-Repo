<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Module;
use Hub\Database;

class ModuleTest extends TestCase
{
    private $db;
    private $module;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->db->beginTransaction();
        $this->module = new Module();

        // Clean test data
        $this->db->execute("DELETE FROM user_module_access WHERE user_id >= 500");
        $this->db->execute("DELETE FROM modules WHERE id >= 500");
        $this->db->execute("DELETE FROM users WHERE id >= 500");

        // Create test users (using valid DB roles)
        $this->db->execute(
            "INSERT INTO users (id, email, name, role, is_active) VALUES
            (500, 'staff@test.com', 'Staff User', 'staff', 1),
            (501, 'director@test.com', 'Director User', 'maintenance_director', 1),
            (502, 'admin@test.com', 'Admin User', 'admin', 1),
            (503, 'super@test.com', 'Super Admin', 'super_admin', 1)"
        );

        // Create test modules
        $this->db->execute(
            "INSERT INTO modules (id, name, display_name, description, icon, slug, base_url, sort_order, is_active) VALUES
            (500, 'TestModule', 'Test Module', 'Test description', '🧪', 'test-module', '/modules/test', 1, 1),
            (501, 'SecondModule', 'Second Module', 'Second module', '📦', 'second-module', '/modules/second', 2, 1),
            (502, 'InactiveModule', 'Inactive', 'Inactive', '❌', 'inactive', '/modules/inactive', 3, 0)"
        );
    }

    protected function tearDown(): void
    {
        $this->db->rollback();
    }

    /** @test */
    public function it_gets_all_active_modules()
    {
        $modules = $this->module->getAll();

        $this->assertIsArray($modules);
        $testModules = array_filter($modules, fn($m) => $m['id'] >= 500);
        $this->assertCount(2, $testModules); // Only active modules

        // Verify inactive module not included
        $slugs = array_column($testModules, 'slug');
        $this->assertNotContains('inactive', $slugs);
    }

    /** @test */
    public function it_orders_modules_by_sort_order_and_display_name()
    {
        $modules = $this->module->getAll();

        $testModules = array_filter($modules, fn($m) => $m['id'] >= 500 && $m['is_active']);
        $testModules = array_values($testModules);

        $this->assertGreaterThanOrEqual(2, count($testModules));
        // First module should have lower sort_order
        $this->assertLessThan($testModules[1]['sort_order'], $testModules[0]['sort_order']);
    }

    /** @test */
    public function it_gets_user_modules_for_staff()
    {
        // Grant staff access to first module
        $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by, is_active) VALUES (500, 500, 'staff', 503, 1)"
        );

        $modules = $this->module->getUserModules(500);

        $this->assertIsArray($modules);
        $this->assertCount(1, $modules);
        $this->assertEquals('test-module', $modules[0]['slug']);
        $this->assertEquals('staff', $modules[0]['user_role']);
        $this->assertArrayHasKey('granted_at', $modules[0]);
    }

    /** @test */
    public function it_gets_all_modules_for_super_admin()
    {
        $modules = $this->module->getUserModules(503);

        $this->assertIsArray($modules);
        $testModules = array_filter($modules, fn($m) => $m['id'] >= 500);
        $this->assertCount(2, $testModules); // Only active modules

        // Verify user_role is set
        foreach ($testModules as $module) {
            $this->assertEquals('super_admin', $module['user_role']);
        }
    }

    /** @test */
    public function it_returns_empty_array_for_user_with_no_access()
    {
        $modules = $this->module->getUserModules(500);

        $this->assertIsArray($modules);
        $this->assertCount(0, $modules);
    }

    /** @test */
    public function it_checks_super_admin_has_access()
    {
        $hasAccess = $this->module->hasAccess(503, 'test-module');

        $this->assertTrue($hasAccess);
    }

    /** @test */
    public function it_checks_staff_with_access_has_access()
    {
        $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by, is_active) VALUES (500, 500, 'staff', 503, 1)"
        );

        $hasAccess = $this->module->hasAccess(500, 'test-module');

        $this->assertTrue($hasAccess);
    }

    /** @test */
    public function it_checks_user_without_access_has_no_access()
    {
        $hasAccess = $this->module->hasAccess(500, 'test-module');

        $this->assertFalse($hasAccess);
    }

    /** @test */
    public function it_checks_access_to_inactive_module_returns_false()
    {
        $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by, is_active) VALUES (500, 502, 'staff', 503, 1)"
        );

        $hasAccess = $this->module->hasAccess(500, 'inactive');

        $this->assertFalse($hasAccess);
    }

    /** @test */
    public function it_checks_role_hierarchy_staff_cannot_access_admin_minimum()
    {
        $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by, is_active) VALUES (500, 500, 'staff', 503, 1)"
        );

        $hasAccess = $this->module->hasAccess(500, 'test-module', 'admin');

        $this->assertFalse($hasAccess);
    }

    /** @test */
    public function it_checks_role_hierarchy_super_admin_can_access_admin_minimum()
    {
        $hasAccess = $this->module->hasAccess(503, 'test-module', 'admin');

        $this->assertTrue($hasAccess);
    }

    /** @test */
    public function it_checks_role_hierarchy_admin_can_access_admin_minimum()
    {
        $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by, is_active) VALUES (502, 500, 'admin', 503, 1)"
        );

        $hasAccess = $this->module->hasAccess(502, 'test-module', 'admin');

        $this->assertTrue($hasAccess);
    }    /** @test */
    public function it_grants_new_access()
    {
        $result = $this->module->grantAccess(500, 500, 'staff', 503);

        $this->assertInstanceOf('PDOStatement', $result);

        // Verify access was granted
        $access = $this->db->fetchOne(
            "SELECT * FROM user_module_access WHERE user_id = 500 AND module_id = 500"
        );
        $this->assertNotFalse($access);
        $this->assertEquals('staff', $access['role']);
        $this->assertEquals(503, $access['granted_by']);
        $this->assertEquals(1, $access['is_active']);
    }

    /** @test */
    public function it_updates_existing_access_on_grant()
    {
        // Create initial access
        $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by, is_active) VALUES (500, 500, 'staff', 503, 1)"
        );

        // Update to admin
        $result = $this->module->grantAccess(500, 500, 'admin', 502);

        $this->assertInstanceOf('PDOStatement', $result);

        // Verify access was updated
        $access = $this->db->fetchOne(
            "SELECT * FROM user_module_access WHERE user_id = 500 AND module_id = 500"
        );
        $this->assertEquals('admin', $access['role']);
        $this->assertEquals(502, $access['granted_by']);
        $this->assertEquals(1, $access['is_active']);
    }

    /** @test */
    public function it_reactivates_revoked_access_on_grant()
    {
        // Create revoked access
        $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by, is_active) VALUES (500, 500, 'staff', 503, 0)"
        );

        // Grant again
        $result = $this->module->grantAccess(500, 500, 'admin', 503);

        $this->assertInstanceOf('PDOStatement', $result);

        // Verify access was reactivated
        $access = $this->db->fetchOne(
            "SELECT * FROM user_module_access WHERE user_id = 500 AND module_id = 500"
        );
        $this->assertEquals(1, $access['is_active']);
        $this->assertEquals('admin', $access['role']);
    }    /** @test */
    public function it_revokes_access()
    {
        // Grant access first
        $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by, is_active) VALUES (500, 500, 'staff', 503, 1)"
        );

        $result = $this->module->revokeAccess(500, 500);

        $this->assertInstanceOf('PDOStatement', $result);

        // Verify access was revoked (soft delete)
        $access = $this->db->fetchOne(
            "SELECT * FROM user_module_access WHERE user_id = 500 AND module_id = 500"
        );
        $this->assertEquals(0, $access['is_active']);
    }

    /** @test */
    public function it_revoke_is_idempotent()
    {
        $result1 = $this->module->revokeAccess(500, 500);
        $result2 = $this->module->revokeAccess(500, 500);

        $this->assertInstanceOf('PDOStatement', $result1);
        $this->assertInstanceOf('PDOStatement', $result2);
    }

    /** @test */
    public function it_gets_module_by_slug()
    {
        $module = $this->module->getBySlug('test-module');

        $this->assertIsArray($module);
        $this->assertEquals(500, $module['id']);
        $this->assertEquals('Test Module', $module['display_name']);
        $this->assertEquals('🧪', $module['icon']);
    }

    /** @test */
    public function it_returns_false_for_non_existent_slug()
    {
        $module = $this->module->getBySlug('non-existent');

        $this->assertFalse($module);
    }

    /** @test */
    public function it_returns_false_for_inactive_module_slug()
    {
        $module = $this->module->getBySlug('inactive');

        $this->assertFalse($module);
    }

    /** @test */
    public function it_creates_new_module()
    {
        $data = [
            'name' => 'NewModule',
            'display_name' => 'New Module',
            'description' => 'New module description',
            'icon' => '🆕',
            'slug' => 'new-module',
            'base_url' => '/modules/new',
            'sort_order' => 10
        ];

        $result = $this->module->create($data);

        $this->assertInstanceOf('PDOStatement', $result);

        // Verify module was created
        $module = $this->db->fetchOne("SELECT * FROM modules WHERE slug = 'new-module'");
        $this->assertNotFalse($module);
        $this->assertEquals('New Module', $module['display_name']);
        $this->assertEquals('🆕', $module['icon']);
        $this->assertEquals(10, $module['sort_order']);
    }

    /** @test */
    public function it_creates_module_with_defaults()
    {
        $data = [
            'name' => 'MinimalModule',
            'display_name' => 'Minimal',
            'slug' => 'minimal',
            'base_url' => '/modules/minimal'
        ];

        $result = $this->module->create($data);

        $this->assertInstanceOf('PDOStatement', $result);

        // Verify defaults were applied
        $module = $this->db->fetchOne("SELECT * FROM modules WHERE slug = 'minimal'");
        $this->assertNotFalse($module);
        $this->assertEquals('📦', $module['icon']); // Default icon
        $this->assertEquals(99, $module['sort_order']); // Default sort order
        $this->assertNull($module['description']); // Null description
    }

    /** @test */
    public function it_gets_module_users()
    {
        // Grant access to multiple users (staff/admin are valid module roles per Module.php hierarchy)
        $this->db->execute(
            "INSERT INTO user_module_access (user_id, module_id, role, granted_by, is_active) VALUES
            (500, 500, 'staff', 503, 1),
            (501, 500, 'admin', 503, 1),
            (502, 500, 'admin', 503, 1)"
        );

        $users = $this->module->getModuleUsers(500);

        $this->assertIsArray($users);
        $this->assertGreaterThanOrEqual(3, count($users));

        // Verify it uses the view (has user_name from JOIN)
        $this->assertArrayHasKey('user_name', $users[0]);
    }    /** @test */
    public function it_gets_empty_array_for_module_with_no_users()
    {
        $users = $this->module->getModuleUsers(501);

        $this->assertIsArray($users);
        $this->assertCount(0, $users);
    }

    /** @test */
    public function it_full_lifecycle_test()
    {
        // 1. Create module
        $moduleData = [
            'name' => 'LifecycleModule',
            'display_name' => 'Lifecycle Test',
            'slug' => 'lifecycle-test',
            'base_url' => '/modules/lifecycle',
            'sort_order' => 50
        ];
        $this->module->create($moduleData);

        // 2. Get by slug
        $module = $this->module->getBySlug('lifecycle-test');
        $this->assertNotFalse($module);
        $moduleId = $module['id'];

        // 3. Grant access
        $this->module->grantAccess(500, $moduleId, 'staff', 503);

        // 4. Check access
        $this->assertTrue($this->module->hasAccess(500, 'lifecycle-test'));

        // 5. Get user modules
        $userModules = $this->module->getUserModules(500);
        $lifecycleModule = array_filter($userModules, fn($m) => $m['slug'] === 'lifecycle-test');
        $this->assertCount(1, $lifecycleModule);

        // 6. Update role
        $this->module->grantAccess(500, $moduleId, 'admin', 503);
        $access = $this->db->fetchOne("SELECT role FROM user_module_access WHERE user_id = 500 AND module_id = ?", [$moduleId]);
        $this->assertEquals('admin', $access['role']);

        // 7. Revoke access
        $this->module->revokeAccess(500, $moduleId);
        $this->assertFalse($this->module->hasAccess(500, 'lifecycle-test'));

        // 8. Verify appears in getAll
        $allModules = $this->module->getAll();
        $foundModule = array_filter($allModules, fn($m) => $m['slug'] === 'lifecycle-test');
        $this->assertCount(1, $foundModule);
    }
}
