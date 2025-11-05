<?php

namespace Tests\Unit;

use Hub\User;
use Hub\Database;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private $user;
    private $db;
    private $testUserId;
    private $testAdminId;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->user = new User();

        // Test user IDs in safe range
        $this->testUserId = 700;
        $this->testAdminId = 701;

        // Clean slate
        $this->db->execute("DELETE FROM users WHERE id >= 700");

        // Create test users
        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$this->testUserId, 'test-google-700', 'testuser@example.com', 'Test User', 'staff', 1]
        );

        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$this->testAdminId, 'test-google-701', 'admin@example.com', 'Test Admin', 'admin', 1]
        );
    }

    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM users WHERE id >= 700");
    }

    /**
     * Test getAll returns all users ordered by name
     */
    public function testGetAllReturnsUsersOrderedByName()
    {
        $users = $this->user->getAll();

        $this->assertIsArray($users);
        $this->assertNotEmpty($users);

        // Verify ordering by checking first user has name <= next user
        for ($i = 0; $i < count($users) - 1; $i++) {
            $this->assertLessThanOrEqual(
                $users[$i + 1]['name'],
                $users[$i]['name'],
                'Users should be ordered by name ascending'
            );
        }
    }

    /**
     * Test getAll includes our test users
     */
    public function testGetAllIncludesTestUsers()
    {
        $users = $this->user->getAll();
        $ids = array_column($users, 'id');

        $this->assertContains($this->testUserId, $ids);
        $this->assertContains($this->testAdminId, $ids);
    }

    /**
     * Test getById returns correct user
     */
    public function testGetByIdReturnsCorrectUser()
    {
        $user = $this->user->getById($this->testUserId);

        $this->assertIsArray($user);
        $this->assertEquals($this->testUserId, $user['id']);
        $this->assertEquals('testuser@example.com', $user['email']);
        $this->assertEquals('Test User', $user['name']);
        $this->assertEquals('staff', $user['role']);
        $this->assertEquals(1, $user['is_active']);
    }

    /**
     * Test getById returns null for non-existent user
     */
    public function testGetByIdReturnsNullForNonExistent()
    {
        $user = $this->user->getById(999999);

        $this->assertFalse($user);
    }

    /**
     * Test updateRole changes user role
     */
    public function testUpdateRoleChangesRole()
    {
        $stmt = $this->user->updateRole($this->testUserId, 'principal');

        $this->assertInstanceOf('PDOStatement', $stmt);

        // Verify change persisted
        $user = $this->user->getById($this->testUserId);
        $this->assertEquals('principal', $user['role']);
    }

    /**
     * Test updateRole to all valid roles
     */
    public function testUpdateRoleAllValidRoles()
    {
        $roles = ['staff', 'admin', 'super_admin', 'principal', 'counselor', 'business_manager'];

        foreach ($roles as $role) {
            $stmt = $this->user->updateRole($this->testUserId, $role);
            $this->assertInstanceOf('PDOStatement', $stmt);

            $user = $this->user->getById($this->testUserId);
            $this->assertEquals($role, $user['role']);
        }
    }    /**
     * Test deactivate sets is_active to false
     */
    public function testDeactivateSetsIsActiveFalse()
    {
        $stmt = $this->user->deactivate($this->testUserId);

        $this->assertInstanceOf('PDOStatement', $stmt);

        // Verify deactivation
        $user = $this->user->getById($this->testUserId);
        $this->assertEquals(0, $user['is_active']);
    }

    /**
     * Test deactivate is idempotent
     */
    public function testDeactivateIdempotent()
    {
        $this->user->deactivate($this->testUserId);
        $stmt = $this->user->deactivate($this->testUserId);

        $this->assertInstanceOf('PDOStatement', $stmt);

        $user = $this->user->getById($this->testUserId);
        $this->assertEquals(0, $user['is_active']);
    }

    /**
     * Test activate sets is_active to true
     */
    public function testActivateSetsIsActiveTrue()
    {
        // First deactivate
        $this->user->deactivate($this->testUserId);

        // Then activate
        $stmt = $this->user->activate($this->testUserId);

        $this->assertInstanceOf('PDOStatement', $stmt);

        // Verify activation
        $user = $this->user->getById($this->testUserId);
        $this->assertEquals(1, $user['is_active']);
    }

    /**
     * Test activate is idempotent
     */
    public function testActivateIdempotent()
    {
        $stmt = $this->user->activate($this->testUserId);

        $this->assertInstanceOf('PDOStatement', $stmt);

        $user = $this->user->getById($this->testUserId);
        $this->assertEquals(1, $user['is_active']);
    }

    /**
     * Test approve activates user and records approval
     */
    public function testApproveActivatesUserAndRecordsApproval()
    {
        // Create inactive user (pending)
        $pendingId = 702;
        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$pendingId, 'test-google-702', 'pending@example.com', 'Pending User', 'staff', 0]
        );

        // Approve
        $stmt = $this->user->approve($pendingId, $this->testAdminId);

        $this->assertInstanceOf('PDOStatement', $stmt);

        // Verify approval
        $user = $this->user->getById($pendingId);
        $this->assertEquals(1, $user['is_active']);
        $this->assertEquals($this->testAdminId, $user['approved_by']);
        $this->assertNotNull($user['approved_at']);

        // Cleanup
        $this->db->execute("DELETE FROM users WHERE id = ?", [$pendingId]);
    }

    /**
     * Test approve sets approved_at timestamp
     */
    public function testApproveTimestamp()
    {
        $pendingId = 703;
        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$pendingId, 'test-google-703', 'pending2@example.com', 'Pending User 2', 'staff', 0]
        );

        $before = time();
        $stmt = $this->user->approve($pendingId, $this->testAdminId);
        $this->assertInstanceOf('PDOStatement', $stmt);

        $user = $this->user->getById($pendingId);

        // approved_at should be set (not null)
        $this->assertNotNull($user['approved_at']);

        // Should be a valid datetime string
        $approvedAt = strtotime($user['approved_at']);
        $this->assertIsInt($approvedAt);
        $this->assertGreaterThan(0, $approvedAt);

        // Cleanup
        $this->db->execute("DELETE FROM users WHERE id = ?", [$pendingId]);
    }    /**
     * Test getPending returns only inactive users
     */
    public function testGetPendingReturnsOnlyInactive()
    {
        // Create pending users
        $pending1 = 704;
        $pending2 = 705;

        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$pending1, 'test-google-704', 'pending3@example.com', 'Pending 3', 'staff', 0]
        );

        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL 1 DAY)",
            [$pending2, 'test-google-705', 'pending4@example.com', 'Pending 4', 'staff', 0]
        );

        $pending = $this->user->getPending();

        $this->assertIsArray($pending);

        // All should be inactive
        foreach ($pending as $user) {
            $this->assertEquals(0, $user['is_active']);
        }

        // Should include our test pending users
        $ids = array_column($pending, 'id');
        $this->assertContains($pending1, $ids);
        $this->assertContains($pending2, $ids);

        // Cleanup
        $this->db->execute("DELETE FROM users WHERE id IN (?, ?)", [$pending1, $pending2]);
    }

    /**
     * Test getPending ordering by created_at DESC
     */
    public function testGetPendingOrdering()
    {
        $pending1 = 706;
        $pending2 = 707;

        // Older one first
        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL 2 DAY)",
            [$pending1, 'test-google-706', 'old@example.com', 'Old Pending', 'staff', 0]
        );

        // Newer one second
        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$pending2, 'test-google-707', 'new@example.com', 'New Pending', 'staff', 0]
        );

        $pending = $this->user->getPending();

        // Find our test users in results
        $testUsers = array_filter($pending, function($u) use ($pending1, $pending2) {
            return $u['id'] == $pending1 || $u['id'] == $pending2;
        });

        $testUsers = array_values($testUsers);

        // Newer should come first (DESC order)
        $this->assertCount(2, $testUsers);
        $this->assertEquals($pending2, $testUsers[0]['id']);
        $this->assertEquals($pending1, $testUsers[1]['id']);

        // Cleanup
        $this->db->execute("DELETE FROM users WHERE id IN (?, ?)", [$pending1, $pending2]);
    }

    /**
     * Test getPending excludes active users
     */
    public function testGetPendingExcludesActive()
    {
        $pending = $this->user->getPending();
        $ids = array_column($pending, 'id');

        // Our active test users should not be in pending
        $this->assertNotContains($this->testUserId, $ids);
        $this->assertNotContains($this->testAdminId, $ids);
    }

    /**
     * Test delete removes user
     */
    public function testDeleteRemovesUser()
    {
        $toDelete = 708;
        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$toDelete, 'test-google-708', 'delete@example.com', 'To Delete', 'staff', 1]
        );

        // Verify exists
        $user = $this->user->getById($toDelete);
        $this->assertNotNull($user);

        // Delete
        $stmt = $this->user->delete($toDelete);
        $this->assertInstanceOf('PDOStatement', $stmt);

        // Verify deleted
        $user = $this->user->getById($toDelete);
        $this->assertFalse($user);
    }

    /**
     * Test delete is safe for non-existent user
     */
    public function testDeleteSafeForNonExistent()
    {
        $stmt = $this->user->delete(999999);

        // Should not throw exception
        $this->assertInstanceOf('PDOStatement', $stmt);
    }

    /**
     * Test multiple operations in sequence
     */
    public function testUserLifecycle()
    {
        $userId = 709;

        // 1. Create pending user
        $this->db->execute(
            "INSERT INTO users (id, google_id, email, name, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$userId, 'test-google-709', 'lifecycle@example.com', 'Lifecycle User', 'staff', 0]
        );

        // 2. Verify in pending
        $pending = $this->user->getPending();
        $ids = array_column($pending, 'id');
        $this->assertContains($userId, $ids);

        // 3. Approve
        $this->user->approve($userId, $this->testAdminId);
        $user = $this->user->getById($userId);
        $this->assertEquals(1, $user['is_active']);

        // 4. Update role
        $this->user->updateRole($userId, 'counselor');
        $user = $this->user->getById($userId);
        $this->assertEquals('counselor', $user['role']);

        // 5. Deactivate
        $this->user->deactivate($userId);
        $user = $this->user->getById($userId);
        $this->assertEquals(0, $user['is_active']);

        // 6. Reactivate
        $this->user->activate($userId);
        $user = $this->user->getById($userId);
        $this->assertEquals(1, $user['is_active']);

        // 7. Delete
        $this->user->delete($userId);
        $user = $this->user->getById($userId);
        $this->assertFalse($user);
    }
}
