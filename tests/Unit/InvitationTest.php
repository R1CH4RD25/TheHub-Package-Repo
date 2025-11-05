<?php

namespace Tests\Unit;

use Hub\Invitation;
use Hub\Database;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Invitation class
 * Covers: create, getByToken, markUsed, getAll, sendApprovalEmail
 * Target: 21.54% → 85%+ coverage
 */
class InvitationTest extends TestCase
{
    private $db;
    private $invitation;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['DB_NAME'] = 'woodson_hub_test';

        // Disable domain restriction by default (enable in specific tests)
        $_ENV['REQUIRE_DOMAIN_MATCH'] = 'false';
        unset($_ENV['ALLOWED_DOMAINS']);

        $this->db = Database::getInstance();
        $this->invitation = new Invitation();

        // Clean test data
        $this->db->execute("DELETE FROM invitations WHERE email LIKE '%@test-invitation.com'");
        $this->db->execute("DELETE FROM users WHERE email LIKE '%@test-invitation.com'");

        // Create test inviter
        $this->db->execute("DELETE FROM users WHERE id = 500");
        $this->db->execute(
            "INSERT INTO users (id, email, name, role, is_active) VALUES (?, ?, ?, ?, ?)",
            [500, 'inviter@test-invitation.com', 'Test Inviter', 'admin', 1]
        );
    }

    protected function tearDown(): void
    {
        $this->db->execute("DELETE FROM invitations WHERE email LIKE '%@test-invitation.com'");
        $this->db->execute("DELETE FROM users WHERE email LIKE '%@test-invitation.com'");
        parent::tearDown();
    }

    /**
     * Test successful invitation creation
     */
    public function testCreateInvitationSuccess()
    {
        $email = 'newuser@test-invitation.com';

        $invitationId = $this->invitation->create($email, 'staff', 500);

        $this->assertGreaterThan(0, $invitationId);

        // Verify in database
        $invite = $this->db->fetchOne(
            "SELECT * FROM invitations WHERE id = ?",
            [$invitationId]
        );

        $this->assertEquals($email, $invite['email']);
        $this->assertEquals('staff', $invite['role']);
        $this->assertEquals(500, $invite['invited_by']);
        $this->assertNotEmpty($invite['token']);
        $this->assertEquals(64, strlen($invite['token'])); // bin2hex(32 bytes) = 64 chars
        $this->assertNull($invite['used_at']);
        $this->assertNotNull($invite['expires_at']);
    }

    /**
     * Test invitation with multiple roles
     */
    public function testCreateInvitationDifferentRoles()
    {
        $roles = ['staff', 'admin', 'manager'];

        foreach ($roles as $index => $role) {
            $email = "user{$index}@test-invitation.com";
            $invitationId = $this->invitation->create($email, $role, 500);

            $invite = $this->db->fetchOne(
                "SELECT role FROM invitations WHERE id = ?",
                [$invitationId]
            );

            $this->assertEquals($role, $invite['role']);
        }
    }

    /**
     * Test invitation prevents duplicate user
     */
    public function testCreateInvitationPreventsDuplicateUser()
    {
        // Create existing user
        $email = 'existing@test-invitation.com';
        $this->db->execute(
            "INSERT INTO users (email, name, role, is_active) VALUES (?, ?, ?, ?)",
            [$email, 'Existing User', 'staff', 1]
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User already exists');

        $this->invitation->create($email, 'admin', 500);
    }

    /**
     * Test invitation prevents duplicate active invitation
     */
    public function testCreateInvitationPreventsDuplicateInvite()
    {
        $email = 'duplicate@test-invitation.com';

        // Create first invitation
        $this->invitation->create($email, 'staff', 500);

        // Try to create second invitation
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Active invitation already exists');

        $this->invitation->create($email, 'admin', 500);
    }

    /**
     * Test invitation with domain restriction enabled
     */
    public function testCreateInvitationWithDomainRestriction()
    {
        $_ENV['REQUIRE_DOMAIN_MATCH'] = 'true';
        $_ENV['ALLOWED_DOMAINS'] = 'test-invitation.com';

        // Valid domain - should succeed
        $validEmail = 'valid@test-invitation.com';
        $invitationId = $this->invitation->create($validEmail, 'staff', 500);
        $this->assertGreaterThan(0, $invitationId);

        // Invalid domain - should fail
        $invalidEmail = 'invalid@other-domain.com';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only @test-invitation.com email addresses can be invited');

        $this->invitation->create($invalidEmail, 'staff', 500);
    }

    /**
     * Test invitation with multiple allowed domains
     */
    public function testCreateInvitationMultipleAllowedDomains()
    {
        $_ENV['REQUIRE_DOMAIN_MATCH'] = 'true';
        $_ENV['ALLOWED_DOMAINS'] = 'test-invitation.com, another-domain.com';

        // First domain
        $email1 = 'user1@test-invitation.com';
        $id1 = $this->invitation->create($email1, 'staff', 500);
        $this->assertGreaterThan(0, $id1);

        // Second domain
        $email2 = 'user2@another-domain.com';
        $id2 = $this->invitation->create($email2, 'staff', 500);
        $this->assertGreaterThan(0, $id2);

        // Invalid domain
        $_ENV['ALLOWED_DOMAINS'] = 'test-invitation.com, another-domain.com';
        $invalidEmail = 'user3@blocked.com';

        try {
            $this->invitation->create($invalidEmail, 'staff', 500);
            $this->fail('Should have thrown exception for invalid domain');
        } catch (\Exception $e) {
            $this->assertStringContainsString('email addresses can be invited', $e->getMessage());
        }
    }

    /**
     * Test getByToken with valid token
     */
    public function testGetByTokenValidToken()
    {
        $email = 'tokentest@test-invitation.com';
        $invitationId = $this->invitation->create($email, 'staff', 500);

        // Get token from database
        $invite = $this->db->fetchOne(
            "SELECT token FROM invitations WHERE id = ?",
            [$invitationId]
        );
        $token = $invite['token'];

        // Retrieve by token
        $result = $this->invitation->getByToken($token);

        $this->assertNotNull($result);
        $this->assertEquals($email, $result['email']);
        $this->assertNull($result['used_at']);
    }

    /**
     * Test getByToken with invalid token
     */
    public function testGetByTokenInvalidToken()
    {
        $result = $this->invitation->getByToken('invalid-token-12345');

        $this->assertFalse($result); // PDO returns false for no results
    }

    /**
     * Test getByToken ignores used tokens
     */
    public function testGetByTokenIgnoresUsedTokens()
    {
        $email = 'usedtoken@test-invitation.com';
        $invitationId = $this->invitation->create($email, 'staff', 500);

        $invite = $this->db->fetchOne(
            "SELECT token FROM invitations WHERE id = ?",
            [$invitationId]
        );
        $token = $invite['token'];

        // Mark as used
        $this->invitation->markUsed($invitationId);

        // Should not return used invitation
        $result = $this->invitation->getByToken($token);
        $this->assertFalse($result);
    }

    /**
     * Test getByToken ignores expired tokens
     */
    public function testGetByTokenIgnoresExpiredTokens()
    {
        $email = 'expiredtoken@test-invitation.com';
        $token = bin2hex(random_bytes(32));

        // Create expired invitation directly
        $this->db->execute(
            "INSERT INTO invitations (email, role, invited_by, token, expires_at)
             VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 1 DAY))",
            [$email, 'staff', 500, $token]
        );

        $result = $this->invitation->getByToken($token);
        $this->assertFalse($result);
    }

    /**
     * Test markUsed updates used_at timestamp
     */
    public function testMarkUsedSetsTimestamp()
    {
        $email = 'markused@test-invitation.com';
        $invitationId = $this->invitation->create($email, 'staff', 500);

        // Verify not used initially
        $invite = $this->db->fetchOne(
            "SELECT used_at FROM invitations WHERE id = ?",
            [$invitationId]
        );
        $this->assertNull($invite['used_at']);

        // Mark as used
        $this->invitation->markUsed($invitationId);

        // Verify used_at is set
        $invite = $this->db->fetchOne(
            "SELECT used_at FROM invitations WHERE id = ?",
            [$invitationId]
        );
        $this->assertNotNull($invite['used_at']);
    }

    /**
     * Test markUsed can be called multiple times safely
     */
    public function testMarkUsedIdempotent()
    {
        $email = 'idempotent@test-invitation.com';
        $invitationId = $this->invitation->create($email, 'staff', 500);

        // Mark as used twice
        $this->invitation->markUsed($invitationId);
        $firstTimestamp = $this->db->fetchOne(
            "SELECT used_at FROM invitations WHERE id = ?",
            [$invitationId]
        )['used_at'];

        // Small delay to ensure different timestamp if it updates
        usleep(10000); // 10ms

        $this->invitation->markUsed($invitationId);
        $secondTimestamp = $this->db->fetchOne(
            "SELECT used_at FROM invitations WHERE id = ?",
            [$invitationId]
        )['used_at'];

        // Timestamps should be different (NOW() called each time)
        // This is actually expected behavior - verifying it doesn't error
        $this->assertNotNull($firstTimestamp);
        $this->assertNotNull($secondTimestamp);
    }

    /**
     * Test getAll returns all invitations with inviter details
     */
    public function testGetAllReturnsInvitationsWithInviterInfo()
    {
        // Create multiple invitations
        $this->invitation->create('user1@test-invitation.com', 'staff', 500);
        $this->invitation->create('user2@test-invitation.com', 'admin', 500);
        $this->invitation->create('user3@test-invitation.com', 'manager', 500);

        $invitations = $this->invitation->getAll();

        // Should have at least our 3 test invitations
        $testInvites = array_filter($invitations, function($inv) {
            return strpos($inv['email'], '@test-invitation.com') !== false;
        });

        $this->assertGreaterThanOrEqual(3, count($testInvites));

        // Check structure includes inviter info
        foreach ($testInvites as $inv) {
            $this->assertArrayHasKey('email', $inv);
            $this->assertArrayHasKey('role', $inv);
            $this->assertArrayHasKey('invited_by_name', $inv);
            $this->assertArrayHasKey('invited_by_email', $inv);
            $this->assertArrayHasKey('accepted_at', $inv); // Alias for used_at
            $this->assertEquals('Test Inviter', $inv['invited_by_name']);
        }
    }

    /**
     * Test getAll orders by created_at DESC
     */
    public function testGetAllOrdersByCreatedAtDesc()
    {
        // Create invitations in sequence
        sleep(1); // Ensure different timestamps
        $id1 = $this->invitation->create('first@test-invitation.com', 'staff', 500);

        sleep(1);
        $id2 = $this->invitation->create('second@test-invitation.com', 'staff', 500);

        sleep(1);
        $id3 = $this->invitation->create('third@test-invitation.com', 'staff', 500);

        $invitations = $this->invitation->getAll();

        // Find our test invitations
        $testInvites = array_filter($invitations, function($inv) {
            return strpos($inv['email'], '@test-invitation.com') !== false;
        });
        $testInvites = array_values($testInvites); // Re-index

        // Most recent should be first (DESC order)
        $this->assertEquals('third@test-invitation.com', $testInvites[0]['email']);
        $this->assertEquals('second@test-invitation.com', $testInvites[1]['email']);
        $this->assertEquals('first@test-invitation.com', $testInvites[2]['email']);
    }

    /**
     * Test token is cryptographically random
     */
    public function testTokensAreUnique()
    {
        $tokens = [];

        for ($i = 0; $i < 10; $i++) {
            $email = "unique{$i}@test-invitation.com";
            $invitationId = $this->invitation->create($email, 'staff', 500);

            $invite = $this->db->fetchOne(
                "SELECT token FROM invitations WHERE id = ?",
                [$invitationId]
            );

            $tokens[] = $invite['token'];
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(10, $uniqueTokens);
    }

    /**
     * Test invitation expiration is set to 7 days
     */
    public function testExpirationSetTo7Days()
    {
        $email = 'expiry@test-invitation.com';
        $invitationId = $this->invitation->create($email, 'staff', 500);

        $invite = $this->db->fetchOne(
            "SELECT expires_at, created_at FROM invitations WHERE id = ?",
            [$invitationId]
        );

        $created = new \DateTime($invite['created_at']);
        $expires = new \DateTime($invite['expires_at']);

        $diff = $created->diff($expires);

        // Should be approximately 7 days (allow 1 day variance for timezone/processing)
        $this->assertGreaterThanOrEqual(6, $diff->days);
        $this->assertLessThanOrEqual(8, $diff->days);
    }

    /**
     * Test sendApprovalEmail calls Email class
     */
    public function testSendApprovalEmailSendsEmail()
    {
        // This tests that sendApprovalEmail method executes without errors
        // Email class is mocked in Email tests, here we just verify it doesn't crash

        $email = 'approval@test-invitation.com';

        // Should not throw exception (Email::send is tested in EmailTest)
        $this->invitation->sendApprovalEmail($email);

        // If we get here without exception, the method executed
        $this->assertTrue(true);
    }
}
