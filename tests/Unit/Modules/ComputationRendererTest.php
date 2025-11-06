<?php

namespace Tests\Unit\Modules;

use Hub\Modules\ComputationRenderer;
use Hub\Database;
use Hub\Auth;
use PHPUnit\Framework\TestCase;

class ComputationRendererTest extends TestCase
{
    private static $db;
    private static $computationTableCreated = false;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
    }

    protected function setUp(): void
    {
        // Create test table if needed (outside transaction)
        if (!self::$computationTableCreated) {
            self::$db->execute("CREATE TABLE IF NOT EXISTS computation_test_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id VARCHAR(50) NOT NULL,
                student_name VARCHAR(100),
                grade1 DECIMAL(5,2),
                grade2 DECIMAL(5,2),
                grade3 DECIMAL(5,2),
                credits INT,
                amount DECIMAL(10,2),
                quantity INT,
                status VARCHAR(50),
                score DECIMAL(5,2),
                result DECIMAL(10,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Create test user for audit logging (outside transaction)
            self::$db->execute("INSERT IGNORE INTO users (id, email, name, role, is_active)
                VALUES (999, 'test@test.com', 'Test User', 'admin', 1)");

            self::$computationTableCreated = true;
        }

        // Start transaction for isolation
        self::$db->beginTransaction();

        // Clean test data
        self::$db->execute("DELETE FROM computation_test_data WHERE tenant_id = 'test_tenant'");

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
            'entity' => 'test_table',
            'expression' => '1 + 1'
        ];

        $renderer = new ComputationRenderer($config);

        $this->assertInstanceOf(ComputationRenderer::class, $renderer);
        $this->assertEquals($config, $renderer->getConfig());
    }

    public function testGetConfigReturnsConfiguration(): void
    {
        $config = [
            'displayName' => 'GPA Calculator',
            'entity' => 'students',
            'expression' => '5 + 3'
        ];

        $renderer = new ComputationRenderer($config);

        $this->assertEquals($config, $renderer->getConfig());
    }

    public function testValidateReturnsFalseWhenExpressionMissing(): void
    {
        $config = [
            'entity' => 'students'
        ];

        $renderer = new ComputationRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseWhenEntityMissing(): void
    {
        $config = [
            'expression' => '1 + 1'
        ];

        $renderer = new ComputationRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseForUnsafeExpression(): void
    {
        $config = [
            'entity' => 'students',
            'expression' => 'eval("malicious code")'
        ];

        $renderer = new ComputationRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseForSystemCall(): void
    {
        $config = [
            'entity' => 'students',
            'expression' => 'system("ls -la")'
        ];

        $renderer = new ComputationRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsFalseForFileAccess(): void
    {
        $config = [
            'entity' => 'students',
            'expression' => 'file_get_contents("/etc/passwd")'
        ];

        $renderer = new ComputationRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    public function testValidateReturnsTrueForSimpleArithmetic(): void
    {
        $config = [
            'entity' => 'students',
            'expression' => '5 + 3 * 2'
        ];

        $renderer = new ComputationRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testValidateReturnsTrueForMathFunctions(): void
    {
        $config = [
            'entity' => 'measurements',
            'expression' => 'sqrt(16) + abs(-5)'
        ];

        $renderer = new ComputationRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    public function testRenderReturnsErrorForInvalidConfiguration(): void
    {
        $config = [
            'entity' => 'students'
            // Missing expression
        ];

        $renderer = new ComputationRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Invalid computation configuration', $html);
        $this->assertStringContainsString('alert-danger', $html);
    }

    public function testRenderIncludesDisplayName(): void
    {
        $config = [
            'displayName' => 'Revenue Calculator',
            'entity' => 'computation_test_data',
            'expression' => '10 + 20'
        ];

        $renderer = new ComputationRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Revenue Calculator', $html);
        $this->assertStringContainsString('computation-container', $html);
    }

    public function testRenderIncludesDescription(): void
    {
        $config = [
            'displayName' => 'Total',
            'description' => 'Calculates total revenue',
            'entity' => 'computation_test_data',
            'expression' => '25 * 2'
        ];

        $renderer = new ComputationRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Calculates total revenue', $html);
    }

    // ===== AGGREGATE COMPUTATION TESTS =====

    public function testHandleWithSumAggregation(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, amount)
            VALUES ('test_tenant', 100),
                   ('test_tenant', 200),
                   ('test_tenant', 300)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'SUM(amount)'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals(600, $result['result']);
    }

    public function testHandleWithAvgAggregation(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, score)
            VALUES ('test_tenant', 85),
                   ('test_tenant', 90),
                   ('test_tenant', 95)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'AVG(score)'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals(90, $result['result']);
    }

    public function testHandleWithCountAggregation(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, status)
            VALUES ('test_tenant', 'active'),
                   ('test_tenant', 'active'),
                   ('test_tenant', 'inactive')");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'COUNT(*)'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['result']);
    }

    public function testHandleWithMinAggregation(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, amount)
            VALUES ('test_tenant', 500),
                   ('test_tenant', 200),
                   ('test_tenant', 800)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'MIN(amount)'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['result']);
    }

    public function testHandleWithMaxAggregation(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, amount)
            VALUES ('test_tenant', 500),
                   ('test_tenant', 200),
                   ('test_tenant', 800)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'MAX(amount)'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals(800, $result['result']);
    }

    public function testHandleWithFilters(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, status, amount)
            VALUES ('test_tenant', 'active', 100),
                   ('test_tenant', 'active', 200),
                   ('test_tenant', 'inactive', 999)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'SUM(amount)',
            'filters' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'active']
            ]
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals(300, $result['result']);
    }

    // ===== RECORD-LEVEL COMPUTATION TESTS =====

    public function testHandleWithRecordIdSimpleArithmetic(): void
    {
        $stmt = self::$db->execute("INSERT INTO computation_test_data (tenant_id, grade1, grade2, grade3)
            VALUES ('test_tenant', 85, 90, 88)");
        $recordId = self::$db->getConnection()->lastInsertId();

        $config = [
            'entity' => 'computation_test_data',
            'expression' => '($grade1 + $grade2 + $grade3) / 3',
            'inputs' => [
                ['name' => 'grade1', 'field' => 'grade1'],
                ['name' => 'grade2', 'field' => 'grade2'],
                ['name' => 'grade3', 'field' => 'grade3']
            ]
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle(['record_id' => $recordId]);

        $this->assertTrue($result['success']);
        $this->assertEquals(87.67, round($result['result'], 2));
    }

    public function testHandleWithRecordIdMultiplication(): void
    {
        $stmt = self::$db->execute("INSERT INTO computation_test_data (tenant_id, quantity, amount)
            VALUES ('test_tenant', 5, 25.50)");
        $recordId = self::$db->getConnection()->lastInsertId();

        $config = [
            'entity' => 'computation_test_data',
            'expression' => '$quantity * $price',
            'inputs' => [
                ['name' => 'quantity', 'field' => 'quantity'],
                ['name' => 'price', 'field' => 'amount']
            ]
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle(['record_id' => $recordId]);

        $this->assertTrue($result['success']);
        $this->assertEquals(127.5, $result['result']);
    }

    public function testHandleRecordNotFound(): void
    {
        $config = [
            'entity' => 'computation_test_data',
            'expression' => '$grade1 + $grade2',
            'inputs' => [
                ['name' => 'grade1', 'field' => 'grade1'],
                ['name' => 'grade2', 'field' => 'grade2']
            ]
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle(['record_id' => 99999]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Record not found', $result['message']);
    }

    // ===== FORMAT TESTS =====

    public function testHandleWithCurrencyFormat(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, amount)
            VALUES ('test_tenant', 1234.56)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'SUM(amount)',
            'resultFormat' => 'currency'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals('$1,234.56', $result['formatted']);
    }

    public function testHandleWithNumberFormat(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, amount)
            VALUES ('test_tenant', 9876543.21)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'SUM(amount)',
            'resultFormat' => 'number'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals('9,876,543', $result['formatted']);
    }

    public function testHandleWithPercentFormat(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, score)
            VALUES ('test_tenant', 87.654)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'AVG(score)',
            'resultFormat' => 'percent'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals('87.7%', $result['formatted']);
    }

    public function testHandleWithDecimalFormat(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, amount)
            VALUES ('test_tenant', 123.456789)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'SUM(amount)',
            'resultFormat' => 'decimal'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals('123.46', $result['formatted']);
    }

    // ===== TENANT ISOLATION TEST =====

    public function testTenantIsolation(): void
    {
        self::$db->execute("INSERT INTO computation_test_data (tenant_id, amount)
            VALUES ('test_tenant', 100),
                   ('test_tenant', 200),
                   ('other_tenant', 9999)");

        $config = [
            'entity' => 'computation_test_data',
            'expression' => 'SUM(amount)'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle([]);

        $this->assertTrue($result['success']);
        $this->assertEquals(300, $result['result']);
    }

    // ===== COMPLEX EXPRESSION TESTS =====

    public function testHandleWithComplexArithmetic(): void
    {
        $stmt = self::$db->execute("INSERT INTO computation_test_data (tenant_id, grade1, grade2, credits)
            VALUES ('test_tenant', 85, 90, 3)");
        $recordId = self::$db->getConnection()->lastInsertId();

        $config = [
            'entity' => 'computation_test_data',
            'expression' => '(($grade1 + $grade2) / 2) * $credits',
            'inputs' => [
                ['name' => 'grade1'],
                ['name' => 'grade2'],
                ['name' => 'credits']
            ]
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle(['record_id' => $recordId]);

        $this->assertTrue($result['success']);
        $this->assertEquals(262.5, $result['result']);
    }

    // ===== SAVE RESULT TEST =====

    public function testHandleSavesResultToField(): void
    {
        $stmt = self::$db->execute("INSERT INTO computation_test_data (tenant_id, grade1, grade2, grade3)
            VALUES ('test_tenant', 80, 85, 90)");
        $recordId = self::$db->getConnection()->lastInsertId();

        $config = [
            'entity' => 'computation_test_data',
            'expression' => '($grade1 + $grade2 + $grade3) / 3',
            'inputs' => [
                ['name' => 'grade1'],
                ['name' => 'grade2'],
                ['name' => 'grade3']
            ],
            'saveToField' => 'result'
        ];

        $renderer = new ComputationRenderer($config);
        $result = $renderer->handle(['record_id' => $recordId]);

        $this->assertTrue($result['success']);

        // Verify result was saved to database
        $stmt = self::$db->prepare("SELECT result FROM computation_test_data WHERE id = :id");
        $stmt->execute(['id' => $recordId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(85, round($row['result']));
    }

    public function testRenderComputationError(): void
    {
        $config = [
            'displayName' => 'Calculator',
            'entity' => 'computation_test_data',
            'expression' => '10 + 5',
            'inputs' => []
        ];

        $renderer = new ComputationRenderer($config);

        // Use non-existent record to trigger error
        $_GET['record_id'] = 99999;
        $html = $renderer->render();

        $this->assertStringContainsString('Computation error', $html);
        $this->assertStringContainsString('alert-danger', $html);
    }
}
