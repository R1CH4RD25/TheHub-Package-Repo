<?php

namespace Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;
use Hub\Modules\WorkflowRenderer;
use Hub\Database;
use Hub\Auth;

class WorkflowRendererTest extends TestCase
{
    private static Database $db;

    public static function setUpBeforeClass(): void
    {
        // Use test database
        putenv('DB_NAME=woodson_hub_test');
        $_ENV['DB_NAME'] = 'woodson_hub_test';

        self::$db = Database::getInstance();

        // Create test tables
        self::$db->execute("CREATE TABLE IF NOT EXISTS workflow_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            status VARCHAR(50) DEFAULT 'draft',
            title VARCHAR(255),
            amount DECIMAL(10,2),
            created_by INT,
            assigned_to INT,
            comment TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        self::$db->execute("CREATE TABLE IF NOT EXISTS workflow_test_tenant (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id VARCHAR(50),
            status VARCHAR(50) DEFAULT 'pending',
            title VARCHAR(255),
            created_by INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        self::$db->execute("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255),
            name VARCHAR(255),
            role VARCHAR(50),
            is_active TINYINT(1) DEFAULT 1
        )");

        self::$db->execute("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(50),
            table_name VARCHAR(100),
            record_id VARCHAR(50),
            user_id INT,
            old_values TEXT,
            new_values TEXT,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Start transaction for isolation
        self::$db->beginTransaction();
    }

    public static function tearDownAfterClass(): void
    {
        // Rollback transaction
        self::$db->rollback();

        // Disable foreign key checks temporarily
        self::$db->execute("SET FOREIGN_KEY_CHECKS = 0");

        // Drop test tables
        self::$db->execute("DROP TABLE IF EXISTS workflow_test");
        self::$db->execute("DROP TABLE IF EXISTS workflow_test_tenant");
        self::$db->execute("DROP TABLE IF EXISTS users");
        self::$db->execute("DROP TABLE IF EXISTS audit_log");

        // Re-enable foreign key checks
        self::$db->execute("SET FOREIGN_KEY_CHECKS = 1");
    }    protected function setUp(): void
    {
        // Clean tables
        self::$db->execute("DELETE FROM workflow_test");
        self::$db->execute("DELETE FROM workflow_test_tenant");
        self::$db->execute("DELETE FROM users");
        self::$db->execute("DELETE FROM audit_log");

        // Mock session
        $_SESSION = [
            'tenant_id' => 'test_tenant',
            'user_id' => 1,
            'role' => 'admin',
            'csrf_token' => 'test_token'
        ];

        // Mock GET
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/test';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
    }

    private function getBasicConfig(): array
    {
        return [
            'table' => 'workflow_test',
            'stateField' => 'status',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft', 'color' => 'secondary'],
                ['name' => 'submitted', 'label' => 'Submitted', 'color' => 'primary'],
                ['name' => 'approved', 'label' => 'Approved', 'color' => 'success'],
                ['name' => 'rejected', 'label' => 'Rejected', 'color' => 'danger']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'submitted',
                    'label' => 'Submit',
                    'color' => 'primary',
                    'requiredRole' => 'user'
                ],
                [
                    'from' => 'submitted',
                    'to' => 'approved',
                    'label' => 'Approve',
                    'color' => 'success',
                    'requiredRole' => 'admin'
                ],
                [
                    'from' => 'submitted',
                    'to' => 'rejected',
                    'label' => 'Reject',
                    'color' => 'danger',
                    'requiredRole' => 'admin',
                    'requireComment' => true
                ]
            ]
        ];
    }

    // ===== CONSTRUCTOR & BASIC TESTS =====

    public function testConstructorWithoutRecordId(): void
    {
        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $this->assertInstanceOf(WorkflowRenderer::class, $renderer);
        $this->assertEquals($config, $renderer->getConfig());
    }

    public function testConstructorWithRecordId(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'draft')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $this->assertInstanceOf(WorkflowRenderer::class, $renderer);
    }

    public function testGetConfig(): void
    {
        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $this->assertEquals($config, $renderer->getConfig());
        $this->assertEquals('workflow_test', $renderer->getConfig()['table']);
    }

    // ===== VALIDATION TESTS =====

    public function testValidateWithValidConfig(): void
    {
        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testValidateFailsWithoutTable(): void
    {
        $config = $this->getBasicConfig();
        unset($config['table']);

        $renderer = new WorkflowRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateFailsWithoutStates(): void
    {
        $config = $this->getBasicConfig();
        unset($config['states']);

        $renderer = new WorkflowRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateFailsWithEmptyStates(): void
    {
        $config = $this->getBasicConfig();
        $config['states'] = [];

        $renderer = new WorkflowRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateFailsWithoutTransitions(): void
    {
        $config = $this->getBasicConfig();
        unset($config['transitions']);

        $renderer = new WorkflowRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateFailsWithEmptyTransitions(): void
    {
        $config = $this->getBasicConfig();
        $config['transitions'] = [];

        $renderer = new WorkflowRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateFailsWithInvalidState(): void
    {
        $config = $this->getBasicConfig();
        $config['states'][] = ['label' => 'No Name']; // Missing 'name'

        $renderer = new WorkflowRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateFailsWithInvalidTransition(): void
    {
        $config = $this->getBasicConfig();
        $config['transitions'][] = ['label' => 'Invalid']; // Missing 'from' and 'to'

        $renderer = new WorkflowRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    // ===== RENDER TESTS =====

    public function testRenderWithInvalidConfig(): void
    {
        $config = ['table' => '']; // Invalid
        $renderer = new WorkflowRenderer($config);

        $output = $renderer->render();

        $this->assertStringContainsString('Invalid workflow configuration', $output);
    }

    public function testRenderWithoutRecordId(): void
    {
        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $output = $renderer->render();

        $this->assertStringContainsString('No workflow record specified', $output);
    }

    public function testRenderWithNonExistentRecord(): void
    {
        $_GET['record_id'] = '99999';

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $output = $renderer->render();

        $this->assertStringContainsString('Workflow record not found', $output);
    }

    public function testRenderWithValidRecord(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test Record', 'draft')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $config['displayFields'] = [
            ['field' => 'title', 'label' => 'Title']
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        $this->assertStringContainsString('Current Status', $output);
        $this->assertStringContainsString('Draft', $output);
        $this->assertStringContainsString('Test Record', $output);
    }

    // ===== MULTI-TENANCY TESTS =====

    public function testLoadRecordWithTenantId(): void
    {
        // Insert test record with tenant_id
        self::$db->execute("INSERT INTO workflow_test_tenant (tenant_id, title, status)
            VALUES ('test_tenant', 'Tenant Test', 'pending')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test_tenant',
            'stateField' => 'status',
            'states' => [
                ['name' => 'pending', 'label' => 'Pending', 'color' => 'warning']
            ],
            'transitions' => [
                ['from' => 'pending', 'to' => 'complete', 'label' => 'Complete']
            ]
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        $this->assertStringContainsString('Pending', $output);
    }

    public function testLoadRecordWithWrongTenant(): void
    {
        // Insert record with different tenant
        self::$db->execute("INSERT INTO workflow_test_tenant (tenant_id, title, status)
            VALUES ('other_tenant', 'Other Tenant', 'pending')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['tenant_id'] = 'test_tenant'; // Different tenant

        $config = [
            'table' => 'workflow_test_tenant',
            'stateField' => 'status',
            'states' => [['name' => 'pending', 'label' => 'Pending']],
            'transitions' => [['from' => 'pending', 'to' => 'complete', 'label' => 'Complete']]
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        // Should not find record (wrong tenant)
        $this->assertStringContainsString('not found', $output);
    }

    // ===== STATE AND TRANSITION TESTS =====

    public function testGetAvailableTransitionsForDraftState(): void
    {
        // Insert test record in draft state
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'draft')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user';

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $output = $renderer->render();

        // Should show "Submit" button
        $this->assertStringContainsString('Submit', $output);
        // Should NOT show admin transitions
        $this->assertStringNotContainsString('Approve', $output);
    }

    public function testGetAvailableTransitionsForSubmittedState(): void
    {
        // Insert test record in submitted state
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'submitted')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $output = $renderer->render();

        // Should show admin transitions
        $this->assertStringContainsString('Approve', $output);
        $this->assertStringContainsString('Reject', $output);
    }

    public function testNoTransitionsAvailable(): void
    {
        // Insert record in final state
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'approved')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $output = $renderer->render();

        $this->assertStringContainsString('No actions available', $output);
    }

    // ===== PERMISSION TESTS =====

    public function testTransitionRequiresRole(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'submitted')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user'; // Not admin

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $output = $renderer->render();

        // User should NOT see admin-only transitions
        $this->assertStringNotContainsString('Approve', $output);
    }

    public function testTransitionWithMultipleAllowedRoles(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'draft')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user';

        $config = $this->getBasicConfig();
        $config['transitions'][0]['requiredRole'] = ['user', 'admin']; // Array of roles

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        // User should see transition (is in allowed roles)
        $this->assertStringContainsString('Submit', $output);
    }

    public function testTransitionRequiresOwner(): void
    {
        // Insert record owned by user 2
        self::$db->execute("INSERT INTO workflow_test (title, status, created_by)
            VALUES ('Test', 'draft', 2)");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['user_id'] = 1; // Different user

        $config = $this->getBasicConfig();
        $config['transitions'][0]['requireOwner'] = true;
        $config['ownerField'] = 'created_by';

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        // Should NOT show transition (not owner)
        $this->assertStringNotContainsString('Submit', $output);
    }

    // ===== CONDITION TESTS =====

    public function testConditionEquals(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status, amount)
            VALUES ('Test', 'draft', 100.00)");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user'; // Has required role

        $config = $this->getBasicConfig();
        $config['transitions'][0]['conditions'] = [
            ['field' => 'amount', 'operator' => '=', 'value' => 100.00]
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        // Condition met, should show transition
        $this->assertStringContainsString('Submit', $output);
    }

    public function testConditionGreaterThan(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status, amount)
            VALUES ('Test', 'draft', 50.00)");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user'; // Has required role

        $config = $this->getBasicConfig();
        $config['transitions'][0]['conditions'] = [
            ['field' => 'amount', 'operator' => '>', 'value' => 100.00]
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        // Condition NOT met (50 is not > 100)
        $this->assertStringNotContainsString('Submit', $output);
    }

    public function testConditionLessThan(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status, amount)
            VALUES ('Test', 'draft', 50.00)");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user'; // Has required role

        $config = $this->getBasicConfig();
        $config['transitions'][0]['conditions'] = [
            ['field' => 'amount', 'operator' => '<', 'value' => 100.00]
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        // Condition met (50 < 100)
        $this->assertStringContainsString('Submit', $output);
    }

    public function testConditionIn(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status, amount)
            VALUES ('Test', 'draft', 100.00)");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user'; // Has required role

        $config = $this->getBasicConfig();
        $config['transitions'][0]['conditions'] = [
            ['field' => 'amount', 'operator' => 'in', 'value' => [50, 100, 150]]
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        // Condition met (100 in array)
        $this->assertStringContainsString('Submit', $output);
    }

    public function testConditionContains(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status)
            VALUES ('Test Document', 'draft')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user'; // Has required role

        $config = $this->getBasicConfig();
        $config['transitions'][0]['conditions'] = [
            ['field' => 'title', 'operator' => 'contains', 'value' => 'Document']
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        // Condition met (title contains "Document")
        $this->assertStringContainsString('Submit', $output);
    }    // ===== HANDLE TRANSITION TESTS =====

    public function testHandleWithInvalidCsrfToken(): void
    {
        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $result = $renderer->handle([
            'csrf_token' => 'wrong_token',
            'action' => 'transition'
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('security token', $result['error']);
    }

    public function testHandleWithInvalidAction(): void
    {
        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $result = $renderer->handle([
            'csrf_token' => 'test_token',
            'action' => 'invalid_action'
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid action', $result['error']);
    }

    public function testHandleTransitionSuccess(): void
    {
        // Insert test record
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'draft')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user';

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $result = $renderer->handle([
            'csrf_token' => 'test_token',
            'action' => 'transition',
            'transition_from' => 'draft',
            'transition_to' => 'submitted',
            'comment' => 'Ready for review'
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('updated successfully', $result['message']);

        // Verify state changed in database
        $stmt = self::$db->prepare("SELECT status FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        $this->assertEquals('submitted', $record['status']);
    }

    public function testHandleTransitionWithStateChanged(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'submitted')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        // Try to transition from 'draft' but record is in 'submitted'
        $result = $renderer->handle([
            'csrf_token' => 'test_token',
            'action' => 'transition',
            'transition_from' => 'draft',
            'transition_to' => 'submitted'
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('state has changed', $result['error']);
    }

    public function testHandleTransitionWithInvalidTransition(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'draft')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        // Try invalid transition (draft to approved, skipping submitted)
        $result = $renderer->handle([
            'csrf_token' => 'test_token',
            'action' => 'transition',
            'transition_from' => 'draft',
            'transition_to' => 'approved'
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid transition', $result['error']);
    }

    public function testHandleTransitionWithoutPermission(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'submitted')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user'; // Not admin

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        // Try admin-only transition
        $result = $renderer->handle([
            'csrf_token' => 'test_token',
            'action' => 'transition',
            'transition_from' => 'submitted',
            'transition_to' => 'approved'
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('permission', $result['error']);
    }

    public function testHandleTransitionRequiresComment(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'submitted')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        // Try to reject without comment (comment required)
        $result = $renderer->handle([
            'csrf_token' => 'test_token',
            'action' => 'transition',
            'transition_from' => 'submitted',
            'transition_to' => 'rejected',
            'comment' => '' // Empty
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Comment is required', $result['error']);
    }

    public function testHandleTransitionWithComment(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'submitted')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = $this->getBasicConfig();
        $config['transitions'][2]['commentField'] = 'comment'; // Save comment to field
        $renderer = new WorkflowRenderer($config);

        $result = $renderer->handle([
            'csrf_token' => 'test_token',
            'action' => 'transition',
            'transition_from' => 'submitted',
            'transition_to' => 'rejected',
            'comment' => 'Not approved because...'
        ]);

        $this->assertTrue($result['success']);

        // Verify comment saved
        $stmt = self::$db->prepare("SELECT comment FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        $this->assertEquals('Not approved because...', $record['comment']);
    }

    // ===== FIELD FORMATTING TESTS =====

    public function testFormatValueDate(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status, updated_at)
            VALUES ('Test', 'draft', '2024-11-06 12:00:00')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $config['displayFields'] = [
            ['field' => 'updated_at', 'label' => 'Updated', 'format' => 'date']
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        $this->assertStringContainsString('2024-11-06', $output);
    }

    public function testFormatValueCurrency(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status, amount)
            VALUES ('Test', 'draft', 1234.56)");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $config['displayFields'] = [
            ['field' => 'amount', 'label' => 'Amount', 'format' => 'currency']
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        $this->assertStringContainsString('$1,234.56', $output);
    }

    public function testFormatValueEmail(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'draft')");
        $recordId = self::$db->lastInsertId();

        // Store email in title field for testing
        self::$db->execute("UPDATE workflow_test SET title = 'test@example.com' WHERE id = ?", [$recordId]);

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $config['displayFields'] = [
            ['field' => 'title', 'label' => 'Email', 'format' => 'email']
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        $this->assertStringContainsString('mailto:', $output);
        $this->assertStringContainsString('test@example.com', $output);
    }

    public function testFormatValueUser(): void
    {
        // Insert user
        self::$db->execute("INSERT INTO users (id, name, email) VALUES (99, 'John Doe', 'john@example.com')");

        // Insert workflow record with user ID
        self::$db->execute("INSERT INTO workflow_test (title, status, created_by)
            VALUES ('Test', 'draft', 99)");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $config['displayFields'] = [
            ['field' => 'created_by', 'label' => 'Created By', 'format' => 'user']
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        $this->assertStringContainsString('John Doe', $output);
    }

    // ===== TRANSITION ACTIONS TESTS =====

    public function testTransitionWithUpdateFieldAction(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status, assigned_to)
            VALUES ('Test', 'draft', NULL)");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user';

        $config = $this->getBasicConfig();
        $config['transitions'][0]['actions'] = [
            ['type' => 'updateField', 'field' => 'assigned_to', 'value' => 5]
        ];

        $renderer = new WorkflowRenderer($config);

        $result = $renderer->handle([
            'csrf_token' => 'test_token',
            'action' => 'transition',
            'transition_from' => 'draft',
            'transition_to' => 'submitted'
        ]);

        $this->assertTrue($result['success']);

        // Verify field updated
        $stmt = self::$db->prepare("SELECT assigned_to FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        $this->assertEquals(5, $record['assigned_to']);
    }

    // ===== HISTORY TESTS =====

    public function testRenderHistoryWithNoEntries(): void
    {
        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'draft')");
        $recordId = self::$db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $output = $renderer->render();

        // Should not show history section if no entries
        $this->assertStringNotContainsString('Workflow History', $output);
    }

    public function testRenderHistoryWithEntries(): void
    {
        // Insert user first (foreign key constraint)
        self::$db->execute("INSERT INTO users (id, email, name) VALUES (1, 'test@example.com', 'Test User')");

        self::$db->execute("INSERT INTO workflow_test (title, status) VALUES ('Test', 'submitted')");
        $recordId = self::$db->lastInsertId();

        // Insert audit log entry - details go in new_values
        $details = json_encode(['from' => 'draft', 'to' => 'submitted', 'comment' => 'Ready']);
        self::$db->execute("INSERT INTO audit_log (action, table_name, record_id, user_id, new_values, created_at)
            VALUES ('workflow_transition', 'workflow_test', ?, 1, ?, NOW())",
            [$recordId, $details]
        );

        $_GET['record_id'] = $recordId;

        $config = $this->getBasicConfig();
        $renderer = new WorkflowRenderer($config);

        $output = $renderer->render();

        $this->assertStringContainsString('Workflow History', $output);
        $this->assertStringContainsString('draft', $output);
        $this->assertStringContainsString('submitted', $output);
        $this->assertStringContainsString('Ready', $output);
    }    // ===== EDGE CASES =====

    public function testEmptyConfig(): void
    {
        $renderer = new WorkflowRenderer([]);

        $this->assertFalse($renderer->validate());
    }

    public function testCustomStateField(): void
    {
        self::$db->execute("CREATE TEMPORARY TABLE custom_workflow (
            id INT PRIMARY KEY,
            workflow_state VARCHAR(50)
        )");

        self::$db->execute("INSERT INTO custom_workflow (id, workflow_state) VALUES (1, 'pending')");

        $_GET['record_id'] = 1;

        $config = [
            'table' => 'custom_workflow',
            'stateField' => 'workflow_state',
            'states' => [
                ['name' => 'pending', 'label' => 'Pending']
            ],
            'transitions' => [
                ['from' => 'pending', 'to' => 'done', 'label' => 'Complete']
            ]
        ];

        $renderer = new WorkflowRenderer($config);
        $output = $renderer->render();

        $this->assertStringContainsString('Pending', $output);
    }
}
