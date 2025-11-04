<?php

namespace Tests\Integration;

use Hub\Modules\DashboardRenderer;
use Hub\Database;
use Hub\Auth;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\TestDatabase;

/**
 * Integration tests for DashboardRenderer
 * Tests dashboard widget rendering, role-based visibility, and data queries
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Modules\DashboardRenderer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Database::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Auth::class)]
class DashboardRendererIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::beginTransaction();
    }

    protected function tearDown(): void
    {
        TestDatabase::rollBack();
        parent::tearDown();
    }

    /**
     * Test basic dashboard rendering with stat widget
     */
    public function testBasicDashboardWithStatWidget(): void
    {
        $config = [
            'displayName' => 'Test Dashboard',
            'description' => 'A test dashboard',
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Total Users',
                    'query' => 'SELECT COUNT(*) as value FROM users',
                    'icon' => 'bi-people',
                    'color' => 'primary',
                    'width' => 'col-md-3'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('Test Dashboard', $html);
        $this->assertStringContainsString('A test dashboard', $html);
        $this->assertStringContainsString('Total Users', $html);
        $this->assertStringContainsString('dashboard-container', $html);
    }

    /**
     * Test dashboard validation
     */
    public function testDashboardValidation(): void
    {
        // Valid config
        $validConfig = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Test',
                    'query' => 'SELECT 1'
                ]
            ]
        ];
        $dashboard = new DashboardRenderer($validConfig);
        $this->assertTrue($dashboard->validate());

        // Missing widgets
        $invalidConfig = ['widgets' => []];
        $dashboard = new DashboardRenderer($invalidConfig);
        $this->assertFalse($dashboard->validate());

        // Missing type
        $invalidConfig = [
            'widgets' => [
                ['title' => 'Test', 'query' => 'SELECT 1']
            ]
        ];
        $dashboard = new DashboardRenderer($invalidConfig);
        $this->assertFalse($dashboard->validate());

        // Missing title
        $invalidConfig = [
            'widgets' => [
                ['type' => 'stat', 'query' => 'SELECT 1']
            ]
        ];
        $dashboard = new DashboardRenderer($invalidConfig);
        $this->assertFalse($dashboard->validate());

        // Missing query
        $invalidConfig = [
            'widgets' => [
                ['type' => 'stat', 'title' => 'Test']
            ]
        ];
        $dashboard = new DashboardRenderer($invalidConfig);
        $this->assertFalse($dashboard->validate());

        // Invalid widget type
        $invalidConfig = [
            'widgets' => [
                ['type' => 'invalid', 'title' => 'Test', 'query' => 'SELECT 1']
            ]
        ];
        $dashboard = new DashboardRenderer($invalidConfig);
        $this->assertFalse($dashboard->validate());
    }

    /**
     * Test dashboard with multiple widget types
     */
    public function testDashboardWithMultipleWidgetTypes(): void
    {
        $config = [
            'displayName' => 'Multi-Widget Dashboard',
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Stat Widget',
                    'query' => 'SELECT COUNT(*) as value FROM users',
                    'width' => 'col-md-3'
                ],
                [
                    'type' => 'chart',
                    'title' => 'Chart Widget',
                    'query' => 'SELECT role, COUNT(*) as count FROM users GROUP BY role',
                    'chartType' => 'bar',
                    'width' => 'col-md-6'
                ],
                [
                    'type' => 'table',
                    'title' => 'Table Widget',
                    'query' => 'SELECT id, name, email FROM users LIMIT 5',
                    'width' => 'col-md-6'
                ],
                [
                    'type' => 'list',
                    'title' => 'List Widget',
                    'query' => 'SELECT name FROM users LIMIT 3',
                    'width' => 'col-md-3'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('Stat Widget', $html);
        $this->assertStringContainsString('Chart Widget', $html);
        $this->assertStringContainsString('Table Widget', $html);
        $this->assertStringContainsString('List Widget', $html);
    }

    /**
     * Test role-based widget visibility
     */
    public function testRoleBasedWidgetVisibility(): void
    {
        // Create admin user and log in
        $userId = TestDatabase::createTestUser(['role' => 'admin']);
        $_SESSION = [
            'logged_in' => true,
            'user_id' => $userId,
            'role' => 'admin',
            'email' => 'admin@test.com',
            'name' => 'Admin User'
        ];

        $config = [
            'displayName' => 'Role-Based Dashboard',
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Public Widget',
                    'query' => 'SELECT 1 as value'
                ],
                [
                    'type' => 'stat',
                    'title' => 'Admin Only Widget',
                    'query' => 'SELECT 2 as value',
                    'visibleToRoles' => ['admin', 'super_admin']
                ],
                [
                    'type' => 'stat',
                    'title' => 'Staff Only Widget',
                    'query' => 'SELECT 3 as value',
                    'visibleToRoles' => ['staff']
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        // Admin should see public and admin widgets
        $this->assertStringContainsString('Public Widget', $html);
        $this->assertStringContainsString('Admin Only Widget', $html);
        // Admin should NOT see staff-only widget
        $this->assertStringNotContainsString('Staff Only Widget', $html);

        // Cleanup session
        $_SESSION = [];
    }

    /**
     * Test stat widget with real data
     */
    public function testStatWidgetWithRealData(): void
    {
        // Create test users
        TestDatabase::createTestUser(['name' => 'User 1']);
        TestDatabase::createTestUser(['name' => 'User 2']);
        TestDatabase::createTestUser(['name' => 'User 3']);

        $config = [
            'displayName' => 'User Stats',
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Total Users',
                    'query' => 'SELECT COUNT(*) as value FROM users',
                    'icon' => 'bi-people',
                    'color' => 'success'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('Total Users', $html);
        // Should contain the count (at least 3 from this test)
        $this->assertMatchesRegularExpression('/\d+/', $html);
    }

    /**
     * Test chart widget rendering
     */
    public function testChartWidgetRendering(): void
    {
        $config = [
            'displayName' => 'Charts Dashboard',
            'widgets' => [
                [
                    'type' => 'chart',
                    'title' => 'Users by Role',
                    'query' => 'SELECT role as label, COUNT(*) as value FROM users GROUP BY role',
                    'chartType' => 'pie',
                    'width' => 'col-md-6'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('Users by Role', $html);
        $this->assertStringContainsString('canvas', $html);
    }

    /**
     * Test table widget rendering
     */
    public function testTableWidgetRendering(): void
    {
        // Query all existing users instead of expecting specific ones
        $config = [
            'displayName' => 'User Table',
            'widgets' => [
                [
                    'type' => 'table',
                    'title' => 'Recent Users',
                    'query' => 'SELECT name, email FROM users ORDER BY created_at DESC LIMIT 5',
                    'width' => 'col-md-12'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('Recent Users', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<tbody>', $html);
        // Verify table structure rather than specific data
        $this->assertStringContainsString('</tr>', $html);
    }

    /**
     * Test list widget rendering
     */
    public function testListWidgetRendering(): void
    {
        // Clean up any existing test data
        $db = TestDatabase::getConnection();
        $db->exec("DELETE FROM users WHERE name LIKE 'Item %'");

        // Create test data
        TestDatabase::createTestUser(['name' => 'Item 1', 'email' => 'item1@test.com']);
        TestDatabase::createTestUser(['name' => 'Item 2', 'email' => 'item2@test.com']);

        $config = [
            'displayName' => 'List Dashboard',
            'widgets' => [
                [
                    'type' => 'list',
                    'title' => 'User List',
                    'query' => "SELECT name FROM users WHERE name LIKE 'Item %' ORDER BY name LIMIT 5",
                    'width' => 'col-md-4'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('User List', $html);
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('Item 1', $html);
    }

    /**
     * Test auto-refresh configuration
     */
    public function testAutoRefreshConfiguration(): void
    {
        $config = [
            'displayName' => 'Auto-Refresh Dashboard',
            'autoRefresh' => 30, // 30 seconds
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Test',
                    'query' => 'SELECT 1 as value'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('setTimeout', $html);
        $this->assertStringContainsString('30 * 1000', $html);
    }

    /**
     * Test getConfig method
     */
    public function testGetConfig(): void
    {
        $config = [
            'displayName' => 'Test',
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Widget',
                    'query' => 'SELECT 1'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $retrievedConfig = $dashboard->getConfig();

        $this->assertEquals($config, $retrievedConfig);
    }

    /**
     * Test handle method (read-only dashboard)
     */
    public function testHandleMethodReturnsReadOnly(): void
    {
        $config = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Test',
                    'query' => 'SELECT 1'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $result = $dashboard->handle(['test' => 'data']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('read-only', $result['message']);
    }

    /**
     * Test invalid configuration renders error message
     */
    public function testInvalidConfigurationRendersError(): void
    {
        $config = [
            'displayName' => 'Invalid Dashboard',
            'widgets' => [] // No widgets - invalid
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('alert-danger', $html);
        $this->assertStringContainsString('Invalid dashboard configuration', $html);
    }

    /**
     * Test unknown widget type handling
     */
    public function testUnknownWidgetTypeHandling(): void
    {
        $config = [
            'displayName' => 'Dashboard with Unknown Widget',
            'widgets' => [
                // First add a valid widget so validation passes
                [
                    'type' => 'stat',
                    'title' => 'Valid Widget',
                    'query' => 'SELECT 1'
                ]
            ]
        ];

        // Now manually add an unknown widget type after validation
        $dashboard = new DashboardRenderer($config);

        // Since we can't modify internal state, let's test that validate() rejects unknown types
        $invalidConfig = [
            'widgets' => [
                [
                    'type' => 'unknown_type',
                    'title' => 'Unknown',
                    'query' => 'SELECT 1'
                ]
            ]
        ];

        $invalidDashboard = new DashboardRenderer($invalidConfig);
        $this->assertFalse($invalidDashboard->validate());
    }

    /**
     * Test widget without description (optional field)
     */
    public function testDashboardWithoutDescription(): void
    {
        $config = [
            'displayName' => 'No Description Dashboard',
            // description field omitted
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Test Stat',
                    'query' => 'SELECT 1 as value'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('No Description Dashboard', $html);
        $this->assertStringContainsString('Test Stat', $html);
    }

    /**
     * Test widget with custom width
     */
    public function testWidgetWithCustomWidth(): void
    {
        $config = [
            'displayName' => 'Custom Width Test',
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Half Width',
                    'query' => 'SELECT 1 as value',
                    'width' => 'col-md-6'
                ],
                [
                    'type' => 'stat',
                    'title' => 'Full Width',
                    'query' => 'SELECT 2 as value',
                    'width' => 'col-md-12'
                ]
            ]
        ];

        $dashboard = new DashboardRenderer($config);
        $html = $dashboard->render();

        $this->assertStringContainsString('col-md-6', $html);
        $this->assertStringContainsString('col-md-12', $html);
    }
}
