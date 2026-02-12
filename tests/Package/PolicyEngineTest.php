<?php

namespace Hub\Tests\Package;

use PHPUnit\Framework\TestCase;
use Hub\Package\PolicyEngine;
use Hub\User;
use Hub\Database;

/**
 * PolicyEngine v1 Test Suite
 *
 * Tests all Sprint 2 security features:
 * - Field-level access control
 * - Scope filtering (own, department, campus, role-based)
 * - Data classification masking
 * - Rate limiting
 * - Time-based restrictions
 * - MFA enforcement hooks
 * - Anomaly detection
 */
class PolicyEngineTest extends TestCase
{
    private PolicyEngine $engine;
    private static Database $db;
    private static int $testUserId = 70003;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
        self::$db->execute("INSERT INTO users (id, email, name, role) VALUES (70003, 'policy-test@example.com', 'Policy Test User', 'staff') ON DUPLICATE KEY UPDATE id=id");
    }

    protected function setUp(): void
    {
        $this->engine = new PolicyEngine();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            self::$db->execute("DELETE FROM package_rate_limits WHERE user_id = 70003");
        } catch (\Exception $e) {
        }
    }

    // =========================================================================
    // ROLE HIERARCHY TESTS
    // =========================================================================

    public function testRoleHierarchy(): void
    {
        $this->assertEquals(0, $this->engine->getRoleLevel('guest'));
        $this->assertEquals(10, $this->engine->getRoleLevel('standard'));
        $this->assertEquals(20, $this->engine->getRoleLevel('manager'));
        $this->assertEquals(30, $this->engine->getRoleLevel('admin'));
        $this->assertEquals(40, $this->engine->getRoleLevel('super_admin'));
        $this->assertEquals(0, $this->engine->getRoleLevel('unknown'));
    }

    public function testHasRoleWithHierarchy(): void
    {
        $admin = $this->createUser('admin');
        $standard = $this->createUser('standard');

        $this->assertTrue($this->engine->hasRole($admin, 'standard'));
        $this->assertTrue($this->engine->hasRole($admin, 'manager'));
        $this->assertTrue($this->engine->hasRole($admin, 'admin'));
        $this->assertFalse($this->engine->hasRole($admin, 'super_admin'));

        $this->assertTrue($this->engine->hasRole($standard, 'standard'));
        $this->assertFalse($this->engine->hasRole($standard, 'manager'));
    }

    // =========================================================================
    // PERMISSION TESTS
    // =========================================================================

    public function testExactPermissionMatch(): void
    {
        $admin = $this->createUser('admin');
        $this->assertTrue($this->engine->check($admin, 'students.view'));
        $this->assertTrue($this->engine->check($admin, 'students.manage'));
    }

    public function testWildcardPermission(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $this->assertTrue($this->engine->check($superAdmin, 'anything.goes'));
        $this->assertTrue($this->engine->check($superAdmin, 'some.random.permission'));
    }

    public function testStandardUserLimitedPermissions(): void
    {
        $standard = $this->createUser('standard');
        $this->assertTrue($this->engine->check($standard, 'students.view'));
        $this->assertFalse($this->engine->check($standard, 'students.delete'));
    }

    public function testGuestHasNoPermissions(): void
    {
        $guest = $this->createUser('guest');
        $this->assertFalse($this->engine->check($guest, 'students.view'));
        $this->assertFalse($this->engine->check($guest, 'anything'));
    }

    // =========================================================================
    // PAGE ACCESS TESTS
    // =========================================================================

    public function testCanAccessPageWithRole(): void
    {
        $admin = $this->createUser('admin');
        $standard = $this->createUser('standard');

        $pageConfig = ['id' => 'admin-page', 'requiredRole' => 'admin'];

        $this->assertTrue($this->engine->canAccessPage($admin, $pageConfig));
        $this->assertFalse($this->engine->canAccessPage($standard, $pageConfig));
    }

    public function testCanAccessPageWithPermission(): void
    {
        $manager = $this->createUser('manager');
        $standard = $this->createUser('standard');

        $pageConfig = ['id' => 'edit-page', 'requiredPermission' => 'students.edit'];

        $this->assertTrue($this->engine->canAccessPage($manager, $pageConfig));
        $this->assertFalse($this->engine->canAccessPage($standard, $pageConfig));
    }

    public function testCanAccessPageNoRestrictions(): void
    {
        $guest = $this->createUser('guest');
        $this->assertTrue($this->engine->canAccessPage($guest, ['id' => 'public-page']));
    }

    // =========================================================================
    // FIELD-LEVEL ACCESS TESTS (v1)
    // =========================================================================

    public function testGetVisibleFieldsNoRestrictions(): void
    {
        $standard = $this->createUser('standard');
        $columns = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
        ];

        $visible = $this->engine->getVisibleFields($standard, $columns);
        $this->assertCount(2, $visible);
    }

    public function testGetVisibleFieldsWithMinRole(): void
    {
        $standard = $this->createUser('standard');
        $admin = $this->createUser('admin');

        $columns = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'ssn', 'label' => 'SSN', 'access' => ['minRole' => 'admin']],
            ['key' => 'password', 'label' => 'Password', 'access' => ['minRole' => 'super_admin']],
        ];

        // Standard user: sees name, email only
        $standardVisible = $this->engine->getVisibleFields($standard, $columns);
        $this->assertCount(2, $standardVisible);
        $keys = array_column($standardVisible, 'key');
        $this->assertContains('name', $keys);
        $this->assertContains('email', $keys);
        $this->assertNotContains('ssn', $keys);

        // Admin: sees name, email, ssn but not password
        $adminVisible = $this->engine->getVisibleFields($admin, $columns);
        $this->assertCount(3, $adminVisible);
        $adminKeys = array_column($adminVisible, 'key');
        $this->assertContains('ssn', $adminKeys);
        $this->assertNotContains('password', $adminKeys);
    }

    public function testGetVisibleFieldKeys(): void
    {
        $admin = $this->createUser('admin');
        $columns = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'ssn', 'label' => 'SSN', 'access' => ['minRole' => 'admin']],
            ['key' => 'secret', 'label' => 'Secret', 'access' => ['minRole' => 'super_admin']],
        ];

        $keys = $this->engine->getVisibleFieldKeys($admin, $columns);
        $this->assertEquals(['name', 'ssn'], $keys);
    }

    public function testGetDataClassifications(): void
    {
        $columns = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'ssn', 'label' => 'SSN', 'access' => ['dataClass' => 'regulated']],
            ['key' => 'password', 'label' => 'Password', 'access' => ['dataClass' => 'secret']],
            ['key' => 'notes', 'label' => 'Notes', 'access' => ['dataClass' => 'confidential']],
        ];

        $classifications = $this->engine->getDataClassifications($columns);
        $this->assertEquals([
            'ssn' => 'regulated',
            'password' => 'secret',
            'notes' => 'confidential',
        ], $classifications);
    }

    // =========================================================================
    // SCOPE FILTER TESTS (v1)
    // =========================================================================

    public function testScopeFiltersEmptyConfig(): void
    {
        $user = $this->createUser('standard');
        $this->assertEmpty($this->engine->getScopeFilters($user, []));
    }

    public function testScopeFiltersSuperAdminBypass(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $scopeConfig = ['type' => 'own', 'ownerColumn' => 'created_by'];
        $this->assertEmpty($this->engine->getScopeFilters($superAdmin, $scopeConfig));
    }

    public function testScopeFiltersAdminBypass(): void
    {
        $admin = $this->createUser('admin');
        $scopeConfig = ['type' => 'own', 'ownerColumn' => 'created_by'];
        $this->assertEmpty($this->engine->getScopeFilters($admin, $scopeConfig));
    }

    public function testScopeFiltersAdminRestricted(): void
    {
        $admin = $this->createUser('admin');
        $scopeConfig = ['type' => 'own', 'ownerColumn' => 'created_by', 'restrictAdmin' => true];
        $filters = $this->engine->getScopeFilters($admin, $scopeConfig);
        $this->assertCount(1, $filters);
        $this->assertEquals('created_by', $filters[0]['column']);
    }

    public function testScopeFiltersOwnRecords(): void
    {
        $user = $this->createUser('standard', 42);
        $scopeConfig = ['type' => 'own', 'ownerColumn' => 'created_by'];

        $filters = $this->engine->getScopeFilters($user, $scopeConfig);
        $this->assertCount(1, $filters);
        $this->assertEquals('created_by', $filters[0]['column']);
        $this->assertEquals('=', $filters[0]['operator']);
        $this->assertEquals(42, $filters[0]['value']);
    }

    public function testScopeFiltersDepartment(): void
    {
        $user = $this->createUser('standard', 1, ['department_id' => 5]);

        $scopeConfig = ['type' => 'department', 'departmentColumn' => 'dept_id'];
        $filters = $this->engine->getScopeFilters($user, $scopeConfig);

        $this->assertCount(1, $filters);
        $this->assertEquals('dept_id', $filters[0]['column']);
        $this->assertEquals(5, $filters[0]['value']);
    }

    public function testScopeFiltersCampus(): void
    {
        $user = $this->createUser('standard', 1, ['campus_id' => 3]);

        $scopeConfig = ['type' => 'campus'];
        $filters = $this->engine->getScopeFilters($user, $scopeConfig);

        $this->assertCount(1, $filters);
        $this->assertEquals('campus_id', $filters[0]['column']);
        $this->assertEquals(3, $filters[0]['value']);
    }

    public function testScopeFiltersNone(): void
    {
        $user = $this->createUser('standard');
        $this->assertEmpty($this->engine->getScopeFilters($user, ['type' => 'none']));
    }

    // =========================================================================
    // FIELD MASKING TESTS (v1)
    // =========================================================================

    public function testFieldMasksEmpty(): void
    {
        $user = $this->createUser('admin');
        $this->assertEmpty($this->engine->getFieldMasks($user, []));
    }

    public function testFieldMasksStandardUser(): void
    {
        $standard = $this->createUser('standard');
        $classifications = [
            'name' => 'public',
            'email' => 'internal',
            'notes' => 'confidential',
            'ssn' => 'regulated',
            'password' => 'secret',
        ];

        $masks = $this->engine->getFieldMasks($standard, $classifications);
        // Standard (level 10) should mask: confidential (20+), regulated (30+), secret (999)
        $this->assertContains('notes', $masks);
        $this->assertContains('ssn', $masks);
        $this->assertContains('password', $masks);
        $this->assertNotContains('name', $masks);
        $this->assertNotContains('email', $masks);
    }

    public function testFieldMasksManagerUser(): void
    {
        $manager = $this->createUser('manager');
        $classifications = [
            'name' => 'public',
            'notes' => 'confidential',
            'ssn' => 'regulated',
            'password' => 'secret',
        ];

        $masks = $this->engine->getFieldMasks($manager, $classifications);
        // Manager (level 20) should mask: regulated (30+), secret (999)
        $this->assertContains('ssn', $masks);
        $this->assertContains('password', $masks);
        $this->assertNotContains('name', $masks);
        $this->assertNotContains('notes', $masks);
    }

    public function testFieldMasksAdminUser(): void
    {
        $admin = $this->createUser('admin');
        $classifications = [
            'name' => 'public',
            'ssn' => 'regulated',
            'password' => 'secret',
        ];

        $masks = $this->engine->getFieldMasks($admin, $classifications);
        // Admin (level 30) should only mask: secret (999)
        $this->assertContains('password', $masks);
        $this->assertNotContains('ssn', $masks);
        $this->assertNotContains('name', $masks);
    }

    public function testSecretFieldAlwaysMasked(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $classifications = ['password' => 'secret'];

        $masks = $this->engine->getFieldMasks($superAdmin, $classifications);
        // Even super admin can't see secret fields directly
        $this->assertContains('password', $masks);
    }

    // =========================================================================
    // TIME RESTRICTION TESTS (v1)
    // =========================================================================

    public function testAlwaysAllowedTimeRestriction(): void
    {
        $this->assertTrue($this->engine->isWithinAllowedTime(['type' => 'always']));
    }

    public function testUnknownTimeRestrictionDefaultsToAllow(): void
    {
        $this->assertTrue($this->engine->isWithinAllowedTime(['type' => 'unknown_type']));
    }

    public function testBusinessHoursRestriction(): void
    {
        $restriction = [
            'type' => 'business_hours',
            'timezone' => 'America/Chicago',
            'startHour' => 0,
            'endHour' => 24,
            'allowedDays' => [1, 2, 3, 4, 5, 6, 7],
        ];

        // With all hours and all days allowed, should always pass
        $this->assertTrue($this->engine->isWithinAllowedTime($restriction));
    }

    public function testBusinessHoursRestrictionNeverAllowed(): void
    {
        $restriction = [
            'type' => 'business_hours',
            'timezone' => 'America/Chicago',
            'startHour' => 0,
            'endHour' => 0, // No hours allowed
            'allowedDays' => [1, 2, 3, 4, 5, 6, 7],
        ];

        $this->assertFalse($this->engine->isWithinAllowedTime($restriction));
    }

    public function testDateRangeRestriction(): void
    {
        // Valid range: now is within
        $this->assertTrue($this->engine->isWithinAllowedTime([
            'type' => 'date_range',
            'startDate' => '2020-01-01',
            'endDate' => '2030-12-31',
        ]));

        // Expired range
        $this->assertFalse($this->engine->isWithinAllowedTime([
            'type' => 'date_range',
            'startDate' => '2020-01-01',
            'endDate' => '2020-12-31',
        ]));
    }

    // =========================================================================
    // MFA TESTS (v1)
    // =========================================================================

    public function testMfaNotRequiredDefaultsToFalse(): void
    {
        // When MFA is not required, re-verification should not be needed
        $this->assertFalse($this->engine->requiresMfaReVerification([]));
        $this->assertFalse($this->engine->requiresMfaReVerification(['requiresMfa' => false]));
    }

    public function testMfaRequiredWithoutSession(): void
    {
        // When MFA is required and no session verification exists
        $this->assertTrue($this->engine->requiresMfaReVerification([
            'requiresMfa' => true,
            'mfaMaxAge' => 300,
        ]));
    }

    // =========================================================================
    // RATE LIMITING TESTS (v1)
    // =========================================================================

    public function testDefaultRateLimitsExist(): void
    {
        $limits = $this->engine->getDefaultRateLimits();
        $this->assertArrayHasKey('query', $limits);
        $this->assertArrayHasKey('mutation', $limits);
        $this->assertArrayHasKey('reveal', $limits);
        $this->assertArrayHasKey('export', $limits);
    }

    public function testRateLimitStatusFormat(): void
    {
        $user = $this->createUser('standard', self::$testUserId);
        $status = $this->engine->getRateLimitStatus($user, 'test.query.list');

        $this->assertArrayHasKey('remaining', $status);
        $this->assertArrayHasKey('limit', $status);
        $this->assertArrayHasKey('used', $status);
        $this->assertArrayHasKey('resetAt', $status);
        $this->assertArrayHasKey('windowSeconds', $status);
    }

    public function testSuperAdminNeverRateLimited(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $this->assertFalse(
            $this->engine->isRateLimited($superAdmin, 'any.action', ['max' => 0, 'window' => 1])
        );
    }

    public function testRateLimitRecordAndCheck(): void
    {
        $user = $this->createUser('standard', self::$testUserId);
        $action = 'test.rate.limit.' . uniqid();

        // Should not be rate limited initially
        $this->assertFalse($this->engine->isRateLimited($user, $action, ['max' => 3, 'window' => 60]));

        // Record 3 hits
        $this->engine->recordRateHit($user, $action, ['max' => 3, 'window' => 60]);
        $this->engine->recordRateHit($user, $action, ['max' => 3, 'window' => 60]);
        $this->engine->recordRateHit($user, $action, ['max' => 3, 'window' => 60]);

        // Should now be rate limited
        $this->assertTrue($this->engine->isRateLimited($user, $action, ['max' => 3, 'window' => 60]));

        // Check status shows correct usage
        $status = $this->engine->getRateLimitStatus($user, $action, ['max' => 3, 'window' => 60]);
        $this->assertEquals(3, $status['used']);
        $this->assertEquals(0, $status['remaining']);
    }

    // =========================================================================
    // UTILITY TESTS
    // =========================================================================

    public function testGetAvailableRoles(): void
    {
        $roles = $this->engine->getAvailableRoles();
        $this->assertContains('guest', $roles);
        $this->assertContains('standard', $roles);
        $this->assertContains('manager', $roles);
        $this->assertContains('admin', $roles);
        $this->assertContains('super_admin', $roles);
        $this->assertCount(5, $roles);
    }

    public function testGetDefaultPermissionsForRole(): void
    {
        $perms = $this->engine->getDefaultPermissionsForRole('super_admin');
        $this->assertContains('*', $perms);

        $guestPerms = $this->engine->getDefaultPermissionsForRole('guest');
        $this->assertEmpty($guestPerms);
    }

    public function testDefaultRateLimitValues(): void
    {
        $limits = $this->engine->getDefaultRateLimits();

        $this->assertEquals(120, $limits['query']['max']);
        $this->assertEquals(60, $limits['query']['window']);
        $this->assertEquals(30, $limits['mutation']['max']);
        $this->assertEquals(5, $limits['reveal']['max']);
        $this->assertEquals(3, $limits['export']['max']);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Create a mock User object for testing
     * Uses PHPUnit mock to avoid DB constructor
     */
    private function createUser(string $role, int $id = 1, array $extraProps = []): User
    {
        $user = $this->createMock(User::class);
        $user->id = $id;
        $user->role = $role;
        $user->email = "test-{$role}@example.com";

        foreach ($extraProps as $prop => $value) {
            $user->$prop = $value;
        }

        return $user;
    }
}
