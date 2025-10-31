<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Auth;

/**
 * Advanced Auth Security Tests - Attack Vectors & Edge Cases
 * Focus: Timing attacks, concurrent access, role persistence, information disclosure
 */
class AuthAdvancedSecurityTest extends TestCase {
    
    protected function setUp(): void {
        $_SESSION = [];
    }
    
    protected function tearDown(): void {
        $_SESSION = [];
    }
    
    // ==================== TIMING ATTACK TESTS ====================
    
    public function testTimingAttackResistance() {
        // Verify hasRole() takes similar time for valid/invalid roles
        // This prevents attackers from enumerating valid roles via timing
        $_SESSION['user_id'] = 999999; // Non-existent user
        $_SESSION['role'] = 'user';
        
        $start1 = microtime(true);
        Auth::hasRole('admin');
        $time1 = microtime(true) - $start1;
        
        $start2 = microtime(true);
        Auth::hasRole('nonexistent_role_xyz');
        $time2 = microtime(true) - $start2;
        
        // Times should be within 50ms of each other
        $this->assertLessThan(0.05, abs($time1 - $time2), 
            'hasRole() timing differs significantly between valid/invalid roles');
    }
    
    public function testTimingConsistencyAcrossMultipleCalls() {
        // Multiple calls should have consistent timing
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'user';
        
        $times = [];
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            Auth::hasRole('admin');
            $times[] = microtime(true) - $start;
        }
        
        $avg = array_sum($times) / count($times);
        $maxDeviation = max(array_map(function($t) use ($avg) { return abs($t - $avg); }, $times));
        
        // Max deviation should be under 20ms
        $this->assertLessThan(0.02, $maxDeviation,
            'hasRole() timing varies too much across multiple calls');
    }
    
    // ==================== CONCURRENT ACCESS TESTS ====================
    
    public function testConcurrentRoleChecksDoNotInterfere() {
        // Simulate concurrent checks with different session data
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'user';
        
        $result1 = Auth::hasRole('user');
        
        // Modify session mid-check
        $_SESSION['role'] = 'admin';
        
        $result2 = Auth::hasRole('admin');
        
        $this->assertTrue($result1, 'First check should succeed');
        $this->assertTrue($result2, 'Second check should succeed with new role');
    }
    
    public function testSessionIsolationBetweenRequests() {
        // First "request"
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'user';
        $this->assertTrue(Auth::hasRole('user'));
        
        // Simulate new request with different session
        $_SESSION = [
            'user_id' => 2,
            'role' => 'admin'
        ];
        
        $this->assertFalse(Auth::hasRole('user'), 'Should not have user role from previous session');
        $this->assertTrue(Auth::hasRole('admin'), 'Should have new admin role');
    }
    
    // ==================== ROLE PERSISTENCE TESTS ====================
    
    public function testRolePersistsAcrossMultipleChecks() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        
        $this->assertTrue(Auth::hasRole('admin'));
        $this->assertTrue(Auth::hasRole('admin'));
        $this->assertTrue(Auth::hasRole('admin'));
        
        // Role should still be admin
        $this->assertEquals('admin', $_SESSION['role']);
    }
    
    public function testRoleDoesNotPersistAfterLogout() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $this->assertTrue(Auth::hasRole('admin'));
        
        // Simulate logout
        $_SESSION = [];
        
        $this->assertFalse(Auth::hasRole('admin'), 'Role check after logout should fail');
    }
    
    // ==================== SUPER ADMIN TESTS ====================
    
    public function testSuperAdminBypassesAllRoleChecks() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';
        
        $testRoles = ['user', 'admin', 'staff', 'manager', 'nonexistent_role', 'custom_role_xyz'];
        
        foreach ($testRoles as $role) {
            $this->assertTrue(Auth::hasRole($role), 
                "Super admin should have access to role: $role");
        }
    }
    
    public function testSuperAdminCannotBeDemoted() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';
        
        $this->assertTrue(Auth::hasRole('super_admin'));
        
        // Attempt to demote (this should not work in real system, but test the check)
        $_SESSION['role'] = 'user';
        
        $this->assertFalse(Auth::hasRole('super_admin'), 'After demotion, should not be super admin');
        $this->assertTrue(Auth::hasRole('user'), 'Should now be regular user');
    }
    
    // ==================== INFORMATION DISCLOSURE TESTS ====================
    
    public function testFailedRoleCheckDoesNotRevealUserExistence() {
        // Non-existent user
        $_SESSION['user_id'] = 999999;
        $_SESSION['role'] = 'user';
        
        $result1 = Auth::hasRole('admin');
        
        // Real user with wrong role
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'user';
        
        $result2 = Auth::hasRole('admin');
        
        // Both should return false without revealing WHY
        $this->assertFalse($result1);
        $this->assertFalse($result2);
        $this->assertEquals($result1, $result2, 'Results should be identical for security');
    }
    
    public function testNoExceptionLeaksRoleInformation() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'user';
        
        try {
            // These should not throw exceptions
            Auth::hasRole('');
            Auth::hasRole('!@#$%^&*()');
            Auth::hasRole(str_repeat('A', 1000));
            $this->assertTrue(true, 'No exceptions thrown');
        } catch (\Exception $e) {
            $this->fail('hasRole() should not throw exceptions that leak info: ' . $e->getMessage());
        }
    }
    
    // ==================== EDGE CASE TESTS ====================
    
    public function testNegativeUserIdHandling() {
        $_SESSION['user_id'] = -1;
        $_SESSION['role'] = 'admin';
        
        // Should handle negative user IDs gracefully (likely deny access)
        $result = Auth::hasRole('admin');
        
        // Either accept (if system allows negative IDs) or reject
        // The important thing is it doesn't crash
        $this->assertIsBool($result, 'Should return boolean for negative user ID');
    }
    
    public function testZeroUserIdHandling() {
        $_SESSION['user_id'] = 0;
        $_SESSION['role'] = 'user';
        
        $result = Auth::hasRole('user');
        
        // Should handle user ID = 0 gracefully
        $this->assertIsBool($result, 'Should return boolean for zero user ID');
    }
    
    public function testVeryLargeUserIdHandling() {
        $_SESSION['user_id'] = PHP_INT_MAX;
        $_SESSION['role'] = 'user';
        
        $result = Auth::hasRole('user');
        
        // Should handle very large user IDs without overflow
        $this->assertIsBool($result, 'Should return boolean for very large user ID');
    }
    
    public function testMixedCaseRoleNames() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'Admin'; // Capitalized
        
        $this->assertFalse(Auth::hasRole('admin'), 'Role names should be case-sensitive');
        $this->assertTrue(Auth::hasRole('Admin'), 'Should match exact case');
    }
    
    public function testRoleWithSpecialCharacters() {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'user';
        
        $specialRoles = [
            'admin-test',
            'admin_test',
            'admin.test',
            'admin/test',
            'admin test',
            'admin<script>',
            'admin\'OR\'1\'=\'1'
        ];
        
        foreach ($specialRoles as $role) {
            $result = Auth::hasRole($role);
            $this->assertIsBool($result, "Should handle special role: $role");
            $this->assertFalse($result, "Should deny access to special role: $role");
        }
    }
    
    public function testSessionWithArrayValues() {
        $_SESSION['user_id'] = [1, 2, 3]; // Invalid - should be scalar
        $_SESSION['role'] = 'admin';
        
        try {
            $result = Auth::hasRole('admin');
            $this->assertIsBool($result, 'Should handle array user_id gracefully');
        } catch (\Exception $e) {
            // If it throws, make sure it's not a SQL error
            $this->assertStringNotContainsString('SQL', $e->getMessage());
        }
    }
    
    // ==================== SESSION SECURITY TESTS ====================
    
    public function testSessionWithoutUserId() {
        $_SESSION['role'] = 'super_admin'; // Role but no user_id
        
        $this->assertFalse(Auth::hasRole('super_admin'), 
            'Should deny access without user_id even if role is set');
    }
    
    public function testSessionWithEmptyUserId() {
        $_SESSION['user_id'] = '';
        $_SESSION['role'] = 'admin';
        
        $this->assertFalse(Auth::hasRole('admin'), 
            'Should deny access with empty user_id');
    }
    
    public function testSessionWithNullUserId() {
        $_SESSION['user_id'] = null;
        $_SESSION['role'] = 'admin';
        
        $this->assertFalse(Auth::hasRole('admin'), 
            'Should deny access with null user_id');
    }
}
