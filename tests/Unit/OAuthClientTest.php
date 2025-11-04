<?php

namespace Hub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\OAuthClient;
use Hub\Tests\Mocks\OAuth\MockGoogleOAuthProvider;
use Hub\Tests\Mocks\OAuth\AuthOAuthAdapter;

/**
 * Comprehensive tests for OAuthClient abstraction layer
 * Tests mock adapter integration, error handling, and production scenarios
 */
class OAuthClientTest extends TestCase
{
    private $mockProvider;
    private $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProvider = new MockGoogleOAuthProvider(
            'test-client-id',
            'http://localhost/callback'
        );

        $this->adapter = new AuthOAuthAdapter($this->mockProvider);
    }

    // ========== getAccessToken() Tests ==========

    public function testGetAccessTokenWithMockAdapter()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        // Create auth code
        $this->mockProvider->addUser('test_user', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('test_user');

        $tokenData = $client->getAccessToken($code);

        $this->assertIsArray($tokenData);
        $this->assertArrayHasKey('access_token', $tokenData);
        $this->assertArrayHasKey('expires_in', $tokenData);
        $this->assertArrayHasKey('token_type', $tokenData);
        $this->assertEquals('Bearer', $tokenData['token_type']);
    }

    public function testGetAccessTokenInvalidCodeFormat()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        // Code with invalid format (spaces not allowed in regex)
        $result = $client->getAccessToken('invalid code with spaces');

        // Mock adapter returns error structure (like real Google OAuth)
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('invalid_grant', $result['error']);
        $this->assertStringContainsString('Invalid authorization code', $result['error_description']);
    }    public function testGetAccessTokenReturnsValidStructure()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        $this->mockProvider->addUser('struct_test', [
            'email' => 'struct@example.com',
            'name' => 'Struct Test',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('struct_test');
        $tokenData = $client->getAccessToken($code);

        // Verify complete token structure
        $this->assertArrayHasKey('access_token', $tokenData);
        $this->assertArrayHasKey('expires_in', $tokenData);
        $this->assertArrayHasKey('token_type', $tokenData);
        $this->assertArrayHasKey('scope', $tokenData);
        $this->assertArrayHasKey('id_token', $tokenData);
        $this->assertEquals('Bearer', $tokenData['token_type']);
        $this->assertEquals(3600, $tokenData['expires_in']);
    }

    // ========== getUserInfo() Tests ==========

    public function testGetUserInfoWithValidToken()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        $this->mockProvider->addUser('user_info_test', [
            'email' => 'userinfo@example.com',
            'name' => 'User Info Test',
            'given_name' => 'User',
            'family_name' => 'Test',
            'picture' => 'https://example.com/pic.jpg',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('user_info_test');
        $tokenData = $client->getAccessToken($code);

        $userInfo = $client->getUserInfo($tokenData['access_token']);

        $this->assertEquals('userinfo@example.com', $userInfo['email']);
        $this->assertEquals('User Info Test', $userInfo['name']);
        $this->assertEquals('User', $userInfo['given_name']);
        $this->assertEquals('Test', $userInfo['family_name']);
        $this->assertEquals('https://example.com/pic.jpg', $userInfo['picture']);
        $this->assertArrayHasKey('sub', $userInfo); // Google uses 'sub' for user ID
    }

    public function testGetUserInfoInvalidToken()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired access token');

        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        $client->getUserInfo('invalid_token_12345');
    }

    public function testGetUserInfoExpiredToken()
    {
        $this->expectException(\Exception::class);

        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        $this->mockProvider->addUser('expired_test', [
            'email' => 'expired@example.com',
            'name' => 'Expired User',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('expired_test');
        $tokenData = $client->getAccessToken($code);

        // Expire the token
        $this->mockProvider->expireToken($tokenData['access_token']);

        $client->getUserInfo($tokenData['access_token']);
    }

    // ========== checkGroupMembership() Tests ==========

    public function testCheckGroupMembershipUserInGroup()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        // IMPORTANT: userId must match email prefix for AuthOAuthAdapter
        // Email: member@example.com → userId: member
        $this->mockProvider->addUser('member', [
            'email' => 'member@example.com',
            'name' => 'Group Member',
        ]);

        $this->mockProvider->setUserGroups('member', [
            'admins@example.com',
            'staff@example.com'
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('member');
        $tokenData = $client->getAccessToken($code);

        $isMember = $client->checkGroupMembership(
            $tokenData['access_token'],
            'member@example.com',
            ['admins@example.com']
        );

        $this->assertTrue($isMember);
    }

    public function testCheckGroupMembershipUserNotInGroup()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        $this->mockProvider->addUser('nonmember', [
            'email' => 'nonmember@example.com',
            'name' => 'Non Member',
        ]);

        $this->mockProvider->setUserGroups('nonmember', [
            'staff@example.com'
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('nonmember');
        $tokenData = $client->getAccessToken($code);

        $isMember = $client->checkGroupMembership(
            $tokenData['access_token'],
            'nonmember@example.com',
            ['admins@example.com', 'managers@example.com']
        );

        $this->assertFalse($isMember);
    }

    public function testCheckGroupMembershipMultipleGroups()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        $this->mockProvider->addUser('multi', [
            'email' => 'multi@example.com',
            'name' => 'Multi Group User',
        ]);

        $this->mockProvider->setUserGroups('multi', [
            'admins@example.com',
            'managers@example.com',
            'staff@example.com'
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('multi');
        $tokenData = $client->getAccessToken($code);

        // Check if in any of the required groups
        $isMember = $client->checkGroupMembership(
            $tokenData['access_token'],
            'multi@example.com',
            ['managers@example.com', 'developers@example.com']
        );

        $this->assertTrue($isMember);
    }

    public function testCheckGroupMembershipNoGroups()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        $this->mockProvider->addUser('nogroups', [
            'email' => 'nogroups@example.com',
            'name' => 'No Groups User',
        ]);

        // Don't set any groups

        $code = $this->mockProvider->createAuthCodeForUser('nogroups');
        $tokenData = $client->getAccessToken($code);

        $isMember = $client->checkGroupMembership(
            $tokenData['access_token'],
            'nogroups@example.com',
            ['admins@example.com']
        );

        $this->assertFalse($isMember);
    }

    public function testCheckGroupMembershipEmptyRequiredGroups()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        $this->mockProvider->addUser('test', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('test');
        $tokenData = $client->getAccessToken($code);

        // Empty required groups should return false
        $isMember = $client->checkGroupMembership(
            $tokenData['access_token'],
            'test@example.com',
            []
        );

        $this->assertFalse($isMember);
    }

    // ========== Constructor & Configuration Tests ==========

    public function testConstructorWithoutMockAdapter()
    {
        $client = new OAuthClient(
            'production-client-id',
            'production-secret',
            'https://production.com/callback'
        );

        // Verify object created successfully
        $this->assertInstanceOf(OAuthClient::class, $client);
    }

    public function testConstructorWithMockAdapter()
    {
        $client = new OAuthClient(
            'test-client-id',
            'test-secret',
            'http://localhost/callback',
            $this->adapter
        );

        // Verify mock adapter is used
        $this->assertInstanceOf(OAuthClient::class, $client);

        // Quick test to ensure mock is actually being used
        $this->mockProvider->addUser('quick', [
            'email' => 'quick@example.com',
            'name' => 'Quick Test',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('quick');
        $tokenData = $client->getAccessToken($code);

        $this->assertArrayHasKey('access_token', $tokenData);
    }
}
