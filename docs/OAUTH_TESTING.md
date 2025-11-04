# OAuth Testing Guide

## Overview

The Hub supports **Google OAuth** and **Microsoft OAuth** for authentication. To enable comprehensive testing without requiring real OAuth credentials, we've built a complete **OAuth mocking framework**.

This guide helps organizations:
- ✅ Run tests in CI/CD without OAuth secrets
- ✅ Test authentication flows end-to-end
- ✅ Validate permission logic with different user types
- ✅ Add custom OAuth providers (SAML, Okta, etc.)

---

## Quick Start

### Running Tests with Mocks

```bash
# Test OAuth mock providers
php vendor/bin/phpunit tests/Unit/OAuthMockProvidersTest.php

# Test Auth with mocks (coming soon)
php vendor/bin/phpunit tests/Unit/AuthOAuthFlowTest.php
```

**No configuration required!** Mocks work out of the box.

---

## Architecture

### Mock Provider Interface

All OAuth providers implement `OAuthProviderInterface`:

```php
namespace Hub\Tests\Mocks\OAuth;

interface OAuthProviderInterface
{
    public function getAuthorizationUrl(string $state): string;
    public function exchangeCodeForToken(string $code): array;
    public function getUserProfile(string $accessToken): array;
    public function getUserGroups(string $accessToken, string $userId): array;
    public function validateToken(string $accessToken): bool;
    public function getProviderName(): string;
}
```

### Available Mocks

| Mock Provider | File | Simulates |
|---------------|------|-----------|
| **Google OAuth** | `MockGoogleOAuthProvider.php` | Google OAuth 2.0 + Directory API |
| **Microsoft OAuth** | `MockMicrosoftOAuthProvider.php` | Azure AD OAuth + Graph API |

---

## Using OAuth Mocks in Tests

### Example: Google OAuth Flow

```php
use Hub\Tests\Mocks\OAuth\MockGoogleOAuthProvider;

class MyAuthTest extends TestCase
{
    public function testGoogleLogin()
    {
        $provider = new MockGoogleOAuthProvider();
        
        // Add custom test user
        $provider->addUser('teacher_john', [
            'email' => 'john.doe@schooldistrict.edu',
            'name' => 'John Doe',
            'given_name' => 'John',
            'family_name' => 'Doe',
            'domain' => 'schooldistrict.edu',
        ]);
        
        // Set group memberships (for auto-approval)
        $provider->setUserGroups('teacher_john', [
            'teachers@schooldistrict.edu',
            'staff@schooldistrict.edu',
        ]);
        
        // Simulate OAuth flow
        $code = $provider->createAuthCodeForUser('teacher_john');
        $tokenResponse = $provider->exchangeCodeForToken($code);
        $profile = $provider->getUserProfile($tokenResponse['access_token']);
        $groups = $provider->getUserGroups($tokenResponse['access_token'], 'teacher_john');
        
        // Assertions
        $this->assertEquals('john.doe@schooldistrict.edu', $profile['email']);
        $this->assertContains('teachers@schooldistrict.edu', $groups);
    }
}
```

### Example: Microsoft OAuth Flow

```php
use Hub\Tests\Mocks\OAuth\MockMicrosoftOAuthProvider;

class MyAuthTest extends TestCase
{
    public function testMicrosoftLogin()
    {
        $provider = new MockMicrosoftOAuthProvider(
            clientId: 'mock-app-id',
            tenantId: 'mock-tenant-id',
            redirectUri: 'http://localhost/auth/microsoft/callback'
        );
        
        // Add custom test user
        $provider->addUser('admin_jane', [
            'email' => 'jane.smith@schooldistrict.onmicrosoft.com',
            'name' => 'Jane Smith',
            'given_name' => 'Jane',
            'family_name' => 'Smith',
            'job_title' => 'IT Administrator',
        ]);
        
        // Set Azure AD groups
        $provider->setUserGroups('admin_jane', [
            'Global Admins',
            'IT Support',
        ]);
        
        // Simulate OAuth flow
        $code = $provider->createAuthCodeForUser('admin_jane');
        $tokenResponse = $provider->exchangeCodeForToken($code);
        $profile = $provider->getUserProfile($tokenResponse['access_token']);
        
        // Assertions
        $this->assertEquals('jane.smith@schooldistrict.onmicrosoft.com', $profile['mail']);
        $this->assertEquals('IT Administrator', $profile['jobTitle']);
    }
}
```

---

## Test Scenarios

### ✅ Testing Successful Logins

```php
$provider = new MockGoogleOAuthProvider();
$provider->addUser('valid_user', ['email' => 'user@domain.com']);

$code = $provider->createAuthCodeForUser('valid_user');
$tokens = $provider->exchangeCodeForToken($code);
$profile = $provider->getUserProfile($tokens['access_token']);
```

### ❌ Testing Invalid Auth Codes

```php
$provider = new MockGoogleOAuthProvider();

try {
    $provider->exchangeCodeForToken('invalid-code-with-spaces');
    $this->fail('Expected exception');
} catch (\Exception $e) {
    $this->assertStringContainsString('Invalid authorization code', $e->getMessage());
}
```

### ⏱️ Testing Token Expiration

```php
$provider = new MockGoogleOAuthProvider();
$code = $provider->createAuthCodeForUser('test_user');
$tokens = $provider->exchangeCodeForToken($code);

// Manually expire token
$provider->expireToken($tokens['access_token']);

$this->assertFalse($provider->validateToken($tokens['access_token']));
```

### 🚫 Testing Domain Restrictions

```php
$provider = new MockGoogleOAuthProvider();
$provider->addUser('external', [
    'email' => 'hacker@evil.com',
    'domain' => 'evil.com',
]);

$code = $provider->createAuthCodeForUser('external');
$tokens = $provider->exchangeCodeForToken($code);
$profile = $provider->getUserProfile($tokens['access_token']);

// Your auth logic should reject non-matching domain
$this->assertNotEquals('woodsonisd.net', $profile['hd']);
```

### 👥 Testing Group Memberships

```php
$provider = new MockGoogleOAuthProvider();
$provider->addUser('admin', ['email' => 'admin@domain.com']);
$provider->setUserGroups('admin', ['admins@domain.com']);

$code = $provider->createAuthCodeForUser('admin');
$tokens = $provider->exchangeCodeForToken($code);
$groups = $provider->getUserGroups($tokens['access_token'], 'admin');

$this->assertContains('admins@domain.com', $groups);
```

---

## Default Test Users

Each mock provider comes with pre-configured users:

### Google Mock - Default Users

| User ID | Email | Name | Groups | Purpose |
|---------|-------|------|--------|---------|
| `default` | test@woodsonisd.net | Test User | - | Basic testing |
| `super_admin` | admin@woodsonisd.net | Super Admin | admins@woodsonisd.net | Admin testing |
| `external` | hacker@evil.com | External User | - | Domain rejection testing |

### Microsoft Mock - Default Users

| User ID | Email | Name | Groups | Purpose |
|---------|-------|------|--------|---------|
| `default` | test@woodsonisd.onmicrosoft.com | Test User | - | Basic testing |
| `super_admin` | admin@woodsonisd.onmicrosoft.com | Super Admin | Global Admins, IT Staff | Admin testing |
| `external` | external@otherschool.com | External User | - | Tenant rejection testing |

---

## Adding Custom OAuth Providers

### Step 1: Create Mock Class

```php
namespace Hub\Tests\Mocks\OAuth;

class MockSAMLProvider implements OAuthProviderInterface
{
    public function getAuthorizationUrl(string $state): string
    {
        return 'https://saml.provider.com/login?SAMLRequest=...&RelayState=' . $state;
    }
    
    public function exchangeCodeForToken(string $code): array
    {
        // SAML assertion validation
        return [
            'access_token' => 'saml_' . bin2hex(random_bytes(16)),
            'expires_in' => 3600,
        ];
    }
    
    public function getUserProfile(string $accessToken): array
    {
        // Extract user from SAML assertion
        return [
            'id' => 'saml-user-id',
            'email' => 'user@saml.com',
            'name' => 'SAML User',
        ];
    }
    
    // ... implement remaining interface methods
}
```

### Step 2: Add Tests

```php
class SAMLAuthTest extends TestCase
{
    public function testSAMLLogin()
    {
        $provider = new MockSAMLProvider();
        // ... test SAML flow
    }
}
```

---

## CI/CD Integration

### GitHub Actions

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install
      - run: php vendor/bin/phpunit
        env:
          # No OAuth secrets needed!
          DB_HOST: localhost
          DB_NAME: woodson_hub_test
```

### GitLab CI

```yaml
test:
  image: php:8.3
  script:
    - composer install
    - php vendor/bin/phpunit
  # No secrets required!
```

---

## Troubleshooting

### Mock Classes Not Found

```bash
# Regenerate autoloader
composer dump-autoload
```

### Token Validation Failing

```php
// Check token is not expired
$provider->validateToken($token); // Should return true

// If false, token may have expired (default: 3600 seconds)
// Create a fresh token:
$code = $provider->createAuthCodeForUser('user_id');
$tokens = $provider->exchangeCodeForToken($code);
```

### User Profile Returns Default User

```php
// Ensure auth code uses correct user ID
$code = $provider->createAuthCodeForUser('my_custom_user'); // ✅ Correct
$code = $provider->createAuthCodeForUser('default');        // ❌ Uses default

// Verify user was added
$provider->addUser('my_custom_user', [
    'email' => 'custom@domain.com',
]);
```

---

## Real OAuth vs. Mock OAuth

| Feature | Real OAuth | Mock OAuth |
|---------|-----------|------------|
| **Setup** | Requires client ID, secret, service account JSON | Zero configuration |
| **Speed** | 2-3 seconds per request | <1ms per request |
| **Reliability** | Network-dependent | 100% reliable |
| **CI/CD** | Requires secrets management | No secrets needed |
| **Customization** | Limited to provider API | Fully customizable |
| **Testing Edge Cases** | Difficult (rate limits, errors) | Easy (full control) |

---

## Best Practices

### ✅ DO

- Use mocks for **all unit/integration tests**
- Test **multiple user types** (admin, staff, external)
- Test **edge cases** (expired tokens, invalid codes, domain mismatches)
- Add **custom users** for specific scenarios
- Run tests in **CI/CD without secrets**

### ❌ DON'T

- Use mocks for **production** (real OAuth only)
- Share **real OAuth credentials** in tests
- Hard-code **user IDs** (use createAuthCodeForUser)
- Skip **token validation** tests (security-critical)

---

## Support

### Questions?

- 📖 See `tests/Unit/OAuthMockProvidersTest.php` for examples
- 💬 Check existing tests for usage patterns
- 🐛 Found a bug? Open an issue

### Contributing

New OAuth providers welcome! Follow the pattern:

1. Implement `OAuthProviderInterface`
2. Add default test users
3. Write comprehensive tests
4. Update this guide

---

## Summary

| Component | Status | Files |
|-----------|--------|-------|
| **Google Mock** | ✅ Complete | `MockGoogleOAuthProvider.php` |
| **Microsoft Mock** | ✅ Complete | `MockMicrosoftOAuthProvider.php` |
| **Interface** | ✅ Complete | `OAuthProviderInterface.php` |
| **Tests** | ✅ Complete | `OAuthMockProvidersTest.php` |
| **Auth Integration** | 🚧 Coming Soon | `AuthOAuthFlowTest.php` |

**Result**: Zero-configuration OAuth testing for multi-tenant deployments! 🎉
