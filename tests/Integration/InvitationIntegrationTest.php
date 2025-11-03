<?php

namespace Tests\Integration;

use Hub\Invitation;
use Hub\User;
use Hub\Auth;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\TestDatabase;

/**
 * Integration tests for Invitation system
 * Tests the complete invitation lifecycle: creation, validation, acceptance, and user activation
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Invitation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\User::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Database::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Email::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\SiteSettings::class)]
class InvitationIntegrationTest extends TestCase
{
    private Invitation $invitation;
    private User $user;
    private string $uniqueId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uniqueId = uniqid('test_', true);
        $this->invitation = new Invitation();
        $this->user = new User();
        TestDatabase::beginTransaction();
    }

    /**
     * Helper: Generate unique email for testing
     */
    private function uniqueEmail(string $prefix = 'test'): string
    {
        return $prefix . '-' . $this->uniqueId . '@woodsonisd.net';
    }

    protected function tearDown(): void
    {
        TestDatabase::rollBack();
        parent::tearDown();
    }

    /**
     * Test complete invitation creation workflow
     */
    public function testCompleteInvitationCreationFlow(): void
    {
        $email = $this->uniqueEmail('newinvite');
        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        // Create invitation (returns invitation ID, generates token internally)
        $invitationId = $this->invitation->create($email, 'admin', $inviterUserId);

        // Verify invitation ID is returned
        $this->assertGreaterThan(0, $invitationId);

        // Verify invitation is in database - get by ID
        $db = TestDatabase::getConnection();
        $stmt = $db->prepare("SELECT * FROM invitations WHERE id = ?");
        $stmt->execute([$invitationId]);
        $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($invitation);
        $this->assertEquals($email, $invitation['email']);
        $this->assertEquals('admin', $invitation['role']);
        $this->assertEquals($inviterUserId, $invitation['invited_by']);
        $this->assertNull($invitation['used_at']);
        $this->assertNotEmpty($invitation['token']);
        $this->assertEquals(64, strlen($invitation['token'])); // bin2hex(random_bytes(32)) = 64 chars
    }

    /**
     * Test token validation flow
     */
    public function testTokenValidation(): void
    {
        $email = $this->uniqueEmail('validtoken');
        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        $invitationId = $this->invitation->create($email, 'staff', $inviterUserId);

        // Get token from database
        $db = TestDatabase::getConnection();
        $stmt = $db->prepare("SELECT token FROM invitations WHERE id = ?");
        $stmt->execute([$invitationId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $token = $result['token'];

        // Valid token should return invitation
        $invitation = $this->invitation->getByToken($token);
        $this->assertNotNull($invitation);
        $this->assertNull($invitation['used_at']);

        // Invalid token should return false (not null - PDO behavior)
        $this->assertFalse($this->invitation->getByToken('invalid-token-' . uniqid()));
    }

    /**
     * Test invitation acceptance workflow
     */
    public function testInvitationAcceptanceCreatesUser(): void
    {
        $email = $this->uniqueEmail('accept');
        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        $invitationId = $this->invitation->create($email, 'manager', $inviterUserId);

        // Get token before marking used
        $db = TestDatabase::getConnection();
        $stmt = $db->prepare("SELECT token FROM invitations WHERE id = ?");
        $stmt->execute([$invitationId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $token = $result['token'];

        // Accept invitation (markUsed takes invitation ID)
        $this->invitation->markUsed($invitationId);

        // Verify marked as used - getByToken should return false for used invitations (PDO behavior)
        $invitation = $this->invitation->getByToken($token);
        $this->assertFalse($invitation);

        // Double-check in database
        $stmt = $db->prepare("SELECT used_at FROM invitations WHERE id = ?");
        $stmt->execute([$invitationId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($result['used_at']);
    }

    /**
     * Test invitation expiration handling
     */
    public function testExpiredInvitationIsInvalid(): void
    {
        $email = $this->uniqueEmail('expired');
        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        // Create invitation with past expiration directly in database
        $db = TestDatabase::getConnection();
        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare("
            INSERT INTO invitations (email, token, role, invited_by, expires_at, created_at)
            VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 1 DAY), NOW())
        ");
        $stmt->execute([$email, $token, 'staff', $inviterUserId]);

        // Expired invitation should return false (getByToken checks expires_at > NOW() - PDO behavior)
        $invitation = $this->invitation->getByToken($token);
        $this->assertFalse($invitation);
    }

    /**
     * Test duplicate invitation handling
     */
    public function testDuplicateInvitationPrevention(): void
    {
        $email = $this->uniqueEmail('duplicate');
        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        // First invitation should succeed
        $invitationId = $this->invitation->create($email, 'staff', $inviterUserId);
        $this->assertGreaterThan(0, $invitationId);

        // Second invitation to same email should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Active invitation already exists');
        $this->invitation->create($email, 'staff', $inviterUserId);
    }

    /**
     * Test invitation for existing user prevention
     */
    public function testExistingUserInvitationPrevention(): void
    {
        $email = $this->uniqueEmail('existing');

        // Create existing user
        TestDatabase::createTestUser(['email' => $email, 'role' => 'staff']);

        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        // Invitation to existing user should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User already exists');
        $this->invitation->create($email, 'staff', $inviterUserId);
    }    /**
     * Test invitation list retrieval
     */
    public function testGetAllInvitations(): void
    {
        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        // Create multiple invitations
        $this->invitation->create($this->uniqueEmail('user1'), 'staff', $inviterUserId);
        $this->invitation->create($this->uniqueEmail('user2'), 'admin', $inviterUserId);
        $this->invitation->create($this->uniqueEmail('user3'), 'manager', $inviterUserId);

        // Get all invitations
        $invitations = $this->invitation->getAll();

        $this->assertIsArray($invitations);
        $this->assertGreaterThanOrEqual(3, count($invitations));

        // Verify structure
        foreach ($invitations as $inv) {
            $this->assertArrayHasKey('id', $inv);
            $this->assertArrayHasKey('email', $inv);
            $this->assertArrayHasKey('role', $inv);
            $this->assertArrayHasKey('token', $inv);
            $this->assertArrayHasKey('invited_by', $inv);
            $this->assertArrayHasKey('created_at', $inv);
            $this->assertArrayHasKey('expires_at', $inv);
        }
    }

    /**
     * Test user registration flow via invitation
     */
    public function testUserRegistrationViaInvitation(): void
    {
        $email = $this->uniqueEmail('register');
        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        // Create invitation
        $invitationId = $this->invitation->create($email, 'staff', $inviterUserId);

        // Get token to verify invitation exists
        $db = TestDatabase::getConnection();
        $stmt = $db->prepare("SELECT * FROM invitations WHERE id = ?");
        $stmt->execute([$invitationId]);
        $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($invitation);

        // Simulate user registration (normally done via OAuth)
        $stmt = $db->prepare("
            INSERT INTO users (email, name, role, google_id, is_active, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$email, 'New User', $invitation['role'], 'google-' . uniqid()]);
        $userId = $db->lastInsertId();

        // Mark invitation as used
        $this->invitation->markUsed($invitationId);

        // Verify user was created with correct role
        $user = $this->user->getById($userId);
        $this->assertNotNull($user);
        $this->assertEquals($email, $user['email']);
        $this->assertEquals('staff', $user['role']);
        $this->assertEquals(1, $user['is_active']);
    }    /**
     * Test invitation approval workflow for pending users
     */
    public function testPendingUserApprovalFlow(): void
    {
        $email = $this->uniqueEmail('pending');

        // Create pending user (is_active = 0)
        $db = TestDatabase::getConnection();
        $stmt = $db->prepare("
            INSERT INTO users (email, name, role, google_id, is_active, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$email, 'Pending User', 'staff', 'google-' . uniqid()]);
        $userId = $db->lastInsertId();

        // Verify user is pending
        $pendingUsers = $this->user->getPending();
        $this->assertGreaterThanOrEqual(1, count($pendingUsers));

        $found = false;
        foreach ($pendingUsers as $pending) {
            if ($pending['id'] == $userId) {
                $found = true;
                $this->assertEquals(0, $pending['is_active']);
            }
        }
        $this->assertTrue($found);

        // Approve user
        $approverUserId = TestDatabase::createTestUser(['role' => 'super_admin']);
        $this->user->approve($userId, $approverUserId);

        // Verify user is now active (role remains unchanged)
        $user = $this->user->getById($userId);
        $this->assertEquals(1, $user['is_active']);
        $this->assertEquals('staff', $user['role']); // Role unchanged by approve()
        $this->assertNotNull($user['approved_at']);
        $this->assertEquals($approverUserId, $user['approved_by']);
    }

    /**
     * Test invitation role assignment
     */
    public function testInvitationRoleAssignment(): void
    {
        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        // Test each valid role
        $roles = ['staff', 'manager', 'admin', 'super_admin'];

        foreach ($roles as $role) {
            $email = "role-{$role}-" . uniqid() . '@woodsonisd.net';
            $invitationId = $this->invitation->create($email, $role, $inviterUserId);

            // Get invitation from database to verify role
            $db = TestDatabase::getConnection();
            $stmt = $db->prepare("SELECT * FROM invitations WHERE id = ?");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);

            $this->assertEquals($role, $invitation['role']);
        }
    }

    /**
     * Test invitation with domain validation
     */
    public function testInvitationDomainValidation(): void
    {
        $inviterUserId = TestDatabase::createTestUser(['role' => 'super_admin']);

        // Valid domain (woodsonisd.net) - create() checks REQUIRE_DOMAIN_MATCH env var
        $validEmail = $this->uniqueEmail('valid');
        $invitationId = $this->invitation->create($validEmail, 'staff', $inviterUserId);
        $this->assertGreaterThan(0, $invitationId);

        // Note: Domain validation requires REQUIRE_DOMAIN_MATCH=true in .env
        // If not set, domain validation is skipped
        // This test verifies the method completes successfully with woodsonisd.net domain
    }

    /**
     * Test invitation metadata persistence
     */
    public function testInvitationMetadataPersistence(): void
    {
        $email = $this->uniqueEmail('metadata');
        $inviterUserId = TestDatabase::createTestUser([
            'email' => $this->uniqueEmail('inviter'),
            'name' => 'Super Admin User',
            'role' => 'super_admin'
        ]);

        $invitationId = $this->invitation->create($email, 'manager', $inviterUserId);

        // Get invitation from database
        $db = TestDatabase::getConnection();
        $stmt = $db->prepare("SELECT * FROM invitations WHERE id = ?");
        $stmt->execute([$invitationId]);
        $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Verify all metadata is stored
        $this->assertEquals($email, $invitation['email']);
        $this->assertEquals('manager', $invitation['role']);
        $this->assertEquals($inviterUserId, $invitation['invited_by']);
        $this->assertNotNull($invitation['created_at']);
        $this->assertNotNull($invitation['expires_at']);
        $this->assertNull($invitation['used_at']);

        // Verify expiration is ~7 days from creation (allow 6-7 days due to timing)
        $created = new \DateTime($invitation['created_at']);
        $expires = new \DateTime($invitation['expires_at']);
        $diff = $created->diff($expires);
        $this->assertGreaterThanOrEqual(6, $diff->days);
        $this->assertLessThanOrEqual(7, $diff->days);
    }
}
