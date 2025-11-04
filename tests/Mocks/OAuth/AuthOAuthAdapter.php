<?php

namespace Hub\Tests\Mocks\OAuth;

/**
 * Adapter to make mock OAuth providers compatible with Auth.php
 * 
 * This bridges the gap between our mock providers and the existing
 * Auth.php implementation which uses direct cURL calls to Google.
 */
class AuthOAuthAdapter
{
    private OAuthProviderInterface $provider;
    private array $mockResponses = [];

    public function __construct(OAuthProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Mock the getAccessToken cURL call
     * Simulates: POST https://oauth2.googleapis.com/token
     */
    public function mockGetAccessToken(string $code): array
    {
        try {
            return $this->provider->exchangeCodeForToken($code);
        } catch (\Exception $e) {
            return [
                'error' => 'invalid_grant',
                'error_description' => $e->getMessage()
            ];
        }
    }

    /**
     * Mock the getUserInfo cURL call
     * Simulates: GET https://www.googleapis.com/oauth2/v3/userinfo
     */
    public function mockGetUserInfo(string $accessToken): array
    {
        try {
            $profile = $this->provider->getUserProfile($accessToken);
            
            // Convert provider-specific format to Google format
            if ($this->provider->getProviderName() === 'google') {
                return $profile;
            } elseif ($this->provider->getProviderName() === 'microsoft') {
                // Convert Microsoft format to Google format
                return [
                    'sub' => $profile['id'],
                    'email' => $profile['mail'] ?? $profile['userPrincipalName'],
                    'name' => $profile['displayName'],
                    'given_name' => $profile['givenName'] ?? '',
                    'family_name' => $profile['surname'] ?? '',
                    'picture' => null, // Microsoft Graph doesn't return this by default
                ];
            }
            
            return $profile;
        } catch (\Exception $e) {
            throw new \Exception('Failed to get user info: ' . $e->getMessage());
        }
    }

    /**
     * Mock the checkGroupMembership call
     * Simulates: GET https://admin.googleapis.com/admin/directory/v1/groups
     */
    public function mockCheckGroupMembership(string $accessToken, string $userEmail, array $requiredGroups): bool
    {
        // Extract user ID from email for lookup
        $userId = strstr($userEmail, '@', true);
        
        $userGroups = $this->provider->getUserGroups($accessToken, $userId);
        
        foreach ($requiredGroups as $requiredGroup) {
            if (in_array($requiredGroup, $userGroups)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the underlying provider
     */
    public function getProvider(): OAuthProviderInterface
    {
        return $this->provider;
    }
}
