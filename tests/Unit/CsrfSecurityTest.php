<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * CSRF Protection Security Tests
 * Focus: generateCsrfToken, verifyCsrfToken (from bootstrap.php)
 */
class CsrfSecurityTest extends TestCase {
    
    protected function setUp(): void {
        $_SESSION = [];
        // Load CSRF functions
        if (!function_exists('generateCsrfToken')) {
            require_once __DIR__ . '/../../src/bootstrap.php';
        }
    }
    
    protected function tearDown(): void {
        $_SESSION = [];
    }
    
    // ==================== CSRF TOKEN GENERATION TESTS ====================
    
    public function testGenerateCsrfTokenCreatesToken() {
        $token = generateCsrfToken();
        
        $this->assertNotEmpty($token, 'Token should not be empty');
        $this->assertIsString($token, 'Token should be string');
        $this->assertEquals(64, strlen($token), 'Token should be 64 chars (32 bytes hex)');
    }
    
    public function testGenerateCsrfTokenStoresInSession() {
        $token = generateCsrfToken();
        
        $this->assertArrayHasKey('csrf_token', $_SESSION, 'Token should be stored in session');
        $this->assertEquals($token, $_SESSION['csrf_token']);
    }
    
    public function testGenerateCsrfTokenReusesExistingToken() {
        $token1 = generateCsrfToken();
        $token2 = generateCsrfToken();
        
        $this->assertEquals($token1, $token2, 'Should return same token on multiple calls');
    }
    
    public function testGenerateCsrfTokenIsUnpredictable() {
        // Generate multiple tokens in different sessions
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $_SESSION = [];
            $tokens[] = generateCsrfToken();
        }
        
        $uniqueTokens = array_unique($tokens);
        $this->assertEquals(10, count($uniqueTokens), 'All tokens should be unique');
    }
    
    public function testGenerateCsrfTokenIsHexadecimal() {
        $token = generateCsrfToken();
        
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token, 
            'Token should be 64 hex characters');
    }
    
    // ==================== CSRF TOKEN VERIFICATION TESTS ====================
    
    public function testVerifyCsrfTokenAcceptsValidToken() {
        $token = generateCsrfToken();
        
        $this->assertTrue(verifyCsrfToken($token), 'Valid token should be accepted');
    }
    
    public function testVerifyCsrfTokenRejectsInvalidToken() {
        generateCsrfToken(); // Generate valid token
        
        $this->assertFalse(verifyCsrfToken('invalid_token'), 
            'Invalid token should be rejected');
    }
    
    public function testVerifyCsrfTokenRejectsEmptyToken() {
        generateCsrfToken();
        
        $this->assertFalse(verifyCsrfToken(''), 'Empty token should be rejected');
    }
    
    public function testVerifyCsrfTokenRejectsNullToken() {
        generateCsrfToken();
        
        $this->assertFalse(verifyCsrfToken(null), 'Null token should be rejected');
    }
    
    public function testVerifyCsrfTokenRejectsWhenNoSessionToken() {
        // Don't generate token
        
        $this->assertFalse(verifyCsrfToken('any_token'), 
            'Should reject when no session token exists');
    }
    
    public function testVerifyCsrfTokenIsCaseSensitive() {
        $token = generateCsrfToken();
        $uppercaseToken = strtoupper($token);
        
        $this->assertFalse(verifyCsrfToken($uppercaseToken), 
            'Token verification should be case-sensitive');
    }
    
    public function testVerifyCsrfTokenRejectsTruncatedToken() {
        $token = generateCsrfToken();
        $truncated = substr($token, 0, 32); // Half the token
        
        $this->assertFalse(verifyCsrfToken($truncated), 
            'Truncated token should be rejected');
    }
    
    public function testVerifyCsrfTokenRejectsExtendedToken() {
        $token = generateCsrfToken();
        $extended = $token . 'extra';
        
        $this->assertFalse(verifyCsrfToken($extended), 
            'Extended token should be rejected');
    }
    
    public function testVerifyCsrfTokenRejectsSimilarToken() {
        $token = generateCsrfToken();
        $similar = substr($token, 0, -1) . 'x'; // Change last char
        
        $this->assertFalse(verifyCsrfToken($similar), 
            'Token with one char different should be rejected');
    }
    
    // ==================== TIMING ATTACK PREVENTION ====================
    
    public function testVerifyCsrfTokenTimingAttackResistance() {
        $token = generateCsrfToken();
        
        // Measure time for valid token
        $start1 = microtime(true);
        verifyCsrfToken($token);
        $time1 = microtime(true) - $start1;
        
        // Measure time for invalid token (same length)
        $invalidToken = str_repeat('a', 64);
        $start2 = microtime(true);
        verifyCsrfToken($invalidToken);
        $time2 = microtime(true) - $start2;
        
        // Times should be similar (within 10ms)
        $this->assertLessThan(0.01, abs($time1 - $time2),
            'Verification timing should be consistent (hash_equals protection)');
    }
    
    public function testVerifyCsrfTokenConsistentTimingAcrossMultipleCalls() {
        $token = generateCsrfToken();
        
        $times = [];
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            verifyCsrfToken($token);
            $times[] = microtime(true) - $start;
        }
        
        $avg = array_sum($times) / count($times);
        $maxDeviation = max(array_map(function($t) use ($avg) { 
            return abs($t - $avg); 
        }, $times));
        
        $this->assertLessThan(0.005, $maxDeviation, 
            'Verification timing should be consistent');
    }
    
    // ==================== SECURITY EDGE CASES ====================
    
    public function testVerifyCsrfTokenRejectsArrayToken() {
        generateCsrfToken();
        
        try {
            $result = verifyCsrfToken(['array', 'token']);
            $this->assertFalse($result, 'Array token should be rejected');
        } catch (\TypeError $e) {
            // Expected for strict typing
            $this->assertTrue(true);
        }
    }
    
    public function testVerifyCsrfTokenRejectsObjectToken() {
        generateCsrfToken();
        
        try {
            $obj = new \stdClass();
            $result = verifyCsrfToken($obj);
            $this->assertFalse($result, 'Object token should be rejected');
        } catch (\TypeError $e) {
            // Expected for strict typing
            $this->assertTrue(true);
        }
    }
    
    public function testGenerateCsrfTokenWithExistingSessionData() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['other_data'] = 'should not interfere';
        
        $token = generateCsrfToken();
        
        $this->assertNotEmpty($token);
        $this->assertEquals(1, $_SESSION['user_id'], 'Existing session data preserved');
        $this->assertEquals('admin', $_SESSION['role'], 'Existing session data preserved');
    }
    
    public function testCsrfTokenPersistsAcrossMultipleOperations() {
        $token1 = generateCsrfToken();
        
        // Simulate some operations
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        
        $token2 = generateCsrfToken();
        
        $this->assertEquals($token1, $token2, 'Token should persist across operations');
        $this->assertTrue(verifyCsrfToken($token1), 'Original token still valid');
    }
    
    // ==================== SQL INJECTION ATTEMPTS ====================
    
    public function testVerifyCsrfTokenRejectsSqlInjection() {
        generateCsrfToken();
        
        $sqlInjectionAttempts = [
            "' OR '1'='1",
            "'; DROP TABLE users;--",
            "' UNION SELECT * FROM users--",
            "1' OR '1'='1'--",
            "admin'--"
        ];
        
        foreach ($sqlInjectionAttempts as $attempt) {
            $this->assertFalse(verifyCsrfToken($attempt), 
                "SQL injection attempt should be rejected: $attempt");
        }
    }
    
    public function testVerifyCsrfTokenRejectsSpecialCharacters() {
        generateCsrfToken();
        
        $specialChars = [
            '<script>alert(1)</script>',
            '../../etc/passwd',
            '../../../windows/system32',
            '${7*7}',
            '#{7*7}',
            '{{7*7}}'
        ];
        
        foreach ($specialChars as $char) {
            $this->assertFalse(verifyCsrfToken($char), 
                "Special character token should be rejected: $char");
        }
    }
    
    // ==================== TOKEN REGENERATION TESTS ====================
    
    public function testCsrfTokenCanBeRegeneratedManually() {
        $token1 = generateCsrfToken();
        
        // Manually regenerate by clearing session
        unset($_SESSION['csrf_token']);
        
        $token2 = generateCsrfToken();
        
        $this->assertNotEquals($token1, $token2, 'New token should be different');
        $this->assertFalse(verifyCsrfToken($token1), 'Old token should be invalid');
        $this->assertTrue(verifyCsrfToken($token2), 'New token should be valid');
    }
    
    public function testCsrfTokenInvalidAfterSessionClear() {
        $token = generateCsrfToken();
        
        $_SESSION = []; // Clear session
        
        $this->assertFalse(verifyCsrfToken($token), 
            'Token should be invalid after session cleared');
    }
    
    // ==================== DOUBLE SUBMIT COOKIE PATTERN ====================
    
    public function testMultipleCsrfTokensInDifferentSessions() {
        // Session 1
        $_SESSION = [];
        $token1 = generateCsrfToken();
        
        // Session 2
        $_SESSION = [];
        $token2 = generateCsrfToken();
        
        // Session 3
        $_SESSION = [];
        $token3 = generateCsrfToken();
        
        $this->assertNotEquals($token1, $token2, 'Different sessions = different tokens');
        $this->assertNotEquals($token2, $token3, 'Different sessions = different tokens');
        $this->assertNotEquals($token1, $token3, 'Different sessions = different tokens');
    }
}
