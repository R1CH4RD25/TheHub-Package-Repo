<?php

namespace Hub\Tests\Mocks\OAuth;

/**
 * Mock Microsoft OAuth provider for testing
 * Simulates Azure AD OAuth 2.0 + Microsoft Graph API
 */
class MockMicrosoftOAuthProvider implements OAuthProviderInterface
{
    private array $users = [];
    private array $groups = [];
    private array $validTokens = [];
    private string $clientId;
    private string $tenantId;
    private string $redirectUri;

    public function __construct(
        string $clientId = 'mock-client-id',
        string $tenantId = 'mock-tenant-id',
        string $redirectUri = 'http://localhost/auth/microsoft/callback'
    ) {
        $this->clientId = $clientId;
        $this->tenantId = $tenantId;
        $this->redirectUri = $redirectUri;
        $this->initializeDefaultUsers();
    }

    public function getAuthorizationUrl(string $state): string
    {
        return sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize?client_id=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s',
            urlencode($this->tenantId),
            urlencode($this->clientId),
            urlencode($this->redirectUri),
            urlencode('openid email profile User.Read'),
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
        $accessToken = 'EwAoA8l6BAAUO' . bin2hex(random_bytes(32));
        $this->validTokens[$accessToken] = [
            'code' => $code,
            'expires_at' => time() + 3600,
        ];

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'openid email profile User.Read',
            'refresh_token' => 'M.R3_' . bin2hex(random_bytes(16)),
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
            'userPrincipalName' => $user['email'],
            'mail' => $user['email'],
            'displayName' => $user['name'],
            'givenName' => $user['given_name'],
            'surname' => $user['family_name'],
            'jobTitle' => $user['job_title'] ?? null,
            'officeLocation' => $user['office'] ?? null,
            'mobilePhone' => $user['phone'] ?? null,
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
        return 'microsoft';
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
            'job_title' => 'Employee',
        ], $userData);
    }

    /**
     * Set group memberships for a user (Azure AD group IDs or display names)
     */
    public function setUserGroups(string $userId, array $groups): void
    {
        $this->groups[$userId] = $groups;
    }

    /**
     * Create an authorization code for a specific user
     */
    public function createAuthCodeForUser(string $userId): string
    {
        return 'ms_auth_code_' . $userId . '_' . bin2hex(random_bytes(8));
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
            'email' => 'test@woodsonisd.onmicrosoft.com',
            'name' => 'Test User',
            'given_name' => 'Test',
            'family_name' => 'User',
            'job_title' => 'Staff Member',
        ]);

        // Super admin user
        $this->addUser('super_admin', [
            'email' => 'admin@woodsonisd.onmicrosoft.com',
            'name' => 'Super Admin',
            'given_name' => 'Super',
            'family_name' => 'Admin',
            'job_title' => 'IT Administrator',
        ]);
        $this->setUserGroups('super_admin', ['Global Admins', 'IT Staff']);

        // External tenant user (should be rejected)
        $this->addUser('external', [
            'email' => 'external@otherschool.com',
            'name' => 'External User',
            'given_name' => 'External',
            'family_name' => 'User',
        ]);
    }

    private function getUserIdFromCode(string $code): string
    {
        // Extract user ID from authorization code
        // Format: ms_auth_code_{userId}_{random}
        if (preg_match('/^ms_auth_code_(.+?)_[a-f0-9]+$/', $code, $matches)) {
            return $matches[1];
        }
        return 'default';
    }

    private function generateMockIdToken(): string
    {
        // Mock JWT token (header.payload.signature)
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iss' => 'https://login.microsoftonline.com/' . $this->tenantId . '/v2.0',
            'sub' => 'mock-user-id',
            'email' => 'test@woodsonisd.onmicrosoft.com',
            'preferred_username' => 'test@woodsonisd.onmicrosoft.com',
            'tid' => $this->tenantId,
            'iat' => time(),
            'exp' => time() + 3600,
        ]));
        $signature = base64_encode('mock-signature');

        return "$header.$payload.$signature";
    }
}
