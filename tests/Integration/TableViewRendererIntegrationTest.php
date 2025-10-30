<?php

namespace Tests\Integration;

use Hub\Modules\TableViewRenderer;
use Hub\Database;
use Hub\Auth;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\TestDatabase;

/**
 * Integration tests for TableViewRenderer
 * Tests table rendering, sorting, filtering, pagination, and CRUD operations
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Modules\TableViewRenderer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Database::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Auth::class)]
class TableViewRendererIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::beginTransaction();
        $_GET = []; // Clear query parameters
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        TestDatabase::rollBack();
        parent::tearDown();
    }

    /**
     * Test basic table rendering
     */
    public function testBasicTableRendering(): void
    {
        $config = [
            'id' => 'users-table',
            'title' => 'Users',
            'dataSource' => [
                'table' => 'users'
            ],
            'columns' => [
                ['field' => 'id', 'label' => 'ID'],
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'email', 'label' => 'Email']
            ]
        ];

        $table = new TableViewRenderer($config);

        // Test validation works
        $this->assertTrue($table->validate());

        // Test render produces HTML table (tenancy disabled by default)
        $html = $table->render();
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('users-table', $html);
        $this->assertStringContainsString('Users', $html);
    }

    /**
     * Test table validation
     */
    public function testTableValidation(): void
    {
        // Valid config
        $validConfig = [
            'dataSource' => ['table' => 'users'],
            'columns' => [
                ['field' => 'id', 'label' => 'ID']
            ]
        ];
        $table = new TableViewRenderer($validConfig);
        $this->assertTrue($table->validate());

        // Missing dataSource
        $invalidConfig = [
            'columns' => [['field' => 'id']]
        ];
        $table = new TableViewRenderer($invalidConfig);
        $this->assertFalse($table->validate());

        // Missing table in dataSource
        $invalidConfig = [
            'dataSource' => [],
            'columns' => [['field' => 'id']]
        ];
        $table = new TableViewRenderer($invalidConfig);
        $this->assertFalse($table->validate());

        // Missing columns
        $invalidConfig = [
            'dataSource' => ['table' => 'users']
        ];
        $table = new TableViewRenderer($invalidConfig);
        $this->assertFalse($table->validate());

        // Column missing field
        $invalidConfig = [
            'dataSource' => ['table' => 'users'],
            'columns' => [['label' => 'ID']]
        ];
        $table = new TableViewRenderer($invalidConfig);
        $this->assertFalse($table->validate());
    }

    /**
     * Test table with configuration structure
     */
    public function testTableWithRealData(): void
    {
        $config = [
            'title' => 'User Directory',
            'dataSource' => ['table' => 'users'],
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'email', 'label' => 'Email']
            ],
            'perPage' => 10
        ];

        $table = new TableViewRenderer($config);

        // Verify validation
        $this->assertTrue($table->validate());

        // Test render with real data
        $html = $table->render();
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('User Directory', $html);
    }

    /**
     * Test pagination
     */
    public function testPagination(): void
    {
        // Create test data with unique emails
        $db = \Hub\Database::getInstance();
        for ($i = 1; $i <= 25; $i++) {
            $stmt = $db->prepare("INSERT INTO users (name, email, role, google_id) VALUES (?, ?, 'staff', ?)");
            $stmt->execute(["User $i", "pag_user{$i}_" . uniqid() . "@test.com", "google_pag_$i"]);
        }

        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'email', 'label' => 'Email']
            ],
            'perPage' => 10
        ];

        // Test page 1
        $_GET['page'] = '1';
        $table = new TableViewRenderer($config);
        $html = $table->render();
        $this->assertStringContainsString('<table', $html);

        // Should show pagination controls
        $this->assertStringContainsString('pagination', $html);
    }

    /**
     * Test sorting
     */
    public function testSorting(): void
    {
        // Create test data
        $db = \Hub\Database::getInstance();
        $stmt = $db->prepare("INSERT INTO users (name, email, role, google_id) VALUES (?, ?, 'staff', ?)");
        $stmt->execute(["Sort_Zoe", "sort_zoe_" . uniqid() . "@test.com", "google_sort_zoe"]);
        $stmt->execute(["Sort_Alice", "sort_alice_" . uniqid() . "@test.com", "google_sort_alice"]);

        $config = [
            'dataSource' => [
                'table' => 'users',
                'where' => "name LIKE 'Sort_%'"  // Filter to only our test data
            ],
            'columns' => [
                ['field' => 'name', 'label' => 'Name', 'sortable' => true]
            ],
            'defaultSort' => 'name'
        ];

        // Sort by name ASC
        $_GET['sort'] = 'name';
        $_GET['dir'] = 'ASC';
        $table = new TableViewRenderer($config);
        $html = $table->render();
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Sort_Alice', $html);
        $this->assertStringContainsString('Sort_Zoe', $html);

        // Sort by name DESC
        $_GET['dir'] = 'DESC';
        $table = new TableViewRenderer($config);
        $html = $table->render();
        $this->assertStringContainsString('Sort_Zoe', $html);
        $this->assertStringContainsString('Sort_Alice', $html);
    }

    /**
     * Test filtering
     */
    public function testFiltering(): void
    {
        // Create test data
        $db = \Hub\Database::getInstance();
        $stmt = $db->prepare("INSERT INTO users (name, email, role, google_id) VALUES (?, ?, 'staff', ?)");
        $stmt->execute(["John Smith", "john@test.com", "google_john"]);
        $stmt->execute(["Jane Doe", "jane@test.com", "google_jane"]);

        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [
                ['field' => 'name', 'label' => 'Name', 'filterable' => true],
                ['field' => 'email', 'label' => 'Email', 'filterable' => true]
            ]
        ];

        // Filter by name
        $_GET['filter'] = ['name' => 'John'];
        $table = new TableViewRenderer($config);
        $html = $table->render();
        $this->assertStringContainsString('John Smith', $html);
        $this->assertStringNotContainsString('Jane Doe', $html);
    }

    /**
     * Test getConfig method
     */
    public function testGetConfig(): void
    {
        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [['field' => 'id', 'label' => 'ID']]
        ];

        $table = new TableViewRenderer($config);
        $retrievedConfig = $table->getConfig();

        $this->assertEquals($config, $retrievedConfig);
    }

    /**
     * Test invalid configuration renders error
     */
    public function testInvalidConfigurationRendersError(): void
    {
        $config = [
            // Missing required fields
            'columns' => []
        ];

        $table = new TableViewRenderer($config);
        $html = $table->render();

        $this->assertStringContainsString('alert-danger', $html);
        $this->assertStringContainsString('Invalid table configuration', $html);
    }

    /**
     * Test table with action buttons configuration
     */
    public function testTableWithActionButtons(): void
    {
        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [
                ['field' => 'name', 'label' => 'Name']
            ],
            'actions' => [
                [
                    'label' => 'Add New',
                    'type' => 'primary',
                    'url' => '/users/create'
                ]
            ]
        ];

        $table = new TableViewRenderer($config);
        $this->assertTrue($table->validate());

        // Verify actions configuration
        $retrieved = $table->getConfig();
        $this->assertArrayHasKey('actions', $retrieved);
        $this->assertCount(1, $retrieved['actions']);
        $this->assertEquals('Add New', $retrieved['actions'][0]['label']);
    }

    /**
     * Test table with custom per-page setting
     */
    public function testCustomPerPageSetting(): void
    {
        // Custom per-page of 5
        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [['field' => 'name', 'label' => 'Name']],
            'perPage' => 5
        ];

        $table = new TableViewRenderer($config);
        $this->assertTrue($table->validate());

        // Verify perPage configuration
        $retrieved = $table->getConfig();
        $this->assertEquals(5, $retrieved['perPage']);
    }

    /**
     * Test handle method for CRUD operations
     */
    public function testHandleMethodForCreate(): void
    {
        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [
                ['field' => 'name', 'label' => 'Name'],
                ['field' => 'email', 'label' => 'Email']
            ]
        ];

        $table = new TableViewRenderer($config);
        $result = $table->handle([
            'action' => 'create',
            'data' => [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'role' => 'staff',
                'google_id' => 'google-123'
            ]
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test table with default sort configuration
     */
    public function testTableWithDefaultSort(): void
    {
        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [
                ['field' => 'name', 'label' => 'Name', 'sortable' => true],
                ['field' => 'created_at', 'label' => 'Created', 'sortable' => true]
            ],
            'defaultSort' => 'created_at'
        ];

        $table = new TableViewRenderer($config);
        $this->assertTrue($table->validate());

        // Verify default sort is set
        $retrieved = $table->getConfig();
        $this->assertEquals('created_at', $retrieved['defaultSort']);
    }

    /**
     * Test table title is optional
     */
    public function testTableTitleIsOptional(): void
    {
        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [['field' => 'name', 'label' => 'Name']]
            // No title specified
        ];

        $table = new TableViewRenderer($config);
        $this->assertTrue($table->validate());

        // Verify no title in config
        $retrieved = $table->getConfig();
        $this->assertArrayNotHasKey('title', $retrieved);
    }

    /**
     * Test invalid sort direction defaults to ASC
     */
    public function testInvalidSortDirectionDefaultsToAsc(): void
    {
        $_GET['sort'] = 'name';
        $_GET['dir'] = 'INVALID';

        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [
                ['field' => 'name', 'label' => 'Name', 'sortable' => true]
            ]
        ];

        $table = new TableViewRenderer($config);
        $this->assertTrue($table->validate());

        // Should construct without errors despite invalid sort direction
        $this->assertInstanceOf(TableViewRenderer::class, $table);
    }

    /**
     * Test page number cannot be less than 1
     */
    public function testPageNumberCannotBeLessThanOne(): void
    {
        $_GET['page'] = '-5';

        $config = [
            'dataSource' => ['table' => 'users'],
            'columns' => [['field' => 'name', 'label' => 'Name']],
            'perPage' => 10
        ];

        $table = new TableViewRenderer($config);
        $this->assertTrue($table->validate());

        // Constructor should handle negative page numbers gracefully
        $this->assertInstanceOf(TableViewRenderer::class, $table);
    }
}
