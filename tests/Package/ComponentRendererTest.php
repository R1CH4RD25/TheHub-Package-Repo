<?php

namespace Hub\Tests\Package;

use PHPUnit\Framework\TestCase;
use Hub\Package\Renderers\FormRenderer;
use Hub\Package\Renderers\TableRenderer;
use Hub\Package\Renderers\DetailRenderer;
use Hub\Package\Renderers\DashboardRenderer;
use Hub\Package\Renderers\FilterRenderer;

/**
 * Component Renderer Tests
 *
 * Tests all five renderers: Form, Table, Detail, Dashboard, Filter.
 * Verifies HTML output structure, data binding, field rendering,
 * edge cases, and security (escaping).
 */
class ComponentRendererTest extends TestCase
{
    private array $baseContext;

    protected function setUp(): void
    {
        $this->baseContext = [
            'packageId' => 'com.test.package',
            'pageId' => 'index',
            'user' => ['id' => 1, 'role' => 'admin'],
            'csrfToken' => 'test-csrf-token-12345',
            'baseUrl' => '/p/com.test.package',
            'routeParams' => [],
        ];
    }

    // =========================================================================
    // TABLE RENDERER
    // =========================================================================

    public function testTableRendererType(): void
    {
        $renderer = new TableRenderer();
        $this->assertEquals('table', $renderer->getType());
    }

    public function testTableRendererAssets(): void
    {
        $renderer = new TableRenderer();
        $assets = $renderer->getAssets();
        $this->assertArrayHasKey('css', $assets);
        $this->assertArrayHasKey('js', $assets);
        $this->assertNotEmpty($assets['js']);
    }

    public function testTableRenderBasicTable(): void
    {
        $renderer = new TableRenderer();

        $config = [
            'id' => 'test-table',
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'email', 'label' => 'Email'],
            ],
            'query' => 'listUsers',
        ];

        $data = [
            'data' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ],
            'meta' => ['total' => 2, 'page' => 1, 'perPage' => 50],
        ];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('pkg-table', $html);
        $this->assertStringContainsString('test-table', $html);
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('jane@example.com', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Email', $html);
    }

    public function testTableRenderEmptyData(): void
    {
        $renderer = new TableRenderer();

        $config = [
            'columns' => [['key' => 'name', 'label' => 'Name']],
        ];

        $data = ['data' => [], 'meta' => ['total' => 0]];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('0', $html);
    }

    public function testTableRenderWithBulkActions(): void
    {
        $renderer = new TableRenderer();

        $config = [
            'columns' => [['key' => 'name', 'label' => 'Name']],
            'bulkActions' => [
                ['label' => 'Delete Selected', 'mutation' => 'deleteMany', 'variant' => 'danger'],
            ],
        ];

        $data = [
            'data' => [['name' => 'Test']],
            'meta' => ['total' => 1],
        ];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('pkg-bulk-actions', $html);
        $this->assertStringContainsString('Delete Selected', $html);
    }

    public function testTableEscapesXss(): void
    {
        $renderer = new TableRenderer();

        $config = [
            'columns' => [['key' => 'name', 'label' => 'Name']],
        ];

        $data = [
            'data' => [['name' => '<script>alert("xss")</script>']],
            'meta' => ['total' => 1],
        ];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // =========================================================================
    // FORM RENDERER
    // =========================================================================

    public function testFormRendererType(): void
    {
        $renderer = new FormRenderer();
        $this->assertEquals('form', $renderer->getType());
    }

    public function testFormRendererAssets(): void
    {
        $renderer = new FormRenderer();
        $assets = $renderer->getAssets();
        $this->assertNotEmpty($assets['js']);
    }

    public function testFormRenderBasicForm(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'id' => 'user-form',
            'mutation' => 'createUser',
            'fields' => [
                ['key' => 'name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
                ['key' => 'email', 'type' => 'email', 'label' => 'Email'],
            ],
            'submitLabel' => 'Create User',
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('user-form', $html);
        $this->assertStringContainsString('createUser', $html);
        $this->assertStringContainsString('Full Name', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('Create User', $html);
        $this->assertStringContainsString('csrf_token', $html);
        $this->assertStringContainsString('test-csrf-token-12345', $html);
    }

    public function testFormRenderWithSections(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'sections' => [
                [
                    'title' => 'Personal Info',
                    'fields' => [
                        ['key' => 'first_name', 'type' => 'text', 'label' => 'First Name'],
                        ['key' => 'last_name', 'type' => 'text', 'label' => 'Last Name'],
                    ],
                ],
                [
                    'title' => 'Contact',
                    'collapsible' => true,
                    'fields' => [
                        ['key' => 'phone', 'type' => 'text', 'label' => 'Phone'],
                    ],
                ],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('Personal Info', $html);
        $this->assertStringContainsString('Contact', $html);
        $this->assertStringContainsString('pkg-collapsible', $html);
        $this->assertStringContainsString('fieldset', $html);
    }

    public function testFormRenderEditMode(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
            ],
        ];

        $data = [
            'record' => ['id' => 42, 'name' => 'Existing User'],
        ];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('value="42"', $html);
        $this->assertStringContainsString('Existing User', $html);
    }

    public function testFormRenderSelectField(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                [
                    'key' => 'status',
                    'type' => 'select',
                    'label' => 'Status',
                    'options' => [
                        ['value' => 'active', 'label' => 'Active'],
                        ['value' => 'inactive', 'label' => 'Inactive'],
                    ],
                ],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('Inactive', $html);
    }

    public function testFormRenderCheckboxField(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                ['key' => 'agree', 'type' => 'checkbox', 'label' => 'I Agree'],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('I Agree', $html);
        $this->assertStringContainsString('pkg-form-group-checkbox', $html);
    }

    public function testFormRenderDateField(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                ['key' => 'born', 'type' => 'date', 'label' => 'Birth Date'],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('type="date"', $html);
    }

    public function testFormRenderTextareaField(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                ['key' => 'notes', 'type' => 'textarea', 'label' => 'Notes', 'rows' => 6],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('rows="6"', $html);
    }

    public function testFormRenderRequiredField(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('pkg-required', $html);
    }

    public function testFormRenderHiddenField(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                ['key' => 'ref_id', 'type' => 'text', 'hidden' => true, 'default' => '123'],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('value="123"', $html);
    }

    public function testFormRenderWithErrors(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                ['key' => 'email', 'type' => 'email', 'label' => 'Email'],
            ],
        ];

        $data = [
            'errors' => ['email' => 'Invalid email format'],
        ];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('has-error', $html);
        $this->assertStringContainsString('Invalid email format', $html);
    }

    public function testFormRenderCurrencyField(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                ['key' => 'amount', 'type' => 'currency', 'label' => 'Amount'],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('input-group', $html);
        $this->assertStringContainsString('$', $html);
        $this->assertStringContainsString('step="0.01"', $html);
    }

    public function testFormRenderRadioField(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                [
                    'key' => 'priority',
                    'type' => 'radio',
                    'label' => 'Priority',
                    'options' => [
                        ['value' => 'low', 'label' => 'Low'],
                        ['value' => 'high', 'label' => 'High'],
                    ],
                ],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('Low', $html);
        $this->assertStringContainsString('High', $html);
    }

    public function testFormEscapesXss(): void
    {
        $renderer = new FormRenderer();

        $config = [
            'fields' => [
                ['key' => 'name', 'type' => 'text', 'label' => '<img onerror=alert(1)>'],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        // The <img tag is HTML-escaped, preventing script execution
        $this->assertStringNotContainsString('<img onerror', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    // =========================================================================
    // DETAIL RENDERER
    // =========================================================================

    public function testDetailRendererType(): void
    {
        $renderer = new DetailRenderer();
        $this->assertEquals('detail', $renderer->getType());
    }

    public function testDetailRenderBasicView(): void
    {
        $renderer = new DetailRenderer();

        $config = [
            'id' => 'user-detail',
            'sections' => [
                [
                    'title' => 'User Info',
                    'columns' => 2,
                    'fields' => [
                        ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                        ['key' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ],
                ],
            ],
            'backUrl' => '/users',
        ];

        $data = [
            'data' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        ];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('pkg-detail-card', $html);
        $this->assertStringContainsString('User Info', $html);
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('john@example.com', $html);
        $this->assertStringContainsString('Back', $html);
    }

    public function testDetailRenderNotFound(): void
    {
        $renderer = new DetailRenderer();

        $config = [
            'backUrl' => '/users',
        ];

        $data = ['data' => []];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('Record Not Found', $html);
        $this->assertStringContainsString('Go Back', $html);
    }

    public function testDetailRenderDateField(): void
    {
        $renderer = new DetailRenderer();

        $config = [
            'sections' => [
                [
                    'fields' => [
                        ['key' => 'created_at', 'label' => 'Created', 'type' => 'date'],
                    ],
                ],
            ],
        ];

        $data = ['data' => ['created_at' => '2025-06-15']];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('Jun 15, 2025', $html);
    }

    public function testDetailRenderBadgeField(): void
    {
        $renderer = new DetailRenderer();

        $config = [
            'sections' => [
                [
                    'fields' => [
                        ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'badgeColor' => 'success'],
                    ],
                ],
            ],
        ];

        $data = ['data' => ['status' => 'Active']];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('badge-success', $html);
        $this->assertStringContainsString('Active', $html);
    }

    public function testDetailRenderBooleanField(): void
    {
        $renderer = new DetailRenderer();

        $config = [
            'sections' => [
                [
                    'fields' => [
                        ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
                    ],
                ],
            ],
        ];

        $data = ['data' => ['is_active' => true]];
        $html = $renderer->render($config, $data, $this->baseContext);
        $this->assertStringContainsString('Yes', $html);
        $this->assertStringContainsString('text-success', $html);
    }

    public function testDetailRenderCurrencyField(): void
    {
        $renderer = new DetailRenderer();

        $config = [
            'sections' => [
                [
                    'fields' => [
                        ['key' => 'salary', 'label' => 'Salary', 'type' => 'currency'],
                    ],
                ],
            ],
        ];

        $data = ['data' => ['salary' => 75000.50]];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('$75,000.50', $html);
    }

    public function testDetailRenderMaskedField(): void
    {
        $renderer = new DetailRenderer();

        $config = [
            'sections' => [
                [
                    'fields' => [
                        ['key' => 'ssn', 'label' => 'SSN', 'type' => 'masked'],
                    ],
                ],
            ],
        ];

        $data = ['data' => ['ssn' => '123-45-6789']];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('••••••', $html);
        $this->assertStringNotContainsString('123-45-6789', $html);
    }

    public function testDetailRenderFlatFields(): void
    {
        $renderer = new DetailRenderer();

        // No sections — should auto-render flat fields
        $config = [];

        $data = [
            'data' => ['name' => 'John', 'age' => 30, '_internal' => 'skip'],
        ];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('John', $html);
        $this->assertStringContainsString('30', $html);
        $this->assertStringNotContainsString('_internal', $html);
    }

    public function testDetailRenderWithActions(): void
    {
        $renderer = new DetailRenderer();

        $config = [
            'actions' => [
                [
                    'label' => 'Edit',
                    'type' => 'route',
                    'to' => '/edit/{id}',
                    'variant' => 'primary',
                    'icon' => 'bi-pencil',
                ],
            ],
        ];

        $data = ['data' => ['id' => 42, 'name' => 'Test']];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('Edit', $html);
        $this->assertStringContainsString('/edit/42', $html);
        $this->assertStringContainsString('bi-pencil', $html);
    }

    // =========================================================================
    // DASHBOARD RENDERER
    // =========================================================================

    public function testDashboardRendererType(): void
    {
        $renderer = new DashboardRenderer();
        $this->assertEquals('dashboard', $renderer->getType());
    }

    public function testDashboardRendererAssets(): void
    {
        $renderer = new DashboardRenderer();
        $assets = $renderer->getAssets();
        $this->assertContains('/assets/js/package-dashboard.js', $assets['js']);
    }

    public function testDashboardRenderCards(): void
    {
        $renderer = new DashboardRenderer();

        $config = [
            'id' => 'stats',
            'cards' => [
                ['title' => 'Total Users', 'value' => 150, 'icon' => 'bi-people', 'color' => 'primary'],
                ['title' => 'Active', 'dataKey' => 'active_count', 'color' => 'success'],
            ],
        ];

        $data = ['data' => ['active_count' => 120]];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('pkg-dashboard', $html);
        $this->assertStringContainsString('Total Users', $html);
        $this->assertStringContainsString('150', $html);
        $this->assertStringContainsString('120', $html);
        $this->assertStringContainsString('pkg-card-primary', $html);
        $this->assertStringContainsString('pkg-card-success', $html);
    }

    public function testDashboardRenderCardWithLink(): void
    {
        $renderer = new DashboardRenderer();

        $config = [
            'cards' => [
                ['title' => 'View All', 'value' => 50, 'link' => '/users'],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('<a', $html);
        $this->assertStringContainsString('/p/com.test.package/users', $html);
    }

    public function testDashboardRenderCardWithTrend(): void
    {
        $renderer = new DashboardRenderer();

        $config = [
            'cards' => [
                [
                    'title' => 'Revenue',
                    'value' => 5000,
                    'format' => 'currency',
                    'trend' => ['direction' => 'up', 'value' => '+12%', 'positive' => true],
                ],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('$5,000.00', $html);
        $this->assertStringContainsString('pkg-trend-positive', $html);
        $this->assertStringContainsString('+12%', $html);
    }

    public function testDashboardRenderCardFormats(): void
    {
        $renderer = new DashboardRenderer();

        $config = [
            'cards' => [
                ['title' => 'Percentage', 'value' => 85.3, 'format' => 'percentage'],
                ['title' => 'Compact', 'value' => 1500000, 'format' => 'compact'],
                ['title' => 'Text', 'value' => 'OK', 'format' => 'text'],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('85.3%', $html);
        $this->assertStringContainsString('1.5M', $html);
        $this->assertStringContainsString('OK', $html);
    }

    public function testDashboardRenderCharts(): void
    {
        $renderer = new DashboardRenderer();

        $config = [
            'charts' => [
                [
                    'title' => 'Monthly Revenue',
                    'chartType' => 'bar',
                    'height' => 400,
                    'labels' => ['Jan', 'Feb', 'Mar'],
                    'datasets' => [
                        ['label' => 'Revenue', 'data' => [100, 200, 150]],
                    ],
                ],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('pkg-chart-container', $html);
        $this->assertStringContainsString('Monthly Revenue', $html);
        $this->assertStringContainsString('<canvas', $html);
        $this->assertStringContainsString('__pkgCharts', $html);
        $this->assertStringContainsString('"type":"bar"', $html);
    }

    public function testDashboardRenderActionQueues(): void
    {
        $renderer = new DashboardRenderer();

        $config = [
            'queues' => [
                [
                    'title' => 'Pending Approvals',
                    'items' => [
                        ['label' => 'Leave Requests', 'count' => 5, 'link' => '/approvals/leave', 'variant' => 'warning', 'icon' => 'bi-calendar'],
                        ['label' => 'Purchase Orders', 'dataKey' => 'po_count', 'variant' => 'primary'],
                    ],
                ],
            ],
        ];

        $data = ['data' => ['po_count' => 3]];

        $html = $renderer->render($config, $data, $this->baseContext);

        $this->assertStringContainsString('Pending Approvals', $html);
        $this->assertStringContainsString('Leave Requests', $html);
        $this->assertStringContainsString('>5<', $html);
        $this->assertStringContainsString('>3<', $html);
        $this->assertStringContainsString('/approvals/leave', $html);
    }

    public function testDashboardRenderQuickLinks(): void
    {
        $renderer = new DashboardRenderer();

        $config = [
            'quickLinks' => [
                ['label' => 'Add User', 'url' => '/users/create', 'icon' => 'bi-plus', 'variant' => 'primary'],
                ['label' => 'Reports', 'url' => '/reports', 'icon' => 'bi-graph-up', 'description' => 'View analytics'],
            ],
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('Quick Actions', $html);
        $this->assertStringContainsString('Add User', $html);
        $this->assertStringContainsString('/users/create', $html);
        $this->assertStringContainsString('View analytics', $html);
    }

    public function testDashboardRenderEmpty(): void
    {
        $renderer = new DashboardRenderer();

        $config = [];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('pkg-dashboard', $html);
    }

    // =========================================================================
    // FILTER RENDERER
    // =========================================================================

    public function testFilterRendererType(): void
    {
        $renderer = new FilterRenderer();
        $this->assertEquals('filters', $renderer->getType());
    }

    public function testFilterRenderBasicFilters(): void
    {
        $renderer = new FilterRenderer();

        $config = [
            'id' => 'user-filters',
            'fields' => [
                ['key' => 'search', 'type' => 'search', 'label' => 'Search', 'placeholder' => 'Search users...'],
                [
                    'key' => 'status',
                    'type' => 'select',
                    'label' => 'Status',
                    'options' => [
                        ['value' => 'active', 'label' => 'Active'],
                        ['value' => 'inactive', 'label' => 'Inactive'],
                    ],
                ],
            ],
            'target' => 'user-table',
        ];

        $html = $renderer->render($config, [], $this->baseContext);

        $this->assertStringContainsString('Search', $html);
        $this->assertStringContainsString('Status', $html);
    }

    // =========================================================================
    // COMPONENT REGISTRY
    // =========================================================================

    public function testComponentRegistrySingleton(): void
    {
        $registry = \Hub\Package\ComponentRegistry::getInstance();
        $this->assertInstanceOf(\Hub\Package\ComponentRegistry::class, $registry);

        // Should have default renderers registered
        $this->assertTrue($registry->has('table'));
        $this->assertTrue($registry->has('form'));
        $this->assertTrue($registry->has('detail'));
        $this->assertTrue($registry->has('dashboard'));
        $this->assertTrue($registry->has('filters'));
    }

    public function testComponentRegistryCreateRenderer(): void
    {
        $registry = \Hub\Package\ComponentRegistry::getInstance();

        $tableRenderer = $registry->create('table');
        $this->assertInstanceOf(TableRenderer::class, $tableRenderer);

        $formRenderer = $registry->create('form');
        $this->assertInstanceOf(FormRenderer::class, $formRenderer);
    }

    public function testComponentRegistryUnknownType(): void
    {
        $registry = \Hub\Package\ComponentRegistry::getInstance();
        $this->assertFalse($registry->has('nonexistent'));
    }
}
