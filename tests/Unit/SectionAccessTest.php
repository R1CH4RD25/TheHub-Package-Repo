<?php

namespace Tests\Unit;

use Hub\SectionAccess;
use Hub\Database;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SectionAccess class
 * Covers: getUserSections, hasAccess, getUserRole, grantAccess, revokeAccess,
 *         updateRole, getAllSections, getUsersWithAccess, compareRoles
 * Target: 11.76% → 95%+ coverage
 */
class SectionAccessTest extends TestCase
{
    private $db;
    private $sectionAccess;
    private $testUserId;
    private $testSuperAdminId;
    private $testSectionId;
    private $testSection2Id;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DB_NAME'] = 'woodson_hub_test';
        $this->db = Database::getInstance();
        $this->sectionAccess = new SectionAccess();

        // Clean test data
        $this->db->execute("DELETE FROM section_access WHERE user_id >= 600");
        $this->db->execute("DELETE FROM sections WHERE slug LIKE 'test-section%'");
        $this->db->execute("DELETE FROM users WHERE id >= 600");

        // Create test users
        $this->db->execute("DELETE FROM users WHERE id IN (600, 601)");
        $this->db->execute(
            "INSERT INTO users (id, email, name, role, is_active) VALUES (?, ?, ?, ?, ?)",
            [600, 'teststaff@test-section.com', 'Test Staff', 'staff', 1]
        );
        $this->db->execute(
            "INSERT INTO users (id, email, name, role, is_active) VALUES (?, ?, ?, ?, ?)",
            [601, 'testsuperadmin@test-section.com', 'Test Super Admin', 'super_admin', 1]
        );
        $this->testUserId = 600;
        $this->testSuperAdminId = 601;

        // Create test sections
        $this->db->execute(
            "INSERT INTO sections (slug, name, display_name, description, icon, base_url, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            ['test-section-1', 'Test Section 1', 'Test Section 1', 'Test Description 1', 'fa-test', '/test1', 1, 10]
        );
        $this->testSectionId = $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO sections (slug, name, display_name, description, icon, base_url, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            ['test-section-2', 'Test Section 2', 'Test Section 2', 'Test Description 2', 'fa-test2', '/test2', 1, 20]
        );
        $this->testSection2Id = $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM section_access WHERE user_id >= 600");
        $this->db->execute("DELETE FROM sections WHERE slug LIKE 'test-section%'");
        $this->db->execute("DELETE FROM users WHERE id >= 600");
        parent::tearDown();
    }

    /**
     * Test getUserSections returns empty for user with no access
     */
    public function testGetUserSectionsEmptyForNoAccess()
    {
        $sections = $this->sectionAccess->getUserSections($this->testUserId);

        $this->assertIsArray($sections);
        $this->assertEmpty($sections);
    }

    /**
     * Test getUserSections returns sections user has access to
     */
    public function testGetUserSectionsReturnsUserSections()
    {
        // Grant access to first section
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'staff', $this->testSuperAdminId]
        );

        $sections = $this->sectionAccess->getUserSections($this->testUserId);

        $this->assertCount(1, $sections);
        $this->assertEquals('test-section-1', $sections[0]['section_slug']);
    }

    /**
     * Test getUserSections for super admin returns all active sections
     */
    public function testGetUserSectionsSuperAdminGetsAll()
    {
        $sections = $this->sectionAccess->getUserSections($this->testSuperAdminId);

        // Should include at least our 2 test sections
        $this->assertGreaterThanOrEqual(2, count($sections));

        // Find our test sections
        $found1 = false;
        $found2 = false;
        foreach ($sections as $section) {
            if ($section['section_slug'] === 'test-section-1') {
                $found1 = true;
                $this->assertEquals('super_admin', $section['role']);
            }
            if ($section['section_slug'] === 'test-section-2') {
                $found2 = true;
                $this->assertEquals('super_admin', $section['role']);
            }
        }

        $this->assertTrue($found1, 'Super admin should see test-section-1');
        $this->assertTrue($found2, 'Super admin should see test-section-2');
    }

    /**
     * Test getUserSections filters out inactive sections
     */
    public function testGetUserSectionsExcludesInactiveSections()
    {
        // Create inactive section
        $this->db->execute(
            "INSERT INTO sections (slug, name, display_name, base_url, is_active) VALUES (?, ?, ?, ?, ?)",
            ['test-section-inactive', 'Inactive', 'Inactive', '/inactive', 0]
        );
        $inactiveId = $this->db->lastInsertId();

        // Grant access to inactive section
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $inactiveId, 'staff', $this->testSuperAdminId]
        );

        $sections = $this->sectionAccess->getUserSections($this->testUserId);

        // Should not include inactive section
        $slugs = array_column($sections, 'section_slug');
        $this->assertNotContains('test-section-inactive', $slugs);
    }

    /**
     * Test hasAccess returns false for no access
     */
    public function testHasAccessReturnsFalseForNoAccess()
    {
        $hasAccess = $this->sectionAccess->hasAccess($this->testUserId, 'test-section-1');

        $this->assertFalse($hasAccess);
    }

    /**
     * Test hasAccess returns true with sufficient role
     */
    public function testHasAccessReturnsTrueWithSufficientRole()
    {
        // Grant admin access
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'admin', $this->testSuperAdminId]
        );

        // Check with staff minimum (admin > staff)
        $hasAccess = $this->sectionAccess->hasAccess($this->testUserId, 'test-section-1', 'staff');

        $this->assertTrue($hasAccess);
    }

    /**
     * Test hasAccess returns false with insufficient role
     */
    public function testHasAccessReturnsFalseWithInsufficientRole()
    {
        // Grant staff access
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'staff', $this->testSuperAdminId]
        );

        // Check with admin minimum (staff < admin)
        $hasAccess = $this->sectionAccess->hasAccess($this->testUserId, 'test-section-1', 'admin');

        $this->assertFalse($hasAccess);
    }

    /**
     * Test hasAccess returns true for super admin always
     */
    public function testHasAccessSuperAdminAlwaysTrue()
    {
        // Super admin without explicit access
        $hasAccess = $this->sectionAccess->hasAccess($this->testSuperAdminId, 'test-section-1', 'admin');

        $this->assertTrue($hasAccess);
    }

    /**
     * Test hasAccess returns false for inactive section
     */
    public function testHasAccessReturnsFalseForInactiveSection()
    {
        // Create inactive section
        $this->db->execute(
            "INSERT INTO sections (slug, name, display_name, base_url, is_active) VALUES (?, ?, ?, ?, ?)",
            ['test-section-inactive', 'Inactive', 'Inactive', '/inactive', 0]
        );
        $inactiveId = $this->db->lastInsertId();

        // Grant access
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $inactiveId, 'admin', $this->testSuperAdminId]
        );

        $hasAccess = $this->sectionAccess->hasAccess($this->testUserId, 'test-section-inactive');

        $this->assertFalse($hasAccess);
    }

    /**
     * Test getUserRole returns null for no access
     */
    public function testGetUserRoleReturnsNullForNoAccess()
    {
        $role = $this->sectionAccess->getUserRole($this->testUserId, 'test-section-1');

        $this->assertNull($role);
    }

    /**
     * Test getUserRole returns correct role
     */
    public function testGetUserRoleReturnsCorrectRole()
    {
        // Grant manager access
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'manager', $this->testSuperAdminId]
        );

        $role = $this->sectionAccess->getUserRole($this->testUserId, 'test-section-1');

        $this->assertEquals('manager', $role);
    }

    /**
     * Test getUserRole returns super_admin for super admin user
     */
    public function testGetUserRoleSuperAdminReturnsSuper()
    {
        $role = $this->sectionAccess->getUserRole($this->testSuperAdminId, 'test-section-1');

        $this->assertEquals('super_admin', $role);
    }

    /**
     * Test grantAccess creates new access
     */
    public function testGrantAccessCreatesNewAccess()
    {
        $result = $this->sectionAccess->grantAccess(
            $this->testUserId,
            'test-section-1',
            'admin',
            $this->testSuperAdminId
        );

        $this->assertTrue($result);

        // Verify in database
        $access = $this->db->fetchOne(
            "SELECT * FROM section_access WHERE user_id = ? AND section_id = ?",
            [$this->testUserId, $this->testSectionId]
        );

        $this->assertNotNull($access);
        $this->assertEquals('admin', $access['role']);
        $this->assertEquals($this->testSuperAdminId, $access['granted_by']);
    }

    /**
     * Test grantAccess updates existing access
     */
    public function testGrantAccessUpdatesExistingAccess()
    {
        // Create initial access
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'staff', $this->testSuperAdminId]
        );

        // Update to admin
        $result = $this->sectionAccess->grantAccess(
            $this->testUserId,
            'test-section-1',
            'admin',
            $this->testSuperAdminId
        );

        $this->assertTrue($result);

        // Verify updated
        $access = $this->db->fetchOne(
            "SELECT * FROM section_access WHERE user_id = ? AND section_id = ?",
            [$this->testUserId, $this->testSectionId]
        );

        $this->assertEquals('admin', $access['role']);
    }

    /**
     * Test grantAccess throws exception for invalid section
     */
    public function testGrantAccessThrowsExceptionForInvalidSection()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Section not found');

        $this->sectionAccess->grantAccess(
            $this->testUserId,
            'fake-section-slug',
            'admin',
            $this->testSuperAdminId
        );
    }

    /**
     * Test revokeAccess removes access
     */
    public function testRevokeAccessRemovesAccess()
    {
        // Grant access first
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'admin', $this->testSuperAdminId]
        );

        // Revoke it
        $result = $this->sectionAccess->revokeAccess($this->testUserId, 'test-section-1');

        $this->assertTrue($result);

        // Verify removed
        $access = $this->db->fetchOne(
            "SELECT * FROM section_access WHERE user_id = ? AND section_id = ?",
            [$this->testUserId, $this->testSectionId]
        );

        $this->assertFalse($access);
    }

    /**
     * Test revokeAccess is safe for non-existent access
     */
    public function testRevokeAccessSafeForNonExistent()
    {
        // Revoke non-existent access (should not error)
        $result = $this->sectionAccess->revokeAccess($this->testUserId, 'test-section-1');

        $this->assertTrue($result);
    }

    /**
     * Test updateRole changes role
     */
    public function testUpdateRoleChangesRole()
    {
        // Grant initial access
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'staff', $this->testSuperAdminId]
        );

        // Update role
        $result = $this->sectionAccess->updateRole($this->testUserId, 'test-section-1', 'manager');

        $this->assertTrue($result);

        // Verify updated
        $access = $this->db->fetchOne(
            "SELECT * FROM section_access WHERE user_id = ? AND section_id = ?",
            [$this->testUserId, $this->testSectionId]
        );

        $this->assertEquals('manager', $access['role']);
        // granted_by should remain unchanged
        $this->assertEquals($this->testSuperAdminId, $access['granted_by']);
    }

    /**
     * Test getAllSections returns all sections
     */
    public function testGetAllSectionsReturnsAll()
    {
        $sections = $this->sectionAccess->getAllSections();

        $this->assertIsArray($sections);
        $this->assertGreaterThanOrEqual(2, count($sections));

        // Find our test sections
        $found = array_filter($sections, function($s) {
            return in_array($s['slug'], ['test-section-1', 'test-section-2']);
        });

        $this->assertGreaterThanOrEqual(2, count($found));
    }

    /**
     * Test getAllSections ordered by sort_order
     */
    public function testGetAllSectionsOrdering()
    {
        $sections = $this->sectionAccess->getAllSections();

        // Find our test sections
        $testSections = array_filter($sections, function($s) {
            return in_array($s['slug'], ['test-section-1', 'test-section-2']);
        });
        $testSections = array_values($testSections);

        if (count($testSections) >= 2) {
            // test-section-1 has sort_order 10, test-section-2 has 20
            $indices = [];
            foreach ($sections as $index => $section) {
                if ($section['slug'] === 'test-section-1') $indices[1] = $index;
                if ($section['slug'] === 'test-section-2') $indices[2] = $index;
            }

            $this->assertLessThan($indices[2], $indices[1], 'Section 1 should come before Section 2');
        }
    }

    /**
     * Test getUsersWithAccess without filter
     */
    public function testGetUsersWithAccessNoFilter()
    {
        // Grant access to both sections
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'staff', $this->testSuperAdminId]
        );
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSection2Id, 'admin', $this->testSuperAdminId]
        );

        $users = $this->sectionAccess->getUsersWithAccess();

        $this->assertIsArray($users);

        // Find our test user's access
        $testUserAccess = array_filter($users, function($u) {
            return $u['user_id'] == $this->testUserId;
        });

        $this->assertGreaterThanOrEqual(2, count($testUserAccess));
    }

    /**
     * Test getUsersWithAccess with section filter
     */
    public function testGetUsersWithAccessWithFilter()
    {
        // Grant access to section 1
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'staff', $this->testSuperAdminId]
        );

        $users = $this->sectionAccess->getUsersWithAccess('test-section-1');

        $this->assertIsArray($users);

        // All users should be for test-section-1
        foreach ($users as $user) {
            $this->assertEquals('test-section-1', $user['section_slug']);
        }

        // Should include our test user
        $found = array_filter($users, function($u) {
            return $u['user_id'] == $this->testUserId;
        });

        $this->assertCount(1, $found);
    }

    /**
     * Test role hierarchy comparison
     */
    public function testRoleHierarchy()
    {
        // Staff < Admin (staff gets access)
        $this->db->execute(
            "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
            [$this->testUserId, $this->testSectionId, 'admin', $this->testSuperAdminId]
        );

        $hasStaffAccess = $this->sectionAccess->hasAccess($this->testUserId, 'test-section-1', 'staff');
        $hasAdminAccess = $this->sectionAccess->hasAccess($this->testUserId, 'test-section-1', 'admin');
        $hasSuperAccess = $this->sectionAccess->hasAccess($this->testUserId, 'test-section-1', 'super_admin');

        $this->assertTrue($hasStaffAccess, 'Admin should have staff access');
        $this->assertTrue($hasAdminAccess, 'Admin should have admin access');
        $this->assertFalse($hasSuperAccess, 'Admin should NOT have super_admin access');
    }

    /**
     * Test complete role hierarchy
     */
    public function testCompleteRoleHierarchy()
    {
        $roles = ['staff', 'maintenance', 'maintenance_director', 'manager', 'admin', 'super_admin'];

        foreach ($roles as $index => $role) {
            // Grant role
            $this->db->execute(
                "DELETE FROM section_access WHERE user_id = ? AND section_id = ?",
                [$this->testUserId, $this->testSectionId]
            );
            $this->db->execute(
                "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, ?)",
                [$this->testUserId, $this->testSectionId, $role, $this->testSuperAdminId]
            );

            // Should have access to all roles at or below current level
            for ($i = 0; $i <= $index; $i++) {
                $hasAccess = $this->sectionAccess->hasAccess($this->testUserId, 'test-section-1', $roles[$i]);
                $this->assertTrue($hasAccess, "$role should have {$roles[$i]} access");
            }

            // Should NOT have access to roles above current level
            for ($i = $index + 1; $i < count($roles); $i++) {
                $hasAccess = $this->sectionAccess->hasAccess($this->testUserId, 'test-section-1', $roles[$i]);
                $this->assertFalse($hasAccess, "$role should NOT have {$roles[$i]} access");
            }
        }
    }
}
