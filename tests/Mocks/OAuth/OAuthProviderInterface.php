<?php

namespace Hub\Tests\Mocks\OAuth;

/**
 * Interface for OAuth provider mocks
 * Supports Google, Microsoft, and future providers
 */
interface OAuthProviderInterface
{
    /**
     * Generate authorization URL
     * @param string $state CSRF state token
     * @return string Authorization URL
     */
    public function getAuthorizationUrl(string $state): string;

    /**
     * Exchange authorization code for access token
     * @param string $code Authorization code
     * @return array Token response ['access_token' => '...', 'expires_in' => 3600, ...]
     * @throws \Exception on invalid code
     */
    public function exchangeCodeForToken(string $code): array;

    /**
     * Get user profile from provider
     * @param string $accessToken Access token
     * @return array User data ['id' => '...', 'email' => '...', 'name' => '...', ...]
     * @throws \Exception on invalid token
     */
    public function getUserProfile(string $accessToken): array;

    /**
     * Get user's group memberships (if supported)
     * @param string $accessToken Access token
     * @param string $userId User ID
     * @return array List of group emails/IDs
     */
    public function getUserGroups(string $accessToken, string $userId): array;

    /**
     * Validate access token
     * @param string $accessToken Access token to validate
     * @return bool True if valid, false otherwise
     */
    public function validateToken(string $accessToken): bool;

    /**
     * Get provider name (google, microsoft, etc.)
     * @return string Provider identifier
     */
    public function getProviderName(): string;
}
