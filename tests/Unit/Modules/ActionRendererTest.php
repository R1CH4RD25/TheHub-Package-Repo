<?php

namespace Tests\Unit\Modules;

use Hub\Modules\ActionRenderer;
use Hub\Database;
use Hub\Auth;
use PHPUnit\Framework\TestCase;

class ActionRendererTest extends TestCase
{
    private static $db;
    private static $tableCreated = false;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
    }

    protected function setUp(): void
    {
        // Create test table if needed
        if (!self::$tableCreated) {
            self::$db->execute("CREATE TABLE IF NOT EXISTS action_test_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id VARCHAR(50) NOT NULL,
                name VARCHAR(100),
                status VARCHAR(50),
                assigned_to INT,
                is_deleted TINYINT(1) DEFAULT 0,
                deleted_at TIMESTAMP NULL,
                deleted_by INT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            self::$tableCreated = true;
        }

        // Start transaction
        self::$db->beginTransaction();

        // Clean test data
        self::$db->execute("DELETE FROM action_test_records WHERE tenant_id = 'test_tenant'");

        // Setup session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['tenant_id'] = 'test_tenant';
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['csrf_token'] = 'test_token_123';
        $_SESSION['user_name'] = 'Test User'; // For placeholder replacement
    }

    protected function tearDown(): void
    {
        try {
            self::$db->rollback();
        } catch (\PDOException $e) {
            // Transaction already ended
        }
        $_GET = [];
        $_POST = [];
        // Clean rate limit keys
        foreach (array_keys($_SESSION) as $key) {
            if (strpos($key, 'action_rate_') === 0) {
                unset($_SESSION[$key]);
            }
        }
    }

    // ===== VALIDATION TESTS =====

    public function testConstructorInitializesWithConfig(): void
    {
        $config = [
            'entity' => 'users',
            'operation' => 'update',
            'fields' => ['status' => 'active']
        ];

        $renderer = new ActionRenderer($config);

        $this->assertInstanceOf(ActionRenderer::class, $renderer);
        $this->assertEquals($config, $renderer->getConfig());
    }

    public function testGetConfigReturnsConfiguration(): void
    {
        $config = [
            'displayName' => 'Archive Records',
            'entity' => 'tickets',
            'operation' => 'update',
            'fields' => ['archived' => 1]
        ];

        $renderer = new ActionRenderer($config);

        $this->assertEquals($config, $renderer->getConfig());
    }

    public function testValidateReturnsFalseWhenEntityMissing(): void
    {
        $config = [
            'operation' => 'update',
            'fields' => ['status' => 'closed']
        ];

        $renderer = new ActionRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseWhenOperationMissing(): void
    {
        $config = [
            'entity' => 'users',
            'fields' => ['status' => 'active']
        ];

        $renderer = new ActionRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseForInvalidOperation(): void
    {
        $config = [
            'entity' => 'users',
            'operation' => 'destroy', // Invalid
            'fields' => ['status' => 'deleted']
        ];

        $renderer = new ActionRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseWhenUpdateOperationMissingFields(): void
    {
        $config = [
            'entity' => 'users',
            'operation' => 'update'
            // Missing fields
        ];

        $renderer = new ActionRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsTrueForValidUpdateConfig(): void
    {
        $config = [
            'entity' => 'users',
            'operation' => 'update',
            'fields' => ['status' => 'approved']
        ];

        $renderer = new ActionRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testValidateReturnsTrueForValidDeleteConfig(): void
    {
        $config = [
            'entity' => 'users',
            'operation' => 'delete'
            // Delete doesn't require fields
        ];

        $renderer = new ActionRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testRenderReturnsErrorForInvalidConfiguration(): void
    {
        $config = [
            'operation' => 'update' // Missing entity
        ];

        $renderer = new ActionRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Invalid action configuration', $html);
        $this->assertStringContainsString('alert-danger', $html);
    }

    // ===== RENDER TESTS =====

    public function testRenderWithNoRecordsSelected(): void
    {
        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'closed']
        ];

        $renderer = new ActionRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('No records selected', $html);
        $this->assertStringContainsString('alert-info', $html);
    }

    public function testRenderWithSingleRecord(): void
    {
        $_GET['id'] = '123';

        $config = [
            'displayName' => 'Approve',
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'approved']
        ];

        $renderer = new ActionRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Approve', $html);
        $this->assertStringContainsString('<strong>1</strong> record(s) selected', $html);
        $this->assertStringContainsString('csrf_token', $html);
        $this->assertStringContainsString('record_ids[]', $html);
    }

    public function testRenderWithBulkRecords(): void
    {
        $_GET['ids'] = ['101', '102', '103'];

        $config = [
            'displayName' => 'Bulk Archive',
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['archived' => 1]
        ];

        $renderer = new ActionRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Bulk Archive', $html);
        $this->assertStringContainsString('<strong>3</strong> record(s) selected', $html);
    }

    public function testRenderWithDescription(): void
    {
        $_GET['id'] = '1';

        $config = [
            'displayName' => 'Mark Complete',
            'description' => 'This will mark the selected records as complete',
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'complete']
        ];

        $renderer = new ActionRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Mark Complete', $html);
        $this->assertStringContainsString('This will mark the selected records as complete', $html);
    }

    public function testRenderWithConfirmation(): void
    {
        $_GET['id'] = '1';

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'delete',
            'confirmation' => [
                'enabled' => true,
                'message' => 'Are you sure you want to delete this?'
            ]
        ];

        $renderer = new ActionRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('confirmAction', $html);
        $this->assertStringContainsString('Are you sure you want to delete this?', $html);
        $this->assertStringContainsString('<script>', $html);
    }

    public function testRenderWithInputFields(): void
    {
        $_GET['id'] = '1';

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'updated'], // Need at least one field for validation
            'inputFields' => [
                [
                    'name' => 'comment',
                    'label' => 'Comment',
                    'type' => 'textarea',
                    'required' => true
                ],
                [
                    'name' => 'priority',
                    'label' => 'Priority',
                    'type' => 'select',
                    'required' => false,
                    'options' => [
                        ['value' => 'low', 'label' => 'Low'],
                        ['value' => 'high', 'label' => 'High']
                    ]
                ]
            ]
        ];

        $renderer = new ActionRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Comment', $html);
        $this->assertStringContainsString('textarea', $html);
        $this->assertStringContainsString('Priority', $html);
        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('Low', $html);
        $this->assertStringContainsString('High', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function testRenderWithCustomButtonClass(): void
    {
        $_GET['id'] = '1';

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'delete',
            'buttonClass' => 'btn-danger',
            'icon' => 'bi-trash'
        ];

        $renderer = new ActionRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('btn-danger', $html);
        $this->assertStringContainsString('bi-trash', $html);
    }

    // ===== HANDLE TESTS WITH DATABASE =====

    public function testHandleWithInvalidCsrfToken(): void
    {
        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'approved']
        ];

        $renderer = new ActionRenderer($config);
        $result = $renderer->handle([
            'csrf_token' => 'wrong_token',
            'record_ids' => ['1']
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid CSRF token', $result['message']);
    }

    public function testHandleWithMissingRecordIds(): void
    {
        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'closed']
        ];

        $renderer = new ActionRenderer($config);
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123'
            // Missing record_ids
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('No records specified', $result['message']);
    }

    public function testHandleWithTooManyRecords(): void
    {
        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'processed']
        ];

        // Generate 1001 record IDs
        $recordIds = range(1, 1001);

        $renderer = new ActionRenderer($config);
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => $recordIds
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Maximum 1000 records', $result['message']);
    }

    public function testHandleUpdatesSingleRecord(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status)
            VALUES ('test_tenant', 'Test Record', 'pending')");
        $recordId = self::$db->lastInsertId();

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'approved'] // Simple value, no placeholder
        ];

        $renderer = new ActionRenderer($config);
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => [$recordId]
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('1 record(s) updated', $result['message']);
        $this->assertEquals(1, $result['details']['success_count']);
        $this->assertEquals(0, $result['details']['fail_count']);

        // Verify database update
        $stmt = self::$db->query("SELECT status FROM action_test_records WHERE id = $recordId");
        $row = $stmt->fetch();
        $this->assertEquals('approved', $row['status']);
    }    public function testHandleUpdatesBulkRecords(): void
    {
        // Insert test records
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status)
            VALUES ('test_tenant', 'Record 1', 'pending'),
                   ('test_tenant', 'Record 2', 'pending'),
                   ('test_tenant', 'Record 3', 'pending')");

        $stmt = self::$db->query("SELECT id FROM action_test_records WHERE tenant_id = 'test_tenant' ORDER BY id");
        $recordIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'completed']
        ];

        $renderer = new ActionRenderer($config);
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => $recordIds
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('3 record(s) updated', $result['message']);
        $this->assertEquals(3, $result['details']['success_count']);

        // Verify all updated
        $stmt = self::$db->query("SELECT COUNT(*) FROM action_test_records
                                  WHERE tenant_id = 'test_tenant' AND status = 'completed'");
        $this->assertEquals(3, $stmt->fetchColumn());
    }

    public function testHandleSoftDeletesRecord(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status)
            VALUES ('test_tenant', 'To Delete', 'active')");
        $recordId = self::$db->lastInsertId();

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'delete'
        ];

        $renderer = new ActionRenderer($config);
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => [$recordId]
        ]);

        $this->assertTrue($result['success']);

        // Verify soft delete (is_deleted = 1)
        $stmt = self::$db->query("SELECT is_deleted, deleted_by FROM action_test_records WHERE id = $recordId");
        $row = $stmt->fetch();
        $this->assertEquals(1, $row['is_deleted']);
        $this->assertEquals(1, $row['deleted_by']);
    }

    public function testHandleWithPlaceholders(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status)
            VALUES ('test_tenant', 'Test', 'new')");
        $recordId = self::$db->lastInsertId();

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => [
                'assigned_to' => '{current_user_id}',  // Use current_user_id only (exists in Auth)
                'status' => 'assigned'
            ]
        ];

        $renderer = new ActionRenderer($config);
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => [$recordId]
        ]);

        $this->assertTrue($result['success']);

        // Verify placeholder was replaced with user ID (1)
        $stmt = self::$db->query("SELECT assigned_to FROM action_test_records WHERE id = $recordId");
        $row = $stmt->fetch();
        $this->assertEquals(1, $row['assigned_to']);
    }    public function testHandleWithInputFields(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status)
            VALUES ('test_tenant', 'Test', 'open')");
        $recordId = self::$db->lastInsertId();

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => [],
            'inputFields' => [
                ['name' => 'status', 'label' => 'New Status', 'type' => 'text']
            ]
        ];

        $renderer = new ActionRenderer($config);
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => [$recordId],
            'status' => 'resolved' // From input field
        ]);

        $this->assertTrue($result['success']);

        // Verify input field value was used
        $stmt = self::$db->query("SELECT status FROM action_test_records WHERE id = $recordId");
        $row = $stmt->fetch();
        $this->assertEquals('resolved', $row['status']);
    }

    // ===== TENANT ISOLATION TEST =====

    public function testTenantIsolation(): void
    {
        // Insert records for different tenants
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status)
            VALUES ('test_tenant', 'Our Record', 'pending'),
                   ('other_tenant', 'Their Record', 'pending')");

        $stmt = self::$db->query("SELECT id FROM action_test_records WHERE name = 'Our Record'");
        $ourId = $stmt->fetchColumn();

        $stmt = self::$db->query("SELECT id FROM action_test_records WHERE name = 'Their Record'");
        $otherId = $stmt->fetchColumn();

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'updated']
        ];

        $renderer = new ActionRenderer($config);

        // Try to update both (should only update test_tenant record)
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => [$ourId, $otherId]
        ]);

        $this->assertTrue($result['success']);

        // Verify only test_tenant record was updated
        $stmt = self::$db->query("SELECT status FROM action_test_records WHERE id = $ourId");
        $this->assertEquals('updated', $stmt->fetchColumn());

        $stmt = self::$db->query("SELECT status FROM action_test_records WHERE id = $otherId");
        $this->assertEquals('pending', $stmt->fetchColumn()); // Unchanged
    }

    // ===== RATE LIMITING TESTS =====

    public function testRateLimitAllowsUnder100Actions(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status)
            VALUES ('test_tenant', 'Test', 'new')");
        $recordId = self::$db->lastInsertId();

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'processed']
        ];

        $renderer = new ActionRenderer($config);

        // Execute 5 actions (well under limit)
        for ($i = 0; $i < 5; $i++) {
            $result = $renderer->handle([
                'csrf_token' => 'test_token_123',
                'record_ids' => [$recordId]
            ]);
            $this->assertTrue($result['success']);
        }
    }

    public function testRateLimitBlocksOver100Actions(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status)
            VALUES ('test_tenant', 'Test', 'new')");
        $recordId = self::$db->lastInsertId();

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'processed']
        ];

        $renderer = new ActionRenderer($config);

        // Simulate 100 actions already executed
        $_SESSION['action_rate_1'] = ['count' => 100, 'start' => time()];

        // Next action should be blocked
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => [$recordId]
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Rate limit exceeded', $result['message']);
    }

    public function testRateLimitResetsAfterWindow(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status)
            VALUES ('test_tenant', 'Test', 'new')");
        $recordId = self::$db->lastInsertId();

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => ['status' => 'processed']
        ];

        $renderer = new ActionRenderer($config);

        // Simulate 100 actions 61 seconds ago (outside window)
        $_SESSION['action_rate_1'] = ['count' => 100, 'start' => time() - 61];

        // Should be allowed (window reset)
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => [$recordId]
        ]);

        $this->assertTrue($result['success']);
    }

    // ===== MULTIPLE FIELD UPDATE TEST =====

    public function testHandleUpdatesMultipleFields(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO action_test_records (tenant_id, name, status, assigned_to)
            VALUES ('test_tenant', 'Multi Test', 'pending', NULL)");
        $recordId = self::$db->lastInsertId();

        $config = [
            'entity' => 'action_test_records',
            'operation' => 'update',
            'fields' => [
                'status' => 'in_progress',
                'assigned_to' => 999,
                'name' => 'Updated Name'
            ]
        ];

        $renderer = new ActionRenderer($config);
        $result = $renderer->handle([
            'csrf_token' => 'test_token_123',
            'record_ids' => [$recordId]
        ]);

        $this->assertTrue($result['success']);

        // Verify all fields updated
        $stmt = self::$db->query("SELECT status, assigned_to, name FROM action_test_records WHERE id = $recordId");
        $row = $stmt->fetch();
        $this->assertEquals('in_progress', $row['status']);
        $this->assertEquals(999, $row['assigned_to']);
        $this->assertEquals('Updated Name', $row['name']);
    }
}
