<?php

namespace Hub\Tests\Mocks\OAuth;

/**
 * Mock Google OAuth provider for testing
 * Simulates Google OAuth 2.0 + Directory API
 */
class MockGoogleOAuthProvider implements OAuthProviderInterface
{
    private array $users = [];
    private array $groups = [];
    private array $validTokens = [];
    private string $clientId;
    private string $redirectUri;

    public function __construct(string $clientId = 'mock-client-id', string $redirectUri = 'http://localhost/auth/callback')
    {
        $this->clientId = $clientId;
        $this->redirectUri = $redirectUri;
        $this->initializeDefaultUsers();
    }

    public function getAuthorizationUrl(string $state): string
    {
        return sprintf(
            'https://accounts.google.com/o/oauth2/v2/auth?client_id=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s',
            urlencode($this->clientId),
            urlencode($this->redirectUri),
            urlencode('openid email profile'),
            urlencode($state)
        );
    }

    public function exchangeCodeForToken(string $code): array
    {
        // Validate authorization code format
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
            throw new \Exception('Invalid authorization code');
        }

        // Generate mock access token
        $accessToken = 'ya29.mock_' . bin2hex(random_bytes(16));
        $this->validTokens[$accessToken] = [
            'code' => $code,
            'expires_at' => time() + 3600,
        ];

        return [
            'access_token' => $accessToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'openid email profile',
            'id_token' => $this->generateMockIdToken(),
        ];
    }

    public function getUserProfile(string $accessToken): array
    {
        if (!$this->validateToken($accessToken)) {
            throw new \Exception('Invalid or expired access token');
        }

        // Return user based on token's authorization code
        $tokenData = $this->validTokens[$accessToken] ?? null;
        if (!$tokenData) {
            throw new \Exception('Token not found');
        }

        // Map authorization codes to users
        $userId = $this->getUserIdFromCode($tokenData['code']);
        $user = $this->users[$userId] ?? $this->users['default'];

        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'verified_email' => true,
            'name' => $user['name'],
            'given_name' => $user['given_name'],
            'family_name' => $user['family_name'],
            'picture' => $user['picture'] ?? 'https://via.placeholder.com/150',
            'locale' => 'en',
            'hd' => $user['domain'] ?? null, // Hosted domain
        ];
    }

    public function getUserGroups(string $accessToken, string $userId): array
    {
        if (!$this->validateToken($accessToken)) {
            return [];
        }

        return $this->groups[$userId] ?? [];
    }

    public function validateToken(string $accessToken): bool
    {
        if (!isset($this->validTokens[$accessToken])) {
            return false;
        }

        $tokenData = $this->validTokens[$accessToken];
        return $tokenData['expires_at'] > time();
    }

    public function getProviderName(): string
    {
        return 'google';
    }

    // ========== Test Configuration Methods ==========

    /**
     * Add a test user to the mock provider
     */
    public function addUser(string $userId, array $userData): void
    {
        $this->users[$userId] = array_merge([
            'id' => $userId,
            'email' => $userId . '@example.com',
            'name' => 'Test User',
            'given_name' => 'Test',
            'family_name' => 'User',
            'domain' => 'example.com',
        ], $userData);
    }

    /**
     * Set group memberships for a user
     */
    public function setUserGroups(string $userId, array $groupEmails): void
    {
        $this->groups[$userId] = $groupEmails;
    }

    /**
     * Create an authorization code for a specific user
     */
    public function createAuthCodeForUser(string $userId): string
    {
        return 'auth_code_' . $userId . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Expire a token (for testing token expiration)
     */
    public function expireToken(string $accessToken): void
    {
        if (isset($this->validTokens[$accessToken])) {
            $this->validTokens[$accessToken]['expires_at'] = time() - 1;
        }
    }

    // ========== Private Helpers ==========

    private function initializeDefaultUsers(): void
    {
        // Default test user
        $this->addUser('default', [
            'email' => 'test@woodsonisd.net',
            'name' => 'Test User',
            'given_name' => 'Test',
            'family_name' => 'User',
            'domain' => 'woodsonisd.net',
        ]);

        // Super admin user
        $this->addUser('super_admin', [
            'email' => 'admin@woodsonisd.net',
            'name' => 'Super Admin',
            'given_name' => 'Super',
            'family_name' => 'Admin',
            'domain' => 'woodsonisd.net',
        ]);
        $this->setUserGroups('super_admin', ['admins@woodsonisd.net']);

        // External domain user (should be rejected)
        $this->addUser('external', [
            'email' => 'hacker@evil.com',
            'name' => 'External User',
            'given_name' => 'External',
            'family_name' => 'User',
            'domain' => 'evil.com',
        ]);
    }

    private function getUserIdFromCode(string $code): string
    {
        // Extract user ID from authorization code
        // Format: auth_code_{userId}_{random}
        if (preg_match('/^auth_code_(.+?)_[a-f0-9]+$/', $code, $matches)) {
            return $matches[1];
        }
        return 'default';
    }

    private function generateMockIdToken(): string
    {
        // Mock JWT token (header.payload.signature)
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iss' => 'https://accounts.google.com',
            'sub' => 'mock-user-id',
            'email' => 'test@woodsonisd.net',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 3600,
        ]));
        $signature = base64_encode('mock-signature');

        return "$header.$payload.$signature";
    }
}
