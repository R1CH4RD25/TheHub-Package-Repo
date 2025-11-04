<?php

namespace Hub;

/**
 * OAuth client abstraction for Auth.php
 * Allows real Google OAuth in production, mocks in tests
 */
class OAuthClient
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private $mockAdapter; // For testing only

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        $mockAdapter = null
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->mockAdapter = $mockAdapter;
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(string $code): array
    {
        // Use mock if provided (testing)
        if ($this->mockAdapter !== null) {
            return $this->mockAdapter->mockGetAccessToken($code);
        }

        // Real Google OAuth (production)
        $tokenUrl = 'https://oauth2.googleapis.com/token';

        $postData = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Google token exchange failed: " . $response);
            throw new \Exception('Failed to get access token from Google');
        }

        return json_decode($response, true);
    }

    /**
     * Get user info from OAuth provider
     */
    public function getUserInfo(string $accessToken): array
    {
        // Use mock if provided (testing)
        if ($this->mockAdapter !== null) {
            return $this->mockAdapter->mockGetUserInfo($accessToken);
        }

        // Real Google OAuth (production)
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';

        $ch = curl_init($userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Google user info fetch failed: " . $response);
            throw new \Exception('Failed to get user info from Google');
        }

        return json_decode($response, true);
    }

    /**
     * Check if user is member of required groups
     */
    public function checkGroupMembership(string $accessToken, string $userEmail, array $requiredGroups): bool
    {
        // Use mock if provided (testing)
        if ($this->mockAdapter !== null) {
            return $this->mockAdapter->mockCheckGroupMembership($accessToken, $userEmail, $requiredGroups);
        }

        // Real Google Directory API (production)
        // Check if user is a member of any required group
        foreach ($requiredGroups as $groupEmail) {
            $groupCheckUrl = sprintf(
                'https://admin.googleapis.com/admin/directory/v1/groups/%s/hasMember/%s',
                urlencode($groupEmail),
                urlencode($userEmail)
            );

            $ch = curl_init($groupCheckUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if (isset($result['isMember']) && $result['isMember']) {
                    return true;
                }
            }
        }

        return false;
    }
}
