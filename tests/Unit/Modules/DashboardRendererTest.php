<?php

namespace Tests\Unit\Modules;

use Hub\Modules\DashboardRenderer;
use Hub\Database;
use Hub\Auth;
use PHPUnit\Framework\TestCase;

class DashboardRendererTest extends TestCase
{
    private static $db;
    private static $widgetTableCreated = false;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
    }

    protected function setUp(): void
    {
        // Create test table if needed (outside transaction)
        if (!self::$widgetTableCreated) {
            self::$db->execute("CREATE TABLE IF NOT EXISTS dashboard_test_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id VARCHAR(50) NOT NULL,
                category VARCHAR(100),
                status VARCHAR(50),
                amount DECIMAL(10,2),
                item_name VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_category (category),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            self::$widgetTableCreated = true;
        }

        // Start transaction for isolation
        self::$db->beginTransaction();

        // Clean test data
        self::$db->execute("DELETE FROM dashboard_test_data WHERE tenant_id = 'test_tenant'");

        // Setup session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['tenant_id'] = 'test_tenant';
        $_SESSION['csrf_token'] = 'test_token_' . bin2hex(random_bytes(16));
        $_SESSION['user_id'] = 999;
        $_SESSION['role'] = 'admin';
    }

    protected function tearDown(): void
    {
        try {
            self::$db->rollback();
        } catch (\PDOException $e) {
            // Transaction already ended - that's OK
        }
        $_GET = [];
        $_POST = [];
    }

    // ===== VALIDATION TESTS =====

    public function testConstructorInitializesWithConfig(): void
    {
        $config = [
            'displayName' => 'Test Dashboard',
            'widgets' => []
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertInstanceOf(DashboardRenderer::class, $renderer);
        $this->assertEquals($config, $renderer->getConfig());
    }

    public function testGetConfigReturnsConfiguration(): void
    {
        $config = [
            'displayName' => 'My Dashboard',
            'description' => 'Dashboard description',
            'widgets' => [
                ['type' => 'stat', 'title' => 'Total Users', 'query' => 'SELECT COUNT(*) FROM users']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertEquals($config, $renderer->getConfig());
    }

    public function testValidateReturnsFalseWhenWidgetsMissing(): void
    {
        $config = [
            'displayName' => 'Dashboard'
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseWhenWidgetsEmpty(): void
    {
        $config = [
            'displayName' => 'Dashboard',
            'widgets' => []
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseWhenWidgetMissingType(): void
    {
        $config = [
            'widgets' => [
                ['title' => 'Widget 1', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseWhenWidgetMissingTitle(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'stat', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseWhenWidgetMissingQuery(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'stat', 'title' => 'Test Widget']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseForInvalidWidgetType(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'invalid_type', 'title' => 'Test', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsTrueForStatWidget(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'stat', 'title' => 'Total Count', 'query' => 'SELECT COUNT(*) FROM users']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testValidateReturnsTrueForChartWidget(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'chart', 'title' => 'Sales Chart', 'query' => 'SELECT date, amount FROM sales']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testValidateReturnsTrueForTableWidget(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'table', 'title' => 'Recent Items', 'query' => 'SELECT * FROM items LIMIT 5']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testValidateReturnsTrueForListWidget(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'list', 'title' => 'Top Items', 'query' => 'SELECT name FROM items LIMIT 10']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testValidateReturnsTrueForMultipleWidgets(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'stat', 'title' => 'Count', 'query' => 'SELECT COUNT(*) FROM users'],
                ['type' => 'chart', 'title' => 'Chart', 'query' => 'SELECT x, y FROM data'],
                ['type' => 'table', 'title' => 'Table', 'query' => 'SELECT * FROM items'],
                ['type' => 'list', 'title' => 'List', 'query' => 'SELECT name FROM items']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testValidateValidatesAllWidgetsInArray(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'stat', 'title' => 'Valid', 'query' => 'SELECT 1'],
                ['type' => 'invalid', 'title' => 'Invalid', 'query' => 'SELECT 2']
            ]
        ];

        $renderer = new DashboardRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testHandleReturnsReadOnlyMessage(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'stat', 'title' => 'Test', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $result = $renderer->handle(['action' => 'update']);

        $this->assertFalse($result['success']);
        $this->assertEquals('Dashboard module is read-only', $result['message']);
    }

    public function testHandleWithEmptyData(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'stat', 'title' => 'Test', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $result = $renderer->handle([]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testRenderReturnsErrorForInvalidConfiguration(): void
    {
        $config = [
            'displayName' => 'Dashboard'
            // Missing widgets
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Invalid dashboard configuration', $html);
        $this->assertStringContainsString('alert-danger', $html);
    }

    public function testRenderWithValidConfigurationIncludesTitle(): void
    {
        $config = [
            'displayName' => 'Executive Dashboard',
            'widgets' => [
                ['type' => 'stat', 'title' => 'Total Users', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Executive Dashboard', $html);
        $this->assertStringContainsString('dashboard-container', $html);
    }

    public function testRenderWithDescription(): void
    {
        $config = [
            'displayName' => 'Sales Dashboard',
            'description' => 'Overview of sales metrics',
            'widgets' => [
                ['type' => 'stat', 'title' => 'Revenue', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Sales Dashboard', $html);
        $this->assertStringContainsString('Overview of sales metrics', $html);
    }

    public function testRenderWithoutDescription(): void
    {
        $config = [
            'displayName' => 'Dashboard',
            'widgets' => [
                ['type' => 'stat', 'title' => 'Stat', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Dashboard', $html);
        // Note: text-muted appears in widget titles, just check no description paragraph
        $this->assertStringNotContainsString('<p class="text-muted">', $html);
    }

    public function testRenderIncludesGridLayout(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'stat', 'title' => 'Widget 1', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('row g-4', $html);
    }

    public function testRenderIncludesAutoRefreshScript(): void
    {
        $config = [
            'autoRefresh' => 30,
            'widgets' => [
                ['type' => 'stat', 'title' => 'Count', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('setTimeout', $html);
        $this->assertStringContainsString('location.reload', $html);
        $this->assertStringContainsString('30 * 1000', $html);
    }

    public function testRenderWithoutAutoRefresh(): void
    {
        $config = [
            'widgets' => [
                ['type' => 'stat', 'title' => 'Count', 'query' => 'SELECT 1']
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringNotContainsString('setTimeout', $html);
        $this->assertStringNotContainsString('location.reload', $html);
    }

    // ===== DATABASE INTEGRATION TESTS - STAT WIDGET =====

    public function testRenderStatWidgetWithDatabaseData(): void
    {
        // Insert test data
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, category, amount)
            VALUES ('test_tenant', 'sales', 1000),
                   ('test_tenant', 'sales', 2000),
                   ('test_tenant', 'sales', 1500)");

        $config = [
            'displayName' => 'Dashboard',
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Total Revenue',
                    'query' => 'SELECT SUM(amount) FROM dashboard_test_data WHERE tenant_id = "{tenant_id}" AND category = "sales"',
                    'format' => 'currency',
                    'icon' => 'bi-cash',
                    'color' => 'success'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Total Revenue', $html);
        $this->assertStringContainsString('$4,500.00', $html);
        $this->assertStringContainsString('bi-cash', $html);
        $this->assertStringContainsString('text-success', $html);
    }

    public function testRenderStatWidgetWithCountAggregation(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, status)
            VALUES ('test_tenant', 'active'),
                   ('test_tenant', 'active'),
                   ('test_tenant', 'inactive')");

        $config = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Active Users',
                    'query' => 'SELECT COUNT(*) FROM dashboard_test_data WHERE tenant_id = "{tenant_id}" AND status = "active"'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Active Users', $html);
        $this->assertStringContainsString('>2<', $html);
    }

    public function testRenderStatWidgetWithNumberFormat(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, amount)
            VALUES ('test_tenant', 1234567.89)");

        $config = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Big Number',
                    'query' => 'SELECT amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"',
                    'format' => 'number'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('1,234,568', $html);
    }

    public function testRenderStatWidgetWithPercentFormat(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, amount)
            VALUES ('test_tenant', 87.5)");

        $config = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Completion Rate',
                    'query' => 'SELECT amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"',
                    'format' => 'percent'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('87.5%', $html);
    }

    public function testRenderStatWidgetWithDrillDownLink(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, amount)
            VALUES ('test_tenant', 100)");

        $config = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Total Orders',
                    'query' => 'SELECT COUNT(*) FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"',
                    'link' => '/orders?status=all'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('View details', $html);
        $this->assertStringContainsString('href="/orders?status=all"', $html);
        $this->assertStringContainsString('bi-arrow-right', $html);
    }

    public function testRenderStatWidgetWithCustomWidth(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, amount)
            VALUES ('test_tenant', 50)");

        $config = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Metric',
                    'query' => 'SELECT amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"',
                    'width' => 'col-md-6'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('col-md-6', $html);
    }

    // ===== DATABASE INTEGRATION TESTS - CHART WIDGET =====

    public function testRenderChartWidgetWithDatabaseData(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, category, amount)
            VALUES ('test_tenant', 'Jan', 1000),
                   ('test_tenant', 'Feb', 1500),
                   ('test_tenant', 'Mar', 1200)");

        $config = [
            'widgets' => [
                [
                    'type' => 'chart',
                    'title' => 'Monthly Sales',
                    'query' => 'SELECT category, amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}" ORDER BY id',
                    'chartType' => 'line'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Monthly Sales', $html);
        $this->assertStringContainsString('canvas', $html);
        $this->assertStringContainsString('dashboard-chart-0', $html);
        $this->assertStringContainsString('window.dashboardChart_0', $html);
        $this->assertStringContainsString('"labels":["Jan","Feb","Mar"]', $html);
        $this->assertStringContainsString('"data":[1000,1500,1200]', $html);
    }

    public function testRenderChartWidgetWithBarChartType(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, category, amount)
            VALUES ('test_tenant', 'Product A', 500),
                   ('test_tenant', 'Product B', 750)");

        $config = [
            'widgets' => [
                [
                    'type' => 'chart',
                    'title' => 'Product Sales',
                    'query' => 'SELECT category, amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"',
                    'chartType' => 'bar'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('"type":"bar"', $html);
    }

    // ===== DATABASE INTEGRATION TESTS - TABLE WIDGET =====

    public function testRenderTableWidgetWithDatabaseData(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, category, status, amount)
            VALUES ('test_tenant', 'Order 1', 'completed', 100.50),
                   ('test_tenant', 'Order 2', 'pending', 250.75),
                   ('test_tenant', 'Order 3', 'completed', 180.00)");

        $config = [
            'widgets' => [
                [
                    'type' => 'table',
                    'title' => 'Recent Orders',
                    'query' => 'SELECT category, status, amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}" ORDER BY id DESC',
                    'columns' => [
                        ['field' => 'category', 'label' => 'Order'],
                        ['field' => 'status', 'label' => 'Status'],
                        ['field' => 'amount', 'label' => 'Amount']
                    ],
                    'limit' => 5
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Recent Orders', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Order 1', $html);
        $this->assertStringContainsString('completed', $html);
        $this->assertStringContainsString('100.5', $html);
    }

    public function testRenderTableWidgetWithEmptyData(): void
    {
        $config = [
            'widgets' => [
                [
                    'type' => 'table',
                    'title' => 'Empty Table',
                    'query' => 'SELECT category, amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}" AND category = "nonexistent"',
                    'columns' => [
                        ['field' => 'category', 'label' => 'Category'],
                        ['field' => 'amount', 'label' => 'Amount']
                    ]
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Empty Table', $html);
        $this->assertStringContainsString('No data', $html);
    }

    public function testRenderTableWidgetWithoutColumns(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, category, amount)
            VALUES ('test_tenant', 'Item', 99.99)");

        $config = [
            'widgets' => [
                [
                    'type' => 'table',
                    'title' => 'Simple Table',
                    'query' => 'SELECT category, amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Simple Table', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Item', $html);
    }

    // ===== DATABASE INTEGRATION TESTS - LIST WIDGET =====

    public function testRenderListWidgetWithDatabaseData(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, item_name)
            VALUES ('test_tenant', 'Task 1'),
                   ('test_tenant', 'Task 2'),
                   ('test_tenant', 'Task 3')");

        $config = [
            'widgets' => [
                [
                    'type' => 'list',
                    'title' => 'Recent Tasks',
                    'query' => 'SELECT item_name FROM dashboard_test_data WHERE tenant_id = "{tenant_id}" ORDER BY id DESC',
                    'limit' => 10
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Recent Tasks', $html);
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('Task 1', $html);
        $this->assertStringContainsString('Task 2', $html);
        $this->assertStringContainsString('bi-dot', $html);
    }

    public function testRenderListWidgetWithEmptyData(): void
    {
        $config = [
            'widgets' => [
                [
                    'type' => 'list',
                    'title' => 'Empty List',
                    'query' => 'SELECT item_name FROM dashboard_test_data WHERE tenant_id = "{tenant_id}" AND item_name = "nonexistent"'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Empty List', $html);
        $this->assertStringContainsString('No items', $html);
    }

    // ===== DATABASE INTEGRATION TESTS - MULTI-WIDGET =====

    public function testRenderMultipleWidgetTypes(): void
    {
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, category, status, amount, item_name)
            VALUES ('test_tenant', 'Sales', 'active', 1000, 'Item 1'),
                   ('test_tenant', 'Support', 'active', 500, 'Item 2')");

        $config = [
            'displayName' => 'Executive Dashboard',
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Total Revenue',
                    'query' => 'SELECT SUM(amount) FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"',
                    'format' => 'currency'
                ],
                [
                    'type' => 'chart',
                    'title' => 'Revenue by Category',
                    'query' => 'SELECT category, SUM(amount) FROM dashboard_test_data WHERE tenant_id = "{tenant_id}" GROUP BY category'
                ],
                [
                    'type' => 'table',
                    'title' => 'Recent Items',
                    'query' => 'SELECT item_name, amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"',
                    'columns' => [
                        ['field' => 'item_name', 'label' => 'Item'],
                        ['field' => 'amount', 'label' => 'Amount']
                    ]
                ],
                [
                    'type' => 'list',
                    'title' => 'Active Statuses',
                    'query' => 'SELECT DISTINCT status FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        // Verify all widgets rendered
        $this->assertStringContainsString('Total Revenue', $html);
        $this->assertStringContainsString('$1,500.00', $html);
        $this->assertStringContainsString('Revenue by Category', $html);
        $this->assertStringContainsString('canvas', $html);
        $this->assertStringContainsString('Recent Items', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Active Statuses', $html);
        $this->assertStringContainsString('<ul', $html);
    }

    // ===== TENANT ISOLATION TEST =====

    public function testTenantIsolation(): void
    {
        // Insert data for multiple tenants
        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, amount)
            VALUES ('test_tenant', 100),
                   ('test_tenant', 200),
                   ('other_tenant', 9999)");

        $config = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Total',
                    'query' => 'SELECT SUM(amount) FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"'
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        // Should only see test_tenant data (300.00 from SUM), not other_tenant data (9999)
        $this->assertStringContainsString('>300', $html);
        $this->assertStringNotContainsString('9999', $html);
    }    // ===== ROLE-BASED VISIBILITY TEST =====

    public function testWidgetVisibilityWithRoleRestriction(): void
    {
        $_SESSION['role'] = 'user';

        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, amount)
            VALUES ('test_tenant', 100)");

        $config = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Public Widget',
                    'query' => 'SELECT amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"'
                ],
                [
                    'type' => 'stat',
                    'title' => 'Admin Only Widget',
                    'query' => 'SELECT amount * 2 FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"',
                    'visibleToRoles' => ['admin', 'super_admin']
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        // User role should see public widget but not admin widget
        $this->assertStringContainsString('Public Widget', $html);
        $this->assertStringNotContainsString('Admin Only Widget', $html);
    }

    public function testWidgetVisibilityWithMatchingRole(): void
    {
        $_SESSION['role'] = 'admin';

        self::$db->execute("INSERT INTO dashboard_test_data (tenant_id, amount)
            VALUES ('test_tenant', 100)");

        $config = [
            'widgets' => [
                [
                    'type' => 'stat',
                    'title' => 'Admin Widget',
                    'query' => 'SELECT amount FROM dashboard_test_data WHERE tenant_id = "{tenant_id}"',
                    'visibleToRoles' => ['admin']
                ]
            ]
        ];

        $renderer = new DashboardRenderer($config);
        $html = $renderer->render();

        // Admin role should see admin widget
        $this->assertStringContainsString('Admin Widget', $html);
    }
}
