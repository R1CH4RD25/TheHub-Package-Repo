<?php

namespace Hub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Database;
use Hub\Tests\Mocks\OAuth\MockMicrosoftOAuthProvider;
use Hub\Tests\Mocks\OAuth\AuthOAuthAdapter;

/**
 * Microsoft OAuth flow tests
 * Tests Azure AD authentication, Graph API profile fetching, and MS-specific features
 *
 * NOTE: These tests validate mock provider functionality.
 * Integration with Auth.php would require Auth refactoring to support multiple providers.
 */
class MicrosoftOAuthTest extends TestCase
{
    private $mockProvider;
    private $adapter;
    private $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProvider = new MockMicrosoftOAuthProvider(
            'test-ms-client-id',
            'http://localhost/auth/ms-callback'
        );

        $this->adapter = new AuthOAuthAdapter($this->mockProvider);
        $this->db = Database::getInstance();
    }

    // ========== Microsoft Provider Tests ==========

    public function testMicrosoftAuthorizationUrl()
    {
        $url = $this->mockProvider->getAuthorizationUrl('test-state-123');

        $this->assertStringContainsString('login.microsoftonline.com', $url);
        $this->assertStringContainsString('client_id=test-ms-client-id', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('scope=', $url);
        $this->assertStringContainsString('openid', $url);
        $this->assertStringContainsString('email', $url);
        $this->assertStringContainsString('profile', $url);
        $this->assertStringContainsString('state=test-state-123', $url);
    }

    public function testMicrosoftTokenExchange()
    {
        // Note: Mock provider defaults email to {userId}@example.com if not provided
        $this->mockProvider->addUser('msuser', [
            'mail' => 'msuser@company.com',
            'displayName' => 'MS User',
            'givenName' => 'MS',
            'surname' => 'User',
            'jobTitle' => 'Developer',
            'officeLocation' => 'Building 42',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('msuser');
        $tokenData = $this->mockProvider->exchangeCodeForToken($code);

        $this->assertArrayHasKey('access_token', $tokenData);
        $this->assertArrayHasKey('token_type', $tokenData);
        $this->assertArrayHasKey('expires_in', $tokenData);
        $this->assertEquals('Bearer', $tokenData['token_type']);
    }

    public function testMicrosoftUserProfile()
    {
        $this->mockProvider->addUser('profiletest', [
            'email' => 'profiletest@company.com',
            'name' => 'Profile Test',  // Mock provider uses 'name' internally
            'given_name' => 'Profile',
            'family_name' => 'Test',
            'job_title' => 'Senior Developer',
            'office' => 'Remote',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('profiletest');
        $tokenData = $this->mockProvider->exchangeCodeForToken($code);

        $profile = $this->mockProvider->getUserProfile($tokenData['access_token']);

        $this->assertEquals('profiletest@company.com', $profile['mail']);
        $this->assertEquals('Profile Test', $profile['displayName']);
        $this->assertEquals('Profile', $profile['givenName']);
        $this->assertEquals('Test', $profile['surname']);
        $this->assertEquals('Senior Developer', $profile['jobTitle']);
        $this->assertEquals('Remote', $profile['officeLocation']);
    }

    public function testMicrosoftUserProfileConversionToGoogleFormat()
    {
        $this->mockProvider->addUser('converttest', [
            'email' => 'converttest@company.com',
            'name' => 'Convert Test',  // Use 'name' not 'displayName'
            'given_name' => 'Convert',
            'family_name' => 'Test',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('converttest');
        $tokenData = $this->mockProvider->exchangeCodeForToken($code);

        // Use adapter to convert to Google format
        $userInfo = $this->adapter->mockGetUserInfo($tokenData['access_token']);

        // Should be in Google OAuth format
        $this->assertArrayHasKey('sub', $userInfo); // Google uses 'sub' for user ID
        $this->assertArrayHasKey('email', $userInfo);
        $this->assertArrayHasKey('name', $userInfo);
        $this->assertArrayHasKey('given_name', $userInfo);
        $this->assertArrayHasKey('family_name', $userInfo);

        $this->assertEquals('converttest', $userInfo['sub']); // ID mapped to 'sub'
        $this->assertEquals('converttest@company.com', $userInfo['email']);
        $this->assertEquals('Convert Test', $userInfo['name']);
        $this->assertEquals('Convert', $userInfo['given_name']);
        $this->assertEquals('Test', $userInfo['family_name']);
    }    public function testMicrosoftUserPrincipalNameFallback()
    {
        // Test when userPrincipalName differs from mail
        $this->mockProvider->addUser('upntest', [
            'email' => 'upntest@company.onmicrosoft.com',  // Sets both mail and userPrincipalName
            'displayName' => 'UPN Test',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('upntest');
        $tokenData = $this->mockProvider->exchangeCodeForToken($code);

        $profile = $this->mockProvider->getUserProfile($tokenData['access_token']);

        // Should have userPrincipalName set to email
        $this->assertEquals('upntest@company.onmicrosoft.com', $profile['userPrincipalName']);
        $this->assertEquals('upntest@company.onmicrosoft.com', $profile['mail']);
    }

    public function testMicrosoftInvalidAuthCode()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid authorization code');

        $this->mockProvider->exchangeCodeForToken('invalid code');
    }

    public function testMicrosoftInvalidToken()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired access token');

        $this->mockProvider->getUserProfile('invalid_token');
    }

    public function testMicrosoftExpiredToken()
    {
        $this->mockProvider->addUser('expired', [
            'mail' => 'expired@company.com',
            'displayName' => 'Expired User',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('expired');
        $tokenData = $this->mockProvider->exchangeCodeForToken($code);

        // Expire the token
        $this->mockProvider->expireToken($tokenData['access_token']);

        $this->expectException(\Exception::class);
        $this->mockProvider->getUserProfile($tokenData['access_token']);
    }

    // ========== Microsoft Groups Tests ==========

    public function testMicrosoftGroupMembership()
    {
        $this->mockProvider->addUser('groupuser', [
            'mail' => 'groupuser@company.com',
            'displayName' => 'Group User',
        ]);

        $this->mockProvider->setUserGroups('groupuser', [
            'developers@company.com',
            'staff@company.com'
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('groupuser');
        $tokenData = $this->mockProvider->exchangeCodeForToken($code);

        $groups = $this->mockProvider->getUserGroups($tokenData['access_token'], 'groupuser');

        $this->assertCount(2, $groups);
        $this->assertContains('developers@company.com', $groups);
        $this->assertContains('staff@company.com', $groups);
    }

    public function testMicrosoftNoGroups()
    {
        $this->mockProvider->addUser('nogroups', [
            'mail' => 'nogroups@company.com',
            'displayName' => 'No Groups User',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('nogroups');
        $tokenData = $this->mockProvider->exchangeCodeForToken($code);

        $groups = $this->mockProvider->getUserGroups($tokenData['access_token'], 'nogroups');

        $this->assertIsArray($groups);
        $this->assertEmpty($groups);
    }

    // ========== Provider Identification Tests ==========

    public function testMicrosoftProviderName()
    {
        $this->assertEquals('microsoft', $this->mockProvider->getProviderName());
    }

    public function testMicrosoftTokenValidation()
    {
        $this->mockProvider->addUser('validtoken', [
            'mail' => 'validtoken@company.com',
            'displayName' => 'Valid Token',
        ]);

        $code = $this->mockProvider->createAuthCodeForUser('validtoken');
        $tokenData = $this->mockProvider->exchangeCodeForToken($code);

        $this->assertTrue($this->mockProvider->validateToken($tokenData['access_token']));
        $this->assertFalse($this->mockProvider->validateToken('invalid_token'));
    }
}
