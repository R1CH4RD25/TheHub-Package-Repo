<?php

namespace Hub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Tests\Mocks\OAuth\MockGoogleOAuthProvider;
use Hub\Tests\Mocks\OAuth\MockMicrosoftOAuthProvider;

/**
 * Test OAuth mock providers work correctly
 * This validates the testing infrastructure itself
 */
class OAuthMockProvidersTest extends TestCase
{
    // ========== Google OAuth Mock Tests ==========

    public function testGoogleMockGeneratesAuthUrl()
    {
        $provider = new MockGoogleOAuthProvider();
        $state = 'test-csrf-state';
        
        $url = $provider->getAuthorizationUrl($state);
        
        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('state=' . urlencode($state), $url);
        $this->assertStringContainsString('client_id=', $url);
        $this->assertStringContainsString('scope=', $url);
    }

    public function testGoogleMockExchangesCodeForToken()
    {
        $provider = new MockGoogleOAuthProvider();
        $code = $provider->createAuthCodeForUser('test_user');
        
        $tokenResponse = $provider->exchangeCodeForToken($code);
        
        $this->assertArrayHasKey('access_token', $tokenResponse);
        $this->assertArrayHasKey('expires_in', $tokenResponse);
        $this->assertArrayHasKey('id_token', $tokenResponse);
        $this->assertEquals(3600, $tokenResponse['expires_in']);
        $this->assertEquals('Bearer', $tokenResponse['token_type']);
    }

    public function testGoogleMockRejectsInvalidAuthCode()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid authorization code');
        
        $provider = new MockGoogleOAuthProvider();
        $provider->exchangeCodeForToken('invalid code with spaces');
    }

    public function testGoogleMockReturnsUserProfile()
    {
        $provider = new MockGoogleOAuthProvider();
        $provider->addUser('custom_user', [
            'email' => 'john.doe@woodsonisd.net',
            'name' => 'John Doe',
            'given_name' => 'John',
            'family_name' => 'Doe',
        ]);
        
        $code = $provider->createAuthCodeForUser('custom_user');
        $tokenResponse = $provider->exchangeCodeForToken($code);
        $profile = $provider->getUserProfile($tokenResponse['access_token']);
        
        $this->assertEquals('john.doe@woodsonisd.net', $profile['email']);
        $this->assertEquals('John Doe', $profile['name']);
        $this->assertEquals('John', $profile['given_name']);
        $this->assertEquals('Doe', $profile['family_name']);
        $this->assertTrue($profile['verified_email']);
    }

    public function testGoogleMockRejectsExpiredToken()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired access token');
        
        $provider = new MockGoogleOAuthProvider();
        $code = $provider->createAuthCodeForUser('test_user');
        $tokenResponse = $provider->exchangeCodeForToken($code);
        
        // Expire the token
        $provider->expireToken($tokenResponse['access_token']);
        
        // Should throw exception
        $provider->getUserProfile($tokenResponse['access_token']);
    }

    public function testGoogleMockReturnsUserGroups()
    {
        $provider = new MockGoogleOAuthProvider();
        $provider->addUser('admin_user', [
            'email' => 'admin@woodsonisd.net',
        ]);
        $provider->setUserGroups('admin_user', [
            'admins@woodsonisd.net',
            'it-staff@woodsonisd.net',
        ]);
        
        $code = $provider->createAuthCodeForUser('admin_user');
        $tokenResponse = $provider->exchangeCodeForToken($code);
        $groups = $provider->getUserGroups($tokenResponse['access_token'], 'admin_user');
        
        $this->assertCount(2, $groups);
        $this->assertContains('admins@woodsonisd.net', $groups);
        $this->assertContains('it-staff@woodsonisd.net', $groups);
    }

    public function testGoogleMockValidatesTokens()
    {
        $provider = new MockGoogleOAuthProvider();
        $code = $provider->createAuthCodeForUser('test_user');
        $tokenResponse = $provider->exchangeCodeForToken($code);
        
        $this->assertTrue($provider->validateToken($tokenResponse['access_token']));
        $this->assertFalse($provider->validateToken('invalid-token'));
        
        // Expire and revalidate
        $provider->expireToken($tokenResponse['access_token']);
        $this->assertFalse($provider->validateToken($tokenResponse['access_token']));
    }

    // ========== Microsoft OAuth Mock Tests ==========

    public function testMicrosoftMockGeneratesAuthUrl()
    {
        $provider = new MockMicrosoftOAuthProvider();
        $state = 'test-csrf-state';
        
        $url = $provider->getAuthorizationUrl($state);
        
        $this->assertStringContainsString('login.microsoftonline.com', $url);
        $this->assertStringContainsString('state=' . urlencode($state), $url);
        $this->assertStringContainsString('client_id=', $url);
        $this->assertStringContainsString('scope=', $url);
    }

    public function testMicrosoftMockExchangesCodeForToken()
    {
        $provider = new MockMicrosoftOAuthProvider();
        $code = $provider->createAuthCodeForUser('test_user');
        
        $tokenResponse = $provider->exchangeCodeForToken($code);
        
        $this->assertArrayHasKey('access_token', $tokenResponse);
        $this->assertArrayHasKey('expires_in', $tokenResponse);
        $this->assertArrayHasKey('id_token', $tokenResponse);
        $this->assertArrayHasKey('refresh_token', $tokenResponse);
        $this->assertEquals(3600, $tokenResponse['expires_in']);
        $this->assertEquals('Bearer', $tokenResponse['token_type']);
    }

    public function testMicrosoftMockReturnsUserProfile()
    {
        $provider = new MockMicrosoftOAuthProvider();
        $provider->addUser('custom_user', [
            'email' => 'jane.smith@woodsonisd.onmicrosoft.com',
            'name' => 'Jane Smith',
            'given_name' => 'Jane',
            'family_name' => 'Smith',
            'job_title' => 'Teacher',
        ]);
        
        $code = $provider->createAuthCodeForUser('custom_user');
        $tokenResponse = $provider->exchangeCodeForToken($code);
        $profile = $provider->getUserProfile($tokenResponse['access_token']);
        
        $this->assertEquals('jane.smith@woodsonisd.onmicrosoft.com', $profile['mail']);
        $this->assertEquals('Jane Smith', $profile['displayName']);
        $this->assertEquals('Jane', $profile['givenName']);
        $this->assertEquals('Smith', $profile['surname']);
        $this->assertEquals('Teacher', $profile['jobTitle']);
    }

    public function testMicrosoftMockReturnsUserGroups()
    {
        $provider = new MockMicrosoftOAuthProvider();
        $provider->addUser('admin_user', [
            'email' => 'admin@woodsonisd.onmicrosoft.com',
        ]);
        $provider->setUserGroups('admin_user', [
            'Global Admins',
            'IT Support',
        ]);
        
        $code = $provider->createAuthCodeForUser('admin_user');
        $tokenResponse = $provider->exchangeCodeForToken($code);
        $groups = $provider->getUserGroups($tokenResponse['access_token'], 'admin_user');
        
        $this->assertCount(2, $groups);
        $this->assertContains('Global Admins', $groups);
        $this->assertContains('IT Support', $groups);
    }

    public function testMicrosoftMockValidatesTokens()
    {
        $provider = new MockMicrosoftOAuthProvider();
        $code = $provider->createAuthCodeForUser('test_user');
        $tokenResponse = $provider->exchangeCodeForToken($code);
        
        $this->assertTrue($provider->validateToken($tokenResponse['access_token']));
        $this->assertFalse($provider->validateToken('invalid-token'));
        
        // Expire and revalidate
        $provider->expireToken($tokenResponse['access_token']);
        $this->assertFalse($provider->validateToken($tokenResponse['access_token']));
    }

    // ========== Cross-Provider Tests ==========

    public function testBothProvidersReturnCorrectNames()
    {
        $google = new MockGoogleOAuthProvider();
        $microsoft = new MockMicrosoftOAuthProvider();
        
        $this->assertEquals('google', $google->getProviderName());
        $this->assertEquals('microsoft', $microsoft->getProviderName());
    }

    public function testBothProvidersGenerateUniqueTokens()
    {
        $google = new MockGoogleOAuthProvider();
        $microsoft = new MockMicrosoftOAuthProvider();
        
        $googleCode = $google->createAuthCodeForUser('user1');
        $microsoftCode = $microsoft->createAuthCodeForUser('user1');
        
        $googleToken = $google->exchangeCodeForToken($googleCode);
        $microsoftToken = $microsoft->exchangeCodeForToken($microsoftCode);
        
        $this->assertNotEquals($googleToken['access_token'], $microsoftToken['access_token']);
    }
}
