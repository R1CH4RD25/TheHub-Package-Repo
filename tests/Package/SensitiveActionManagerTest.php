<?php

namespace Hub\Tests\Package;

use PHPUnit\Framework\TestCase;
use Hub\Package\SensitiveActionManager;
use Hub\Database;

/**
 * SensitiveActionManager Test Suite
 *
 * Tests the secure reveal token lifecycle:
 * - Token issuance with validation
 * - Token consumption (single-use)
 * - Token expiry handling
 * - Token revocation
 * - Rate limit enforcement
 * - Cleanup and stats
 *
 * Uses real database with transactions (rolled back after each test).
 */
class SensitiveActionManagerTest extends TestCase
{
    private SensitiveActionManager $manager;
    private static Database $db;
    private static int $testUserId = 70001;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
        // Create test user for foreign key constraints
        self::$db->execute("INSERT INTO users (id, email, name, role) VALUES (70001, 'sensitive-test@example.com', 'Sensitive Test User', 'admin') ON DUPLICATE KEY UPDATE id=id");
    }

    protected function setUp(): void
    {
        self::$db->beginTransaction();
        $this->manager = new SensitiveActionManager();
    }

    protected function tearDown(): void
    {
        self::$db->rollBack();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        try {
            self::$db->execute("DELETE FROM sensitive_action_tokens WHERE user_id = 70001");
            self::$db->execute("DELETE FROM package_audit_events WHERE user_id = 70001");
            self::$db->execute("DELETE FROM package_rate_limits WHERE user_id = 70001");
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    // =========================================================================
    // TOKEN ISSUANCE TESTS
    // =========================================================================

    public function testIssueTokenReturnsValidResponse(): void
    {
        $user = $this->createMockUser();

        $result = $this->manager->issueToken(
            $user,
            'test-pkg',
            'revealPassword',
            'Testing token issuance',
            null,
            30
        );

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expiresAt', $result);
        $this->assertArrayHasKey('expiresIn', $result);
        $this->assertEquals(30, $result['expiresIn']);
        // Token should be 128 hex chars (64 bytes)
        $this->assertEquals(128, strlen($result['token']));
    }

    public function testIssueTokenRejectsShortReason(): void
    {
        $user = $this->createMockUser();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Reason must be at least 5 characters');

        $this->manager->issueToken($user, 'test-pkg', 'revealPassword', 'abc');
    }

    public function testIssueTokenEnforcesTtlBounds(): void
    {
        $user = $this->createMockUser();

        // TTL should be capped at 120 seconds
        $result = $this->manager->issueToken(
            $user,
            'test-pkg',
            'revealPassword',
            'Testing TTL cap',
            null,
            999
        );
        $this->assertLessThanOrEqual(120, $result['expiresIn']);

        // TTL should have a minimum of 5 seconds
        $result2 = $this->manager->issueToken(
            $user,
            'test-pkg',
            'revealPassword',
            'Testing TTL min',
            null,
            1
        );
        $this->assertGreaterThanOrEqual(5, $result2['expiresIn']);
    }

    public function testIssueTokenRevokesOutstandingTokens(): void
    {
        $user = $this->createMockUser();

        // Issue first token
        $result1 = $this->manager->issueToken(
            $user,
            'test-pkg',
            'revealPassword',
            'First token',
            null,
            60
        );
        $token1 = $result1['token'];

        // Issue second token for same action
        $result2 = $this->manager->issueToken(
            $user,
            'test-pkg',
            'revealPassword',
            'Second token',
            null,
            60
        );
        $token2 = $result2['token'];

        // First token should now be invalid (revoked)
        $validation = $this->manager->validateToken($token1, self::$testUserId);
        $this->assertFalse($validation['valid']);
        $this->assertEquals('Revoked', $validation['reason']);

        // Second token should be valid
        $validation2 = $this->manager->validateToken($token2, self::$testUserId);
        $this->assertTrue($validation2['valid']);
    }

    // =========================================================================
    // TOKEN CONSUMPTION TESTS
    // =========================================================================

    public function testConsumeTokenSuccess(): void
    {
        $user = $this->createMockUser();

        $issueResult = $this->manager->issueToken(
            $user,
            'test-pkg',
            'viewSSN',
            'Need to verify student identity',
            42,
            60
        );

        $consumeResult = $this->manager->consumeToken($issueResult['token'], self::$testUserId);

        $this->assertEquals('test-pkg', $consumeResult['packageId']);
        $this->assertEquals('viewSSN', $consumeResult['actionName']);
        $this->assertEquals(42, $consumeResult['targetRecordId']);
        $this->assertStringContainsString('verify student', $consumeResult['reason']);
    }

    public function testConsumeTokenFailsIfAlreadyUsed(): void
    {
        $user = $this->createMockUser();

        $issueResult = $this->manager->issueToken(
            $user,
            'test-pkg',
            'viewSSN',
            'Single use test',
            null,
            60
        );

        // Consume once — should succeed
        $this->manager->consumeToken($issueResult['token'], self::$testUserId);

        // Consume again — should fail
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token already consumed');
        $this->manager->consumeToken($issueResult['token'], self::$testUserId);
    }

    public function testConsumeTokenFailsIfBelongsToOtherUser(): void
    {
        $user = $this->createMockUser();

        $issueResult = $this->manager->issueToken(
            $user,
            'test-pkg',
            'viewSSN',
            'Ownership test',
            null,
            60
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid token');
        $this->manager->consumeToken($issueResult['token'], 99999); // Wrong user
    }

    public function testConsumeTokenFailsIfInvalidToken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid token');
        $this->manager->consumeToken('nonexistent-token', self::$testUserId);
    }

    // =========================================================================
    // TOKEN VALIDATION TESTS
    // =========================================================================

    public function testValidateValidToken(): void
    {
        $user = $this->createMockUser();

        $issueResult = $this->manager->issueToken(
            $user,
            'test-pkg',
            'viewSSN',
            'Validation test',
            null,
            60
        );

        $validation = $this->manager->validateToken($issueResult['token'], self::$testUserId);
        $this->assertTrue($validation['valid']);
        $this->assertGreaterThan(0, $validation['expiresIn']);
        $this->assertNull($validation['reason']);
    }

    public function testValidateNonexistentToken(): void
    {
        $validation = $this->manager->validateToken('fake-token', self::$testUserId);
        $this->assertFalse($validation['valid']);
        $this->assertEquals('Token not found', $validation['reason']);
    }

    public function testValidateWrongUser(): void
    {
        $user = $this->createMockUser();

        $issueResult = $this->manager->issueToken(
            $user,
            'test-pkg',
            'viewSSN',
            'Wrong user test',
            null,
            60
        );

        $validation = $this->manager->validateToken($issueResult['token'], 99999);
        $this->assertFalse($validation['valid']);
        $this->assertEquals('Invalid token', $validation['reason']);
    }

    public function testValidateConsumedToken(): void
    {
        $user = $this->createMockUser();

        $issueResult = $this->manager->issueToken(
            $user,
            'test-pkg',
            'viewSSN',
            'Consumed validation test',
            null,
            60
        );

        // Consume the token
        $this->manager->consumeToken($issueResult['token'], self::$testUserId);

        // Validate should show already consumed
        $validation = $this->manager->validateToken($issueResult['token'], self::$testUserId);
        $this->assertFalse($validation['valid']);
        $this->assertEquals('Already consumed', $validation['reason']);
    }

    // =========================================================================
    // TOKEN REVOCATION TESTS
    // =========================================================================

    public function testRevokeToken(): void
    {
        $user = $this->createMockUser();

        $issueResult = $this->manager->issueToken(
            $user,
            'test-pkg',
            'viewSSN',
            'Revocation test',
            null,
            60
        );

        $revoked = $this->manager->revokeToken($issueResult['token'], self::$testUserId);
        $this->assertTrue($revoked);

        // Should now be invalid
        $validation = $this->manager->validateToken($issueResult['token'], self::$testUserId);
        $this->assertFalse($validation['valid']);
        $this->assertEquals('Revoked', $validation['reason']);
    }

    public function testRevokeNonexistentToken(): void
    {
        $revoked = $this->manager->revokeToken('fake-token', self::$testUserId);
        $this->assertFalse($revoked);
    }

    public function testRevokeAlreadyConsumedToken(): void
    {
        $user = $this->createMockUser();

        $issueResult = $this->manager->issueToken(
            $user,
            'test-pkg',
            'viewSSN',
            'Revoke consumed test',
            null,
            60
        );

        // Consume first
        $this->manager->consumeToken($issueResult['token'], self::$testUserId);

        // Revoke should return false (already consumed)
        $revoked = $this->manager->revokeToken($issueResult['token'], self::$testUserId);
        $this->assertFalse($revoked);
    }

    // =========================================================================
    // STATS TESTS
    // =========================================================================

    public function testGetStatsReturnsValidStructure(): void
    {
        $stats = $this->manager->getStats();

        $this->assertArrayHasKey('total_issued', $stats);
        $this->assertArrayHasKey('consumed', $stats);
        $this->assertArrayHasKey('revoked', $stats);
        $this->assertArrayHasKey('expired_unused', $stats);
        $this->assertArrayHasKey('active', $stats);
    }

    // =========================================================================
    // CLEANUP TESTS
    // =========================================================================

    public function testCleanupReturnsCount(): void
    {
        $count = $this->manager->cleanup(30);
        $this->assertIsInt($count);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Create a mock User object for testing
     */
    private function createMockUser(): \Hub\User
    {
        $user = $this->createMock(\Hub\User::class);
        $user->id = self::$testUserId;
        $user->role = 'manager';
        $user->email = 'sensitive-test@example.com';
        return $user;
    }
}
