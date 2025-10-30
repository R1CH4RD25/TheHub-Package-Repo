<?php

namespace Tests\Integration;

use Hub\Modules\WorkflowRenderer;
use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\TestDatabase;

/**
 * Integration tests for WorkflowRenderer
 * Tests workflow state machines, transitions, validation, and permissions
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Modules\WorkflowRenderer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Database::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Auth::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\AuditLogger::class)]
class WorkflowRendererIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::beginTransaction();
        $_GET = []; // Clear query parameters
        $_POST = [];
        $_SESSION['csrf_token'] = 'test-csrf-token';
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';

        // Create workflow test table
        $db = Database::getInstance();
        $stmt = $db->prepare("CREATE TEMPORARY TABLE IF NOT EXISTS workflow_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'draft',
            requested_by VARCHAR(255),
            amount DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $stmt->execute();
    }    protected function tearDown(): void
    {
        TestDatabase::rollBack();
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    /**
     * Test basic workflow configuration validation
     */
    public function testWorkflowValidation(): void
    {
        // Valid workflow
        $config = [
            'table' => 'workflow_records',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft', 'color' => 'secondary'],
                ['name' => 'submitted', 'label' => 'Submitted', 'color' => 'primary'],
                ['name' => 'approved', 'label' => 'Approved', 'color' => 'success']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'submitted', 'label' => 'Submit', 'roles' => ['user']],
                ['from' => 'submitted', 'to' => 'approved', 'label' => 'Approve', 'roles' => ['admin']]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        // Missing table
        $invalidConfig = ['states' => [], 'transitions' => []];
        $workflow = new WorkflowRenderer($invalidConfig);
        $this->assertFalse($workflow->validate());

        // Missing states
        $invalidConfig = ['table' => 'test', 'transitions' => []];
        $workflow = new WorkflowRenderer($invalidConfig);
        $this->assertFalse($workflow->validate());

        // Missing transitions
        $invalidConfig = ['table' => 'test', 'states' => [['name' => 'draft']]];
        $workflow = new WorkflowRenderer($invalidConfig);
        $this->assertFalse($workflow->validate());

        // Invalid state (missing name)
        $invalidConfig = [
            'table' => 'test',
            'states' => [['label' => 'Draft']],
            'transitions' => [['from' => 'a', 'to' => 'b']]
        ];
        $workflow = new WorkflowRenderer($invalidConfig);
        $this->assertFalse($workflow->validate());

        // Invalid transition (missing from)
        $invalidConfig = [
            'table' => 'test',
            'states' => [['name' => 'draft']],
            'transitions' => [['to' => 'submitted']]
        ];
        $workflow = new WorkflowRenderer($invalidConfig);
        $this->assertFalse($workflow->validate());

        // Invalid transition (missing to)
        $invalidConfig = [
            'table' => 'test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft']]
        ];
        $workflow = new WorkflowRenderer($invalidConfig);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test getConfig returns configuration
     */
    public function testGetConfig(): void
    {
        $config = [
            'table' => 'workflow_records',
            'stateField' => 'status',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'submitted']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $retrieved = $workflow->getConfig();

        $this->assertEquals('workflow_records', $retrieved['table']);
        $this->assertEquals('status', $retrieved['stateField']);
        $this->assertIsArray($retrieved['states']);
        $this->assertIsArray($retrieved['transitions']);
    }

    /**
     * Test workflow without record ID shows info message
     */
    public function testWorkflowWithoutRecordId(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [['name' => 'draft', 'label' => 'Draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);
        $html = $workflow->render();

        $this->assertStringContainsString('No workflow record specified', $html);
        $this->assertStringContainsString('alert-info', $html);
    }

    /**
     * Test invalid configuration renders error
     */
    public function testInvalidConfigurationRendersError(): void
    {
        $config = ['table' => 'test']; // Missing required fields

        $workflow = new WorkflowRenderer($config);
        $html = $workflow->render();

        $this->assertStringContainsString('Invalid workflow configuration', $html);
        $this->assertStringContainsString('alert-danger', $html);
    }

    /**
     * Test handle method requires CSRF token
     */
    public function testHandleRequiresCsrfToken(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);

        // No CSRF token
        $result = $workflow->handle(['action' => 'transition']);
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid security token', $result['error']);

        // Wrong CSRF token
        $result = $workflow->handle([
            'action' => 'transition',
            'csrf_token' => 'wrong-token'
        ]);
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid security token', $result['error']);
    }

    /**
     * Test handle method with invalid action
     */
    public function testHandleWithInvalidAction(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);

        $result = $workflow->handle([
            'action' => 'invalid_action',
            'csrf_token' => $_SESSION['csrf_token']
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid action', $result['error']);
    }

    /**
     * Test workflow state configuration structure
     */
    public function testWorkflowStateConfiguration(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [
                [
                    'name' => 'draft',
                    'label' => 'Draft',
                    'color' => 'secondary',
                    'icon' => 'bi-file-text'
                ],
                [
                    'name' => 'submitted',
                    'label' => 'Awaiting Review',
                    'color' => 'warning',
                    'icon' => 'bi-clock'
                ],
                [
                    'name' => 'approved',
                    'label' => 'Approved',
                    'color' => 'success',
                    'icon' => 'bi-check-circle'
                ],
                [
                    'name' => 'rejected',
                    'label' => 'Rejected',
                    'color' => 'danger',
                    'icon' => 'bi-x-circle'
                ]
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'submitted']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertCount(4, $retrieved['states']);

        // Verify first state structure
        $this->assertEquals('draft', $retrieved['states'][0]['name']);
        $this->assertEquals('Draft', $retrieved['states'][0]['label']);
        $this->assertEquals('secondary', $retrieved['states'][0]['color']);
        $this->assertEquals('bi-file-text', $retrieved['states'][0]['icon']);
    }

    /**
     * Test workflow transition configuration structure
     */
    public function testWorkflowTransitionConfiguration(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'submitted'],
                ['name' => 'approved']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'submitted',
                    'label' => 'Submit for Review',
                    'roles' => ['user', 'admin'],
                    'requireComment' => false,
                    'notify' => ['manager']
                ],
                [
                    'from' => 'submitted',
                    'to' => 'approved',
                    'label' => 'Approve',
                    'roles' => ['admin'],
                    'requireComment' => true,
                    'notify' => ['user', 'hr']
                ],
                [
                    'from' => 'submitted',
                    'to' => 'draft',
                    'label' => 'Return to Draft',
                    'roles' => ['admin'],
                    'requireComment' => true
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertCount(3, $retrieved['transitions']);

        // Verify first transition structure
        $this->assertEquals('draft', $retrieved['transitions'][0]['from']);
        $this->assertEquals('submitted', $retrieved['transitions'][0]['to']);
        $this->assertEquals('Submit for Review', $retrieved['transitions'][0]['label']);
        $this->assertIsArray($retrieved['transitions'][0]['roles']);
        $this->assertContains('user', $retrieved['transitions'][0]['roles']);
        $this->assertContains('admin', $retrieved['transitions'][0]['roles']);
    }

    /**
     * Test workflow with custom state field
     */
    public function testWorkflowWithCustomStateField(): void
    {
        $config = [
            'table' => 'workflow_records',
            'stateField' => 'approval_status', // Custom field name
            'states' => [
                ['name' => 'pending', 'label' => 'Pending']
            ],
            'transitions' => [
                ['from' => 'pending', 'to' => 'complete']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertEquals('approval_status', $retrieved['stateField']);
    }

    /**
     * Test workflow with display fields configuration
     */
    public function testWorkflowWithDisplayFields(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => [
                ['field' => 'title', 'label' => 'Title'],
                ['field' => 'description', 'label' => 'Description'],
                ['field' => 'requested_by', 'label' => 'Requested By'],
                ['field' => 'created_at', 'label' => 'Created', 'format' => 'date']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertIsArray($retrieved['displayFields']);
        $this->assertCount(4, $retrieved['displayFields']);
    }

    /**
     * Test workflow supports multiple transitions from same state
     */
    public function testWorkflowSupportsMultipleTransitionsFromSameState(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [
                ['name' => 'submitted'],
                ['name' => 'approved'],
                ['name' => 'rejected'],
                ['name' => 'on_hold']
            ],
            'transitions' => [
                ['from' => 'submitted', 'to' => 'approved', 'label' => 'Approve'],
                ['from' => 'submitted', 'to' => 'rejected', 'label' => 'Reject'],
                ['from' => 'submitted', 'to' => 'on_hold', 'label' => 'Put On Hold']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertCount(3, $retrieved['transitions']);

        // All transitions from 'submitted'
        foreach ($retrieved['transitions'] as $transition) {
            $this->assertEquals('submitted', $transition['from']);
        }
    }

    /**
     * Test workflow with conditional transitions
     */
    public function testWorkflowWithConditionalTransitions(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [
                ['name' => 'submitted'],
                ['name' => 'approved']
            ],
            'transitions' => [
                [
                    'from' => 'submitted',
                    'to' => 'approved',
                    'label' => 'Approve',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '<=', 'value' => 10000]
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertArrayHasKey('conditions', $retrieved['transitions'][0]);
        $this->assertIsArray($retrieved['transitions'][0]['conditions']);
    }

    /**
     * Test workflow validation catches empty states array
     */
    public function testWorkflowValidationCatchesEmptyStatesArray(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [], // Empty array
            'transitions' => [['from' => 'a', 'to' => 'b']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test workflow validation catches empty transitions array
     */
    public function testWorkflowValidationCatchesEmptyTransitionsArray(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [['name' => 'draft']],
            'transitions' => [] // Empty array
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test workflow with notification configuration
     */
    public function testWorkflowWithNotificationConfiguration(): void
    {
        $config = [
            'table' => 'workflow_records',
            'states' => [
                ['name' => 'submitted'],
                ['name' => 'approved']
            ],
            'transitions' => [
                [
                    'from' => 'submitted',
                    'to' => 'approved',
                    'label' => 'Approve',
                    'notify' => [
                        'submitter',
                        'manager',
                        'hr@example.com'
                    ],
                    'emailTemplate' => 'workflow_approval'
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $transition = $retrieved['transitions'][0];

        $this->assertArrayHasKey('notify', $transition);
        $this->assertIsArray($transition['notify']);
        $this->assertContains('submitter', $transition['notify']);
        $this->assertContains('manager', $transition['notify']);
        $this->assertContains('hr@example.com', $transition['notify']);
    }

    /**
     * Test workflow configuration is returned exactly as provided
     */
    public function testWorkflowConfigurationIsReturnedExactly(): void
    {
        $config = [
            'table' => 'workflow_records',
            'stateField' => 'status',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft', 'color' => 'secondary']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'submitted', 'label' => 'Submit']
            ],
            'displayFields' => [
                ['field' => 'title', 'label' => 'Title']
            ],
            'customProperty' => 'custom value'
        ];

        $workflow = new WorkflowRenderer($config);
        $retrieved = $workflow->getConfig();

        // Should return exact configuration
        $this->assertEquals($config, $retrieved);
        $this->assertEquals('custom value', $retrieved['customProperty']);
    }

    /**
     * Test workflow rendering with real record
     */
    public function testWorkflowRenderingWithRealRecord(): void
    {
        // Create a workflow record
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, description, status, requested_by, amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            "Purchase Request",
            "Need new laptops",
            "draft",
            "John Doe",
            5000.00
        ]);
        $recordId = $db->lastInsertId();

        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'stateField' => 'status',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft', 'color' => 'secondary'],
                ['name' => 'submitted', 'label' => 'Submitted', 'color' => 'primary'],
                ['name' => 'approved', 'label' => 'Approved', 'color' => 'success']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'submitted', 'label' => 'Submit', 'roles' => ['user']],
                ['from' => 'submitted', 'to' => 'approved', 'label' => 'Approve', 'roles' => ['admin']]
            ],
            'displayFields' => [
                ['field' => 'title', 'label' => 'Title'],
                ['field' => 'description', 'label' => 'Description'],
                ['field' => 'amount', 'label' => 'Amount']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $html = $workflow->render();

        // Should render workflow UI
        $this->assertStringContainsString('workflow-container', $html);
        $this->assertStringContainsString('Draft', $html);
        $this->assertStringContainsString('Purchase Request', $html);
        $this->assertStringContainsString('Current Status', $html);
    }

    /**
     * Test workflow record not found shows error
     */
    public function testWorkflowRecordNotFound(): void
    {
        $_GET['record_id'] = 99999; // Non-existent ID

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);
        $html = $workflow->render();

        $this->assertStringContainsString('Workflow record not found', $html);
        $this->assertStringContainsString('alert-danger', $html);
    }
}
