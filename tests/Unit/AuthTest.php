<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Auth;

/**
 * Unit tests for Auth security functionality
 * Covers role-based access control which is critical for PII protection
 */
class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testHasRoleReturnsFalseWhenNotLoggedIn(): void
    {
        $this->assertFalse(Auth::hasRole('admin'));
    }

    public function testHasRoleReturnsTrueForMatchingGlobalRole(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        
        $this->assertTrue(Auth::hasRole('admin'));
    }

    public function testSuperAdminHasAllRoles(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'super_admin';
        
        $this->assertTrue(Auth::hasRole('admin'));
        $this->assertTrue(Auth::hasRole('user'));
        $this->assertTrue(Auth::hasRole('manager'));
    }
}
