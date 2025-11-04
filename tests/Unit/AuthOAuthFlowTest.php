<?php

namespace Hub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Auth;
use Hub\Database;
use Hub\OAuthClient;
use Hub\Tests\Mocks\OAuth\MockGoogleOAuthProvider;
use Hub\Tests\Mocks\OAuth\AuthOAuthAdapter;

/**
 * Comprehensive OAuth flow tests for Auth.php
 * Tests authentication, user creation, invitation handling, and access control
 */
class AuthOAuthFlowTest extends TestCase
{
    private $db;
    private $mockProvider;
    private $oauthAdapter;
    private $oauthClient;
    private $inviterUserId;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset database
        $this->db = Database::getInstance();
        $this->db->execute("DELETE FROM users WHERE email LIKE '%@test-oauth.com'");
        $this->db->execute("DELETE FROM invitations WHERE email LIKE '%@test-oauth.com'");

        // Create valid inviter user (for foreign key constraints)
        $this->db->execute(
            "INSERT INTO users (google_id, email, name, role, is_active) VALUES (?, ?, ?, ?, ?)",
            ['inviter_google_id', 'inviter@test-oauth.com', 'Test Inviter', 'admin', 1]
        );
        $inviter = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", ['inviter@test-oauth.com']);
        $this->inviterUserId = $inviter['id'];

        // Setup mock OAuth provider
        $this->mockProvider = new MockGoogleOAuthProvider(
            'test-client-id',
            'http://localhost/auth/callback'
        );

        // Create OAuth adapter and client
        $this->oauthAdapter = new AuthOAuthAdapter($this->mockProvider);
        $this->oauthClient = new OAuthClient(
            'test-client-id',
            'test-client-secret',
            'http://localhost/auth/callback',
            $this->oauthAdapter
        );

        // Set test environment
        $_ENV['GOOGLE_CLIENT_ID'] = 'test-client-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'test-client-secret';
        $_ENV['GOOGLE_REDIRECT_URI'] = 'http://localhost/auth/callback';
        $_ENV['ALLOWED_DOMAINS'] = 'test-oauth.com';
        $_ENV['REQUIRE_DOMAIN_MATCH'] = 'true';
        $_ENV['SUPER_ADMIN_EMAIL'] = 'admin@test-oauth.com';
        $_ENV['ENABLE_GOOGLE_GROUPS'] = 'false';
    }

    protected function tearDown(): void
    {
        // Cleanup
        $this->db->execute("DELETE FROM users WHERE email LIKE '%@test-oauth.com'");
        $this->db->execute("DELETE FROM invitations WHERE email LIKE '%@test-oauth.com'");
        unset($_SESSION);
        parent::tearDown();
    }

    // ========== getAuthUrl() Tests ==========

    public function testGetAuthUrlWithDomainHint()
    {
        $_ENV['ALLOWED_DOMAINS'] = 'test-oauth.com,other.com';

        $auth = new Auth($this->oauthClient);
        $url = $auth->getAuthUrl();

        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertStringContainsString('hd=test-oauth.com', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
    }

    public function testGetAuthUrlWithoutDomainHint()
    {
        $_ENV['ALLOWED_DOMAINS'] = '';

        $auth = new Auth($this->oauthClient);
        $url = $auth->getAuthUrl();

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringNotContainsString('hd=', $url);
    }

    public function testGetAuthUrlIncludesCorrectScopes()
    {
        $auth = new Auth($this->oauthClient);
        $url = $auth->getAuthUrl();

        $this->assertStringContainsString('openid', $url);
        $this->assertStringContainsString('email', $url);
        $this->assertStringContainsString('profile', $url);
    }

    public function testGetAuthUrlIncludesRedirectUri()
    {
        $auth = new Auth($this->oauthClient);
        $url = $auth->getAuthUrl();

        $this->assertStringContainsString(
            urlencode('http://localhost/auth/callback'),
            $url
        );
    }

    // ========== handleCallback() Tests - Successful Flows ==========

    public function testHandleCallbackWithInvitation()
    {
        // Create invitation
        $this->db->execute(
            "INSERT INTO invitations (email, role, invited_by, token, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))",
            ['invited@test-oauth.com', 'admin', $this->inviterUserId, 'test-token-123']
        );

        $this->mockProvider->addUser('invited_user', [
            'email' => 'invited@test-oauth.com',
            'name' => 'Invited User',
            'domain' => 'test-oauth.com',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('invited_user');
        $auth = new Auth($this->oauthClient);

        $user = $auth->handleCallback($code);

        $this->assertEquals('admin', $user['role']);
        $this->assertEquals(1, $user['is_active']);
        $this->assertEquals('invited@test-oauth.com', $user['email']);

        // Verify invitation marked as used
        $invitation = $this->db->fetchOne(
            "SELECT * FROM invitations WHERE email = ?",
            ['invited@test-oauth.com']
        );
        $this->assertNotNull($invitation['used_at']);
    }

    public function testHandleCallbackReturningUser()
    {
        // Create existing active user
        $this->db->execute(
            "INSERT INTO users (google_id, email, name, role, is_active) VALUES (?, ?, ?, ?, ?)",
            ['existing_google_id', 'existing@test-oauth.com', 'Existing User', 'admin', 1]
        );

        $this->mockProvider->addUser('existing_user', [
            'id' => 'existing_google_id',
            'email' => 'existing@test-oauth.com',
            'name' => 'Existing User',
            'domain' => 'test-oauth.com',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('existing_user');
        $auth = new Auth($this->oauthClient);

        $user = $auth->handleCallback($code);

        $this->assertEquals('existing@test-oauth.com', $user['email']);
        $this->assertEquals('admin', $user['role']);

        // Verify last_login was updated in database
        $dbUser = $this->db->fetchOne(
            "SELECT last_login FROM users WHERE email = ?",
            ['existing@test-oauth.com']
        );
        $this->assertNotNull($dbUser['last_login']);
    }

    public function testHandleCallbackSuperAdminLogin()
    {
        $_ENV['SUPER_ADMIN_EMAIL'] = 'admin@test-oauth.com';

        $this->mockProvider->addUser('admin_user', [
            'email' => 'admin@test-oauth.com',
            'name' => 'Admin User',
            'domain' => 'test-oauth.com',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('admin_user');
        $auth = new Auth($this->oauthClient);

        $user = $auth->handleCallback($code);

        $this->assertEquals('super_admin', $user['role']);
        $this->assertEquals(1, $user['is_active']);
    }

    public function testHandleCallbackProfilePictureUpdate()
    {
        // Create invitation
        $this->db->execute(
            "INSERT INTO invitations (email, role, invited_by, token, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))",
            ['picture@test-oauth.com', 'staff', $this->inviterUserId, 'test-token-pic']
        );

        $this->mockProvider->addUser('picture_user', [
            'email' => 'picture@test-oauth.com',
            'name' => 'Picture User',
            'domain' => 'test-oauth.com',
            'picture' => 'https://example.com/photo.jpg',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('picture_user');
        $auth = new Auth($this->oauthClient);

        $user = $auth->handleCallback($code);

        $this->assertEquals('https://example.com/photo.jpg', $user['picture']);

        // Verify stored in database
        $dbUser = $this->db->fetchOne(
            "SELECT picture FROM users WHERE email = ?",
            ['picture@test-oauth.com']
        );
        $this->assertEquals('https://example.com/photo.jpg', $dbUser['picture']);
    }

    // ========== handleCallback() Tests - Failure Scenarios ==========

    public function testHandleCallbackInvalidAuthorizationCode()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid authorization code');

        $auth = new Auth($this->oauthClient);
        $auth->handleCallback('invalid code with spaces');
    }

    public function testHandleCallbackExternalDomainRejection()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email domain not allowed');

        $_ENV['REQUIRE_DOMAIN_MATCH'] = 'true';
        $_ENV['ALLOWED_DOMAINS'] = 'test-oauth.com';

        $this->mockProvider->addUser('external', [
            'email' => 'hacker@evil.com',
            'name' => 'External User',
            'domain' => 'evil.com',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('external');
        $auth = new Auth($this->oauthClient);

        $auth->handleCallback($code);
    }

    public function testHandleCallbackUserWithoutInvitationBlocked()
    {
        $this->expectException(\Exception::class);

        $this->mockProvider->addUser('no_invitation', [
            'email' => 'noinvite@test-oauth.com',
            'name' => 'No Invitation',
            'domain' => 'test-oauth.com',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('no_invitation');
        $auth = new Auth($this->oauthClient);

        // This will create user with is_active=0 and throw exception
        $auth->handleCallback($code);
    }

    public function testHandleCallbackInactiveAccountRejection()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('deactivated');

        // Create deactivated user (is_active = 0, has approved_by)
        $this->db->execute(
            "INSERT INTO users (google_id, email, name, role, is_active, approved_by) VALUES (?, ?, ?, ?, ?, ?)",
            ['deactivated_google_id', 'deactivated@test-oauth.com', 'Deactivated User', 'staff', 0, $this->inviterUserId]
        );

        $this->mockProvider->addUser('deactivated_user', [
            'id' => 'deactivated_google_id',
            'email' => 'deactivated@test-oauth.com',
            'name' => 'Deactivated User',
            'domain' => 'test-oauth.com',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('deactivated_user');
        $auth = new Auth($this->oauthClient);

        $auth->handleCallback($code);
    }

    // ========== Session & Login Tests ==========

    public function testHandleCallbackCreatesValidSession()
    {
        // Use super admin for guaranteed active user
        $_ENV['SUPER_ADMIN_EMAIL'] = 'session@test-oauth.com';

        $this->mockProvider->addUser('session_user', [
            'email' => 'session@test-oauth.com',
            'name' => 'Session User',
            'domain' => 'test-oauth.com',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('session_user');
        $auth = new Auth($this->oauthClient);

        $user = $auth->handleCallback($code);

        // Verify session variables
        $this->assertTrue($_SESSION['logged_in'] ?? false);
        $this->assertEquals($user['id'], $_SESSION['user_id'] ?? null);
        $this->assertEquals($user['email'], $_SESSION['email'] ?? null);
        $this->assertEquals($user['role'], $_SESSION['role'] ?? null);
    }

    public function testHandleCallbackLastLoginTimestampUpdated()
    {
        // Create user with old last_login
        $this->db->execute(
            "INSERT INTO users (google_id, email, name, role, is_active, last_login) VALUES (?, ?, ?, ?, ?, ?)",
            ['login_test_id', 'logintest@test-oauth.com', 'Login Test', 'staff', 1, '2020-01-01 00:00:00']
        );

        $this->mockProvider->addUser('login_test', [
            'id' => 'login_test_id',
            'email' => 'logintest@test-oauth.com',
            'name' => 'Login Test',
            'domain' => 'test-oauth.com',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('login_test');
        $auth = new Auth($this->oauthClient);

        $user = $auth->handleCallback($code);

        // Verify last_login was updated
        $dbUser = $this->db->fetchOne(
            "SELECT last_login FROM users WHERE email = ?",
            ['logintest@test-oauth.com']
        );

        $this->assertNotEquals('2020-01-01 00:00:00', $dbUser['last_login']);
        $this->assertNotNull($dbUser['last_login']);
    }
}
