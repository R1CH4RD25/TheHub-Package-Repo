<?php

namespace Tests\Unit;

use Hub\Layout;
use Hub\SiteSettings;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Layout::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\SiteSettings::class)]
class LayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = 'test-token-123';
        $_ENV['MAINTENANCE_MODE'] = 'false';
    }

    protected function tearDown(): void
    {
        $_ENV['MAINTENANCE_MODE'] = 'false';
        parent::tearDown();
    }

    public function testRenderHeaderRegularUserHub(): void
    {
        $user = ['name' => 'John Doe', 'email' => 'john@test.com', 'picture' => 'https://example.com/avatar.jpg'];
        ob_start();
        Layout::renderHeader($user, 'user', 'hub');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('User', $output);
        $this->assertStringNotContainsString('Dashboard', $output);
    }

    public function testRenderHeaderAdminUserHub(): void
    {
        $user = ['name' => 'Admin User', 'email' => 'admin@test.com', 'picture' => ''];
        ob_start();
        Layout::renderHeader($user, 'admin', 'hub');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Admin', $output);
        $this->assertStringContainsString('Dashboard', $output);
        $this->assertStringContainsString('default-avatar.svg', $output);
    }

    public function testRenderHeaderSuperAdminHub(): void
    {
        $user = ['name' => 'Super Admin', 'email' => 'superadmin@test.com', 'picture' => 'https://example.com/super.jpg'];
        ob_start();
        Layout::renderHeader($user, 'super_admin', 'hub');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Super admin', $output);
        $this->assertStringContainsString('Dashboard', $output);
    }

    public function testRenderHeaderDashboardPage(): void
    {
        $user = ['name' => 'Admin User', 'email' => 'admin@test.com', 'picture' => null];
        $options = [
            'mobileMenuItems' => [
                ['tab' => 'users', 'label' => 'User Management', 'active' => true],
                ['tab' => 'sections', 'label' => 'Sections', 'active' => false]
            ]
        ];
        
        ob_start();
        Layout::renderHeader($user, 'admin', 'dashboard', $options);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Admin Dashboard', $output);
        $this->assertStringContainsString('Back to The Hub', $output);
        $this->assertStringContainsString('User Management', $output);
    }

    public function testRenderHeaderMaintenanceMode(): void
    {
        $_ENV['MAINTENANCE_MODE'] = 'true';
        $user = ['name' => 'Admin', 'email' => 'admin@test.com', 'picture' => ''];
        
        ob_start();
        Layout::renderHeader($user, 'admin', 'hub');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('MAINTENANCE MODE ACTIVE', $output);
    }

    public function testRenderFooterHub(): void
    {
        $user = ['name' => 'Test User', 'email' => 'test@example.com'];
        ob_start();
        Layout::renderFooter($user, 'hub');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Test User', $output);
        $this->assertStringContainsString('Logged in as', $output);
    }

    public function testRenderFooterDashboard(): void
    {
        $user = ['name' => 'Admin User', 'email' => 'admin@test.com'];
        ob_start();
        Layout::renderFooter($user, 'dashboard');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Admin Dashboard', $output);
    }

    public function testGetModernLibrariesCDN(): void
    {
        $libraries = Layout::getModernLibraries(false);
        $this->assertStringContainsString('bootstrap', $libraries);
        $this->assertStringContainsString('alpinejs', $libraries);
    }

    public function testGetModernLibrariesLocalBundle(): void
    {
        $libraries = Layout::getModernLibraries(true);
        // Should try local bundle first, fallback to CDN if not exists
        $this->assertTrue(
            str_contains($libraries, 'vendor.bundle.js') || str_contains($libraries, 'bootstrap'),
            'Should use local bundle or fallback to CDN'
        );
    }

    public function testGetStylesheetsHub(): void
    {
        $stylesheets = Layout::getStylesheets('hub');
        $this->assertStringContainsString('hub.css', $stylesheets);
    }

    public function testGetStylesheetsDashboard(): void
    {
        $stylesheets = Layout::getStylesheets('dashboard');
        $this->assertStringContainsString('admin.css', $stylesheets);
        $this->assertStringNotContainsString('hub.css', $stylesheets);
    }

    public function testGetInlineStyles(): void
    {
        $styles = Layout::getInlineStyles('hub');
        $this->assertStringContainsString('<style>', $styles);
        $this->assertStringContainsString('.nav-brand img', $styles);
    }

    public function testRenderHeadWithModernLibraries(): void
    {
        ob_start();
        Layout::renderHead('Test Page', 'hub', true);
        $output = ob_get_clean();
        
        $this->assertStringContainsString('<title>Test Page</title>', $output);
        $this->assertStringContainsString('bootstrap', $output);
    }

    public function testRenderHeadWithoutModernLibraries(): void
    {
        ob_start();
        Layout::renderHead('Simple Page', 'hub', false);
        $output = ob_get_clean();
        
        $this->assertStringNotContainsString('bootstrap', $output);
        $this->assertStringContainsString('<title>Simple Page</title>', $output);
    }

    public function testRenderModernInit(): void
    {
        ob_start();
        Layout::renderModernInit();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('window.TheHub', $output);
        $this->assertStringContainsString('init()', $output);
    }
}
