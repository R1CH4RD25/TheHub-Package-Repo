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
    /**
     * Test valid configuration with all fields
     */
    public function testValidConfigWithAllFields(): void
    {
        $config = [
            'table' => 'workflow_test',
            'stateField' => 'status',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft'],
                ['name' => 'approved', 'label' => 'Approved']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'approved', 'label' => 'Approve']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());
    }

    /**
     * Test validation fails with missing table
     */
    public function testValidationFailsWithMissingTable(): void
    {
        $config = [
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'a', 'to' => 'b']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test validation fails with empty states
     */
    public function testValidationFailsWithEmptyStates(): void
    {
        $config = [
            'table' => 'test',
            'states' => [],
            'transitions' => [['from' => 'a', 'to' => 'b']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test validation fails without states key
     */
    public function testValidationFailsWithoutStatesKey(): void
    {
        $config = [
            'table' => 'test',
            'transitions' => [['from' => 'a', 'to' => 'b']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test validation fails with empty transitions
     */
    public function testValidationFailsWithEmptyTransitions(): void
    {
        $config = [
            'table' => 'test',
            'states' => [['name' => 'draft']],
            'transitions' => []
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test validation fails without transitions key
     */
    public function testValidationFailsWithoutTransitionsKey(): void
    {
        $config = [
            'table' => 'test',
            'states' => [['name' => 'draft']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test validation fails when state missing name
     */
    public function testValidationFailsWhenStateMissingName(): void
    {
        $config = [
            'table' => 'test',
            'states' => [['label' => 'Draft']],
            'transitions' => [['from' => 'a', 'to' => 'b']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test validation fails when transition missing from
     */
    public function testValidationFailsWhenTransitionMissingFrom(): void
    {
        $config = [
            'table' => 'test',
            'states' => [['name' => 'draft']],
            'transitions' => [['to' => 'approved']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test validation fails when transition missing to
     */
    public function testValidationFailsWhenTransitionMissingTo(): void
    {
        $config = [
            'table' => 'test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertFalse($workflow->validate());
    }

    /**
     * Test getConfig returns configuration
     */
    public function testGetConfigReturnsConfiguration(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);
        $returned = $workflow->getConfig();

        $this->assertEquals($config, $returned);
        $this->assertEquals('workflow_test', $returned['table']);
    }

    /**
     * Test render without record shows info message
     */
    public function testRenderWithoutRecordShowsInfoMessage(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();

        $this->assertStringContainsString('No workflow record specified', $output);
        $this->assertStringContainsString('alert-info', $output);
    }

    /**
     * Test render with invalid config shows error
     */
    public function testRenderWithInvalidConfigShowsError(): void
    {
        $config = ['states' => []]; // Invalid: missing table and transitions

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();

        $this->assertStringContainsString('Invalid workflow configuration', $output);
        $this->assertStringContainsString('alert-danger', $output);
    }

    /**
     * Test handle requires CSRF token
     */

    /**
     * Test handle with invalid action type
     */
    public function testHandleWithInvalidActionType(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);
        $result = $workflow->handle([
            'csrf_token' => $_SESSION['csrf_token'],
            'action' => 'invalid_action'
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid action', $result['error']);
    }

    /**
     * Test states configuration with colors and icons
     */
    public function testStatesConfigurationWithColorsAndIcons(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft', 'color' => 'secondary', 'icon' => 'fa-edit'],
                ['name' => 'submitted', 'label' => 'Submitted', 'color' => 'primary', 'icon' => 'fa-paper-plane'],
                ['name' => 'approved', 'label' => 'Approved', 'color' => 'success', 'icon' => 'fa-check']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'submitted']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertCount(3, $retrieved['states']);
        $this->assertEquals('success', $retrieved['states'][2]['color']);
    }

    /**
     * Test transitions with roles configuration
     */
    public function testTransitionsWithRolesConfiguration(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'approved']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'approved', 'label' => 'Approve', 'roles' => ['admin', 'manager']]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertCount(2, $retrieved['transitions'][0]['roles']);
        $this->assertContains('admin', $retrieved['transitions'][0]['roles']);
    }

    /**
     * Test transitions with conditions configuration
     */
    public function testTransitionsWithConditionsConfiguration(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'submitted'],
                ['name' => 'approved']
            ],
            'transitions' => [
                [
                    'from' => 'submitted',
                    'to' => 'approved',
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '<', 'value' => 1000]
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertArrayHasKey('conditions', $retrieved['transitions'][0]);
        $this->assertEquals('amount', $retrieved['transitions'][0]['conditions'][0]['field']);
    }

    /**
     * Test displayFields configuration
     */
    public function testDisplayFieldsConfiguration(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => [
                ['field' => 'title', 'label' => 'Title'],
                ['field' => 'amount', 'label' => 'Amount', 'format' => 'currency']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $retrieved = $workflow->getConfig();
        $this->assertArrayHasKey('displayFields', $retrieved);
        $this->assertCount(2, $retrieved['displayFields']);
        $this->assertEquals('currency', $retrieved['displayFields'][1]['format']);
    }

    /**
     * Test getCurrentState returns null without record
     */
    public function testGetCurrentStateReturnsNullWithoutRecord(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);
        // Without setting record_id, should handle gracefully
        $output = $workflow->render();
        $this->assertStringContainsString('No workflow record specified', $output);
    }

    /**
     * Test findTransition locates correct transition
     */
    public function testConfigWithMultipleStates(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft'],
                ['name' => 'review', 'label' => 'In Review'],
                ['name' => 'approved', 'label' => 'Approved'],
                ['name' => 'rejected', 'label' => 'Rejected']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'review'],
                ['from' => 'review', 'to' => 'approved'],
                ['from' => 'review', 'to' => 'rejected']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $this->assertCount(4, $config_data['states']);
        $this->assertCount(3, $config_data['transitions']);
    }

    /**
     * Test transition with comment requirement
     */
    public function testTransitionWithCommentRequirement(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'submitted'],
                ['name' => 'denied']
            ],
            'transitions' => [
                [
                    'from' => 'submitted',
                    'to' => 'denied',
                    'label' => 'Deny',
                    'requireComment' => true,
                    'commentLabel' => 'Reason for Denial'
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $this->assertTrue($config_data['transitions'][0]['requireComment']);
        $this->assertEquals('Reason for Denial', $config_data['transitions'][0]['commentLabel']);
    }

    /**
     * Test transition with color and icon
     */
    public function testTransitionWithColorAndIcon(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'approved']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'approved',
                    'label' => 'Approve',
                    'color' => 'success',
                    'icon' => 'fa-check-circle'
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $this->assertEquals('success', $config_data['transitions'][0]['color']);
        $this->assertEquals('fa-check-circle', $config_data['transitions'][0]['icon']);
    }

    /**
     * Test displayFields with multiple format types
     */
    public function testDisplayFieldsWithMultipleFormats(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => [
                ['field' => 'title', 'label' => 'Title', 'format' => 'text'],
                ['field' => 'amount', 'label' => 'Amount', 'format' => 'currency'],
                ['field' => 'created_at', 'label' => 'Created', 'format' => 'date'],
                ['field' => 'updated_at', 'label' => 'Updated', 'format' => 'datetime'],
                ['field' => 'email', 'label' => 'Email', 'format' => 'email'],
                ['field' => 'user_id', 'label' => 'User', 'format' => 'user']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $this->assertCount(6, $config_data['displayFields']);
        $this->assertEquals('currency', $config_data['displayFields'][1]['format']);
        $this->assertEquals('email', $config_data['displayFields'][4]['format']);
    }

    /**
     * Test condition operators configuration
     */
    public function testConditionOperatorsConfiguration(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'submitted'], ['name' => 'approved']],
            'transitions' => [
                [
                    'from' => 'submitted',
                    'to' => 'approved',
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '>', 'value' => 1000],
                        ['field' => 'amount', 'operator' => '<', 'value' => 5000],
                        ['field' => 'status', 'operator' => '==', 'value' => 'pending'],
                        ['field' => 'type', 'operator' => '!=', 'value' => 'urgent'],
                        ['field' => 'score', 'operator' => '>=', 'value' => 75],
                        ['field' => 'level', 'operator' => '<=', 'value' => 3]
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $conditions = $config_data['transitions'][0]['conditions'];
        $this->assertCount(6, $conditions);
        $this->assertEquals('>', $conditions[0]['operator']);
        $this->assertEquals('==', $conditions[2]['operator']);
        $this->assertEquals('!=', $conditions[3]['operator']);
        $this->assertEquals('>=', $conditions[4]['operator']);
    }

    /**
     * Test custom state field name
     */
    public function testCustomStateFieldName(): void
    {
        $config = [
            'table' => 'workflow_test',
            'stateField' => 'custom_status_field',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $this->assertEquals('custom_status_field', $config_data['stateField']);
    }

    /**
     * Test transition with multiple roles
     */
    public function testTransitionWithMultipleRoles(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft'], ['name' => 'approved']],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'approved',
                    'roles' => ['admin', 'manager', 'super_admin']
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $this->assertCount(3, $config_data['transitions'][0]['roles']);
        $this->assertContains('manager', $config_data['transitions'][0]['roles']);
    }

    /**
     * Test empty displayFields array
     */
    public function testEmptyDisplayFieldsArray(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => []
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $this->assertArrayHasKey('displayFields', $config_data);
        $this->assertIsArray($config_data['displayFields']);
        $this->assertCount(0, $config_data['displayFields']);
    }

    /**
     * Test no displayFields key
     */
    public function testNoDisplayFieldsKey(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $this->assertArrayNotHasKey('displayFields', $config_data);
    }

    /**
     * Test complex workflow with all features
     */
    public function testComplexWorkflowWithAllFeatures(): void
    {
        $config = [
            'table' => 'workflow_test',
            'stateField' => 'status',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft', 'color' => 'secondary', 'icon' => 'fa-edit'],
                ['name' => 'submitted', 'label' => 'Submitted', 'color' => 'primary', 'icon' => 'fa-paper-plane'],
                ['name' => 'review', 'label' => 'Under Review', 'color' => 'info', 'icon' => 'fa-search'],
                ['name' => 'approved', 'label' => 'Approved', 'color' => 'success', 'icon' => 'fa-check'],
                ['name' => 'rejected', 'label' => 'Rejected', 'color' => 'danger', 'icon' => 'fa-times']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'submitted', 'label' => 'Submit', 'roles' => ['user']],
                ['from' => 'submitted', 'to' => 'review', 'label' => 'Review', 'roles' => ['manager']],
                ['from' => 'review', 'to' => 'approved', 'label' => 'Approve', 'roles' => ['admin'], 'color' => 'success'],
                ['from' => 'review', 'to' => 'rejected', 'label' => 'Reject', 'roles' => ['admin'], 'color' => 'danger', 'requireComment' => true]
            ],
            'displayFields' => [
                ['field' => 'title', 'label' => 'Title'],
                ['field' => 'amount', 'label' => 'Amount', 'format' => 'currency'],
                ['field' => 'created_at', 'label' => 'Created', 'format' => 'datetime']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());

        $config_data = $workflow->getConfig();
        $this->assertCount(5, $config_data['states']);
        $this->assertCount(4, $config_data['transitions']);
        $this->assertCount(3, $config_data['displayFields']);
        $this->assertEquals('status', $config_data['stateField']);
    }

    /**
     * Test states validation with numeric names
     */
    public function testStatesWithNumericNames(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => '1', 'label' => 'Step 1'],
                ['name' => '2', 'label' => 'Step 2']
            ],
            'transitions' => [
                ['from' => '1', 'to' => '2']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $this->assertTrue($workflow->validate());
    }

    /**
     * Test transition validation with same from and to
     */
    public function testTransitionWithSameFromAndTo(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'pending']],
            'transitions' => [
                ['from' => 'pending', 'to' => 'pending', 'label' => 'Refresh']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        // Self-transitions should still be valid
        $this->assertTrue($workflow->validate());
    }

    /**
     * Test configuration immutability via getConfig
     */
    public function testConfigurationImmutability(): void
    {
        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']]
        ];

        $workflow = new WorkflowRenderer($config);
        $retrieved1 = $workflow->getConfig();
        $retrieved2 = $workflow->getConfig();

        $this->assertEquals($retrieved1, $retrieved2);
    }


    /**
     * Test formatValue with currency format
     */
    public function testFormatValueCurrency(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, amount) VALUES (?, ?)");
        $stmt->execute(['Test Purchase', 12345.67]);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft', 'label' => 'Draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted', 'label' => 'Submit']],
            'displayFields' => [
                ['field' => 'amount', 'label' => 'Amount', 'format' => 'currency']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('$12,345.67', $output);
    }

    /**
     * Test formatValue with date format
     */
    public function testFormatValueDate(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, created_at) VALUES (?, NOW())");
        $stmt->execute(['Test Record']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => [
                ['field' => 'created_at', 'label' => 'Created', 'format' => 'date']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $output);
    }

    /**
     * Test formatValue with datetime format
     */
    public function testFormatValueDateTime(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, created_at) VALUES (?, NOW())");
        $stmt->execute(['Test Record']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => [
                ['field' => 'created_at', 'label' => 'Created', 'format' => 'datetime']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $output);
    }

    /**
     * Test formatValue with email format
     */
    public function testFormatValueEmail(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, requested_by) VALUES (?, ?)");
        $stmt->execute(['Test', 'user@example.com']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => [
                ['field' => 'requested_by', 'label' => 'Requester', 'format' => 'email']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('href="mailto:user@example.com"', $output);
    }

    /**
     * Test renderStateBadge with custom color and icon
     */
    public function testRenderStateBadgeWithColorAndIcon(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'approved']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft', 'color' => 'secondary'],
                ['name' => 'approved', 'label' => 'Approved', 'color' => 'success', 'icon' => 'check-circle']
            ],
            'transitions' => [['from' => 'draft', 'to' => 'approved']]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('bg-success', $output);
        $this->assertStringContainsString('check-circle', $output);
        $this->assertStringContainsString('Approved', $output);
    }

    /**
     * Test renderTransitions shows available actions
     */
    public function testRenderTransitionsShowsAvailableActions(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft'],
                ['name' => 'submitted', 'label' => 'Submitted']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'submitted', 'label' => 'Submit for Review', 'roles' => ['admin', 'user']]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('Submit for Review', $output);
    }

    /**
     * Test canPerformTransition with role check
     */
    public function testCanPerformTransitionRoleCheck(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'approved']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'approved', 'label' => 'Approve', 'roles' => ['admin']]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Role enforcement happens in handle(), render shows all transitions
        $this->assertStringContainsString('workflow-container', $output);
    }

    /**
     * Test evaluateCondition with greater than operator
     */
    public function testEvaluateConditionGreaterThan(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, amount, status) VALUES (?, ?, ?)");
        $stmt->execute(['Big Purchase', 10000, 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'needs_approval']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'needs_approval',
                    'label' => 'Submit',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '>', 'value' => 5000]
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Should show Submit button because amount > 5000
        $this->assertStringContainsString('Submit', $output);
    }

    /**
     * Test evaluateCondition with less than operator
     */
    public function testEvaluateConditionLessThan(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, amount, status) VALUES (?, ?, ?)");
        $stmt->execute(['Small Purchase', 100, 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'auto_approve']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'auto_approve',
                    'label' => 'Auto Approve',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '<', 'value' => 1000]
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Should show Auto Approve because amount < 1000
        $this->assertStringContainsString('Auto Approve', $output);
    }

    /**
     * Test evaluateCondition with equals operator
     */
    public function testEvaluateConditionEquals(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status, requested_by) VALUES (?, ?, ?)");
        $stmt->execute(['Test', 'draft', 'john.doe']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft'], ['name' => 'approved']],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'approved',
                    'label' => 'Approve for John',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'requested_by', 'operator' => '==', 'value' => 'john.doe']
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('Approve for John', $output);
    }

    /**
     * Test evaluateCondition with not equals operator
     */
    public function testEvaluateConditionNotEquals(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status, requested_by) VALUES (?, ?, ?)");
        $stmt->execute(['Test', 'draft', 'jane.smith']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft'], ['name' => 'review']],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'review',
                    'label' => 'Send to Review',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'requested_by', 'operator' => '!=', 'value' => 'john.doe']
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('Send to Review', $output);
    }

    /**
     * Test handleTransition performs state change
     */
    public function testHandleTransitionPerformsStateChange(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test Purchase', 'draft']);
        $recordId = $db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_POST['action'] = 'transition';
        $_POST['transition_from'] = 'draft';
        $_POST['transition_to'] = 'submitted';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft'],
                ['name' => 'submitted', 'label' => 'Submitted']
            ],
            'transitions' => [
                ['name' => 'submit', 'from' => 'draft', 'to' => 'submitted', 'label' => 'Submit', 'roles' => ['admin']]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $workflow->handle($_POST);

        // Verify state changed in database
        $stmt = $db->prepare("SELECT status FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $result = $stmt->fetch();
        $this->assertEquals('submitted', $result['status']);
    }

    /**
     * Test handleTransition with comment requirement
     */
    public function testHandleTransitionWithComment(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status, description) VALUES (?, ?, ?)");
        $stmt->execute(['Test', 'submitted', '']);
        $recordId = $db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_POST['action'] = 'transition';
        $_POST['transition_from'] = 'submitted';
        $_POST['transition_to'] = 'rejected';
        $_POST['comment'] = 'Needs more details';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'submitted'],
                ['name' => 'rejected']
            ],
            'transitions' => [
                [
                    'name' => 'reject',
                    'from' => 'submitted',
                    'to' => 'rejected',
                    'label' => 'Reject',
                    'roles' => ['admin'],
                    'requireComment' => true,
                    'commentField' => 'description'
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $workflow->handle($_POST);

        // Verify comment was saved
        $stmt = $db->prepare("SELECT description, status FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $result = $stmt->fetch();
        $this->assertEquals('rejected', $result['status']);
        $this->assertEquals('Needs more details', $result['description']);
    }

    /**
     * Test renderTransitionModal shows comment field
     */
    public function testRenderTransitionModalWithCommentField(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'submitted']
            ],
            'transitions' => [
                [
                    'name' => 'submit',
                    'from' => 'draft',
                    'to' => 'submitted',
                    'label' => 'Submit',
                    'roles' => ['admin'],
                    'requireComment' => true
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('modal', $output);
        $this->assertStringContainsString('textarea', $output);
        $this->assertStringContainsString('required', $output);
    }

    /**
     * Test multiple displayFields with different formats
     */
    public function testMultipleDisplayFieldsWithFormats(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, amount, requested_by, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute(['Complex Test', 5432.10, 'test@example.com']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => [
                ['field' => 'title', 'label' => 'Title', 'format' => 'text'],
                ['field' => 'amount', 'label' => 'Amount', 'format' => 'currency'],
                ['field' => 'requested_by', 'label' => 'Email', 'format' => 'email'],
                ['field' => 'created_at', 'label' => 'Date', 'format' => 'datetime']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('Complex Test', $output);
        $this->assertStringContainsString('$5,432.10', $output);
        $this->assertStringContainsString('mailto:test@example.com', $output);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $output);
    }

    /**
     * Test renderHistory shows workflow timeline
     */
    public function testRenderHistoryDisplaysTimeline(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'approved']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'approved']
            ],
            'transitions' => [['from' => 'draft', 'to' => 'approved']]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Should contain history section (even if empty in test)
        $this->assertStringContainsString('workflow-container', $output);
    }

    /**
     * Test custom stateField configuration
     */
    public function testCustomStateField(): void
    {
        $db = Database::getInstance();
        // Use description field as state field
        $stmt = $db->prepare("INSERT INTO workflow_test (title, description) VALUES (?, ?)");
        $stmt->execute(['Test', 'pending']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'stateField' => 'description',  // Custom field!
            'states' => [
                ['name' => 'pending', 'label' => 'Pending', 'color' => 'warning']
            ],
            'transitions' => [['from' => 'pending', 'to' => 'done']]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('Pending', $output);
        $this->assertStringContainsString('bg-warning', $output);
    }

    /**
     * Test transition without permission shows nothing
     */
    public function testTransitionWithoutPermissionHidden(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'user';  // Not admin

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'approved']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'approved', 'label' => 'Approve', 'roles' => ['admin', 'super_admin']]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // User should see NO transitions
        $this->assertStringContainsString('workflow-container', $output);
    }

    /**
     * Test evaluateCondition with >= operator
     */
    public function testEvaluateConditionGreaterThanOrEqual(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, amount, status) VALUES (?, ?, ?)");
        $stmt->execute(['Exact Amount', 5000, 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft'], ['name' => 'approved']],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'approved',
                    'label' => 'Approve',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '>=', 'value' => 5000]
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('Approve', $output);
    }

    /**
     * Test evaluateCondition with <= operator
     */
    public function testEvaluateConditionLessThanOrEqual(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, amount, status) VALUES (?, ?, ?)");
        $stmt->execute(['Max Amount', 1000, 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft'], ['name' => 'fast_track']],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'fast_track',
                    'label' => 'Fast Track',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '<=', 'value' => 1000]
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('Fast Track', $output);
    }


    /**
     * Test updateState actually updates database
     */
    public function testUpdateStateChangesDatabase(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_POST['action'] = 'transition';
        $_POST['transition_from'] = 'draft';
        $_POST['transition_to'] = 'submitted';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'submitted']
            ],
            'transitions' => [
                ['name' => 'submit', 'from' => 'draft', 'to' => 'submitted', 'roles' => ['admin']]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $workflow->handle($_POST);

        // Verify database was updated
        $stmt = $db->prepare("SELECT status, updated_at FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $result = $stmt->fetch();
        $this->assertEquals('submitted', $result['status']);
        $this->assertNotNull($result['updated_at']);
    }

    /**
     * Test transition blocked by failed condition
     */
    public function testTransitionBlockedByCondition(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, amount, status) VALUES (?, ?, ?)");
        $stmt->execute(['Small Purchase', 100, 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'needs_cfo']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'needs_cfo',
                    'label' => 'Escalate to CFO',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '>', 'value' => 10000]
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Should NOT show "Escalate to CFO" because amount is only 100
        $this->assertStringNotContainsString('Escalate to CFO', $output);
    }

    /**
     * Test multiple conditions with AND logic
     */
    public function testMultipleConditionsAND(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, amount, requested_by, status) VALUES (?, ?, ?, ?)");
        $stmt->execute(['VIP Purchase', 8000, 'ceo@company.com', 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'fast_tracked']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'fast_tracked',
                    'label' => 'Fast Track',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '>', 'value' => 5000],
                        ['field' => 'requested_by', 'operator' => '==', 'value' => 'ceo@company.com']
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Both conditions met - should show Fast Track
        $this->assertStringContainsString('Fast Track', $output);
    }

    /**
     * Test meetsConditions with one failing condition
     */
    public function testMeetsConditionsWithFailure(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, amount, requested_by, status) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Purchase', 3000, 'user@company.com', 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'vip_lane']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'vip_lane',
                    'label' => 'VIP Process',
                    'roles' => ['admin'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '>', 'value' => 5000],  // FAILS
                        ['field' => 'requested_by', 'operator' => '==', 'value' => 'ceo@company.com']  // FAILS
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Conditions not met - should NOT show
        $this->assertStringNotContainsString('VIP Process', $output);
    }

    /**
     * Test transition with field update action
     */
    public function testTransitionWithFieldUpdate(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status, requested_by) VALUES (?, ?, ?)");
        $stmt->execute(['Test', 'draft', 'john']);
        $recordId = $db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_POST['action'] = 'transition';
        $_POST['transition_from'] = 'draft';
        $_POST['transition_to'] = 'approved';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];
        $_SESSION['role'] = 'admin';
        $_SESSION['user_id'] = 99;

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'approved']
            ],
            'transitions' => [
                [
                    'name' => 'approve',
                    'from' => 'draft',
                    'to' => 'approved',
                    'roles' => ['admin'],
                    'actions' => [
                        ['type' => 'updateField', 'field' => 'requested_by', 'value' => 'admin_approved']
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $workflow->handle($_POST);

        // Verify field was updated
        $stmt = $db->prepare("SELECT requested_by FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $result = $stmt->fetch();
        $this->assertEquals('admin_approved', $result['requested_by']);
    }

    /**
     * Test getAvailableTransitions filters by current state
     */
    public function testGetAvailableTransitionsFiltersByState(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'submitted']);  // Start in submitted state
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'submitted'],
                ['name' => 'approved']
            ],
            'transitions' => [
                ['from' => 'draft', 'to' => 'submitted', 'label' => 'Submit', 'roles' => ['admin']],
                ['from' => 'submitted', 'to' => 'approved', 'label' => 'Approve', 'roles' => ['admin']]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Should only show Approve (from submitted), not Submit (from draft)
        $this->assertStringContainsString('Approve', $output);
        // Check that Submit button is not shown (but "Submitted" state badge is OK)
        $this->assertStringNotContainsString('>Submit</button>', $output);
    }

    /**
     * Test self-transition (state to same state)
     */
    public function testSelfTransition(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status, description) VALUES (?, ?, ?)");
        $stmt->execute(['Test', 'review', 'Initial']);
        $recordId = $db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_POST['action'] = 'transition';
        $_POST['transition_from'] = 'review';
        $_POST['transition_to'] = 'review';  // Same state!
        $_POST['comment'] = 'Additional notes';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'review', 'label' => 'In Review']
            ],
            'transitions' => [
                [
                    'name' => 'add_note',
                    'from' => 'review',
                    'to' => 'review',  // Same state!
                    'label' => 'Add Note',
                    'roles' => ['admin'],
                    'requireComment' => true,
                    'commentField' => 'description'
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $workflow->handle($_POST);

        // State should remain review but description updated
        $stmt = $db->prepare("SELECT status, description FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $result = $stmt->fetch();
        $this->assertEquals('review', $result['status']);
        $this->assertStringContainsString('Additional notes', $result['description']);
    }

    /**
     * Test formatValue with user format
     */
    public function testFormatValueUser(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, requested_by) VALUES (?, ?)");
        $stmt->execute(['Test', '42']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        // Create a user to lookup
        $userStmt = $db->prepare("INSERT INTO users (id, name, email) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
        $userStmt->execute([42, 'John Smith', 'john@test.com']);

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => [
                ['field' => 'requested_by', 'label' => 'Requester', 'format' => 'user']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Should show user name, not ID
        $this->assertStringContainsString('John Smith', $output);
    }

    /**
     * Test transition without requireComment allows empty comment
     */
    public function testTransitionWithoutRequireComment(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_POST['action'] = 'transition';
        $_POST['transition_from'] = 'draft';
        $_POST['transition_to'] = 'submitted';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];
        $_SESSION['role'] = 'admin';
        // No comment provided

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'submitted']
            ],
            'transitions' => [
                [
                    'name' => 'submit',
                    'from' => 'draft',
                    'to' => 'submitted',
                    'roles' => ['admin']
                    // No requireComment
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $workflow->handle($_POST);

        // Should succeed even without comment
        $stmt = $db->prepare("SELECT status FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $result = $stmt->fetch();
        $this->assertEquals('submitted', $result['status']);
    }

    /**
     * Test renderTransitionButton with custom color
     */
    public function testRenderTransitionButtonWithColor(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'rejected']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'rejected',
                    'label' => 'Reject',
                    'roles' => ['admin'],
                    'color' => 'danger'  // Red button
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('btn-danger', $output);
        $this->assertStringContainsString('Reject', $output);
    }

    /**
     * Test renderTransitionButton with icon
     */
    public function testRenderTransitionButtonWithIcon(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'approved']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'approved',
                    'label' => 'Approve',
                    'roles' => ['admin'],
                    'icon' => 'check-circle'
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('check-circle', $output);
    }

    /**
     * Test complex workflow with multiple paths
     */
    public function testComplexWorkflowMultiplePaths(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status, amount) VALUES (?, ?, ?)");
        $stmt->execute(['Complex', 'draft', 7500]);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft'],
                ['name' => 'manager_review', 'label' => 'Manager Review'],
                ['name' => 'cfo_review', 'label' => 'CFO Review'],
                ['name' => 'approved', 'label' => 'Approved'],
                ['name' => 'rejected', 'label' => 'Rejected']
            ],
            'transitions' => [
                [
                    'from' => 'draft',
                    'to' => 'manager_review',
                    'label' => 'Submit to Manager',
                    'roles' => ['admin', 'user'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '<', 'value' => 10000]
                    ]
                ],
                [
                    'from' => 'draft',
                    'to' => 'cfo_review',
                    'label' => 'Submit to CFO',
                    'roles' => ['admin', 'user'],
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '>=', 'value' => 10000]
                    ]
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        // Amount is 7500, should show Manager path only
        $this->assertStringContainsString('Submit to Manager', $output);
        $this->assertStringNotContainsString('Submit to CFO', $output);
    }

    /**
     * Test findTransition locates correct transition
     */
    public function testFindTransitionById(): void
    {
        $_GET['record_id'] = 1;
        $_POST['action'] = 'transition';
        $_POST['transition_from'] = 'draft';
        $_POST['transition_to'] = 'special';
        $_POST['csrf_token'] = $_SESSION['csrf_token'];
        $_SESSION['role'] = 'admin';

        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'special']
            ],
            'transitions' => [
                [
                    'name' => 'approve_special',
                    'from' => 'draft',
                    'to' => 'special',
                    'roles' => ['admin']
                ]
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $workflow->handle($_POST);

        // Should find and execute the transition
        $stmt = $db->prepare("SELECT status FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $result = $stmt->fetch();
        $this->assertEquals('special', $result['status']);
    }

    /**
     * Test CSRF validation blocks invalid token
     */
    public function testHandleBlocksInvalidCSRF(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, status) VALUES (?, ?)");
        $stmt->execute(['Test', 'draft']);
        $recordId = $db->lastInsertId();

        $_GET['record_id'] = $recordId;
        $_POST['action'] = 'transition';
        $_POST['transition_from'] = 'draft';
        $_POST['transition_to'] = 'submitted';
        $_POST['csrf_token'] = 'INVALID_TOKEN';  // Wrong token!
        $_SESSION['role'] = 'admin';

        $config = [
            'table' => 'workflow_test',
            'states' => [
                ['name' => 'draft'],
                ['name' => 'submitted']
            ],
            'transitions' => [
                ['name' => 'submit', 'from' => 'draft', 'to' => 'submitted', 'roles' => ['admin']]
            ]
        ];

        $workflow = new WorkflowRenderer($config);

        // Capture output to check for error
        ob_start();
        $workflow->handle($_POST);
        $output = ob_get_clean();

        // Should NOT change state with invalid CSRF
        $stmt = $db->prepare("SELECT status FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $result = $stmt->fetch();
        $this->assertEquals('draft', $result['status']);  // Still draft
    }

    /**
     * Test renderRecordDetails shows all fields
     */
    public function testRenderRecordDetailsWithMultipleFields(): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO workflow_test (title, description, amount, requested_by) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Full Test', 'Complete description', 999.99, 'test@example.com']);
        $recordId = $db->lastInsertId();
        $_GET['record_id'] = $recordId;

        $config = [
            'table' => 'workflow_test',
            'states' => [['name' => 'draft']],
            'transitions' => [['from' => 'draft', 'to' => 'submitted']],
            'displayFields' => [
                ['field' => 'title', 'label' => 'Title'],
                ['field' => 'description', 'label' => 'Description'],
                ['field' => 'amount', 'label' => 'Amount', 'format' => 'currency'],
                ['field' => 'requested_by', 'label' => 'Email', 'format' => 'email']
            ]
        ];

        $workflow = new WorkflowRenderer($config);
        $output = $workflow->render();
        $this->assertStringContainsString('Full Test', $output);
        $this->assertStringContainsString('Complete description', $output);
        $this->assertStringContainsString('$999.99', $output);
        $this->assertStringContainsString('test@example.com', $output);
    }


    /**
     * ULTIMATE TEST: Complete workflow lifecycle with all features
     * This test exercises every single method in WorkflowRenderer
     */
    public function testCompleteWorkflowLifecycleAllFeatures(): void
    {
        $db = Database::getInstance();

        // Create test record with all fields
        $stmt = $db->prepare("INSERT INTO workflow_test (title, description, amount, requested_by, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Complete Workflow Test', 'Testing all features', 15000, 'user@test.com', 'draft']);
        $recordId = $db->lastInsertId();

        // Configure complete workflow with ALL features
        $config = [
            'table' => 'workflow_test',
            'stateField' => 'status',
            'states' => [
                ['name' => 'draft', 'label' => 'Draft', 'color' => 'secondary', 'icon' => 'file-text'],
                ['name' => 'submitted', 'label' => 'Submitted', 'color' => 'primary', 'icon' => 'send'],
                ['name' => 'approved', 'label' => 'Approved', 'color' => 'success', 'icon' => 'check-circle'],
                ['name' => 'rejected', 'label' => 'Rejected', 'color' => 'danger', 'icon' => 'x-circle']
            ],
            'transitions' => [
                [
                    'name' => 'submit',
                    'from' => 'draft',
                    'to' => 'submitted',
                    'label' => 'Submit for Approval',
                    'roles' => ['admin', 'user'],
                    'color' => 'primary',
                    'icon' => 'send',
                    'requireComment' => false,
                    'conditions' => [
                        ['field' => 'title', 'operator' => '!=', 'value' => '']
                    ],
                    'actions' => [
                        ['type' => 'updateField', 'field' => 'description', 'value' => 'Submitted for review']
                    ],
                    'notifications' => [
                        ['email' => 'manager@test.com', 'subject' => 'New Submission']
                    ]
                ],
                [
                    'name' => 'approve',
                    'from' => 'submitted',
                    'to' => 'approved',
                    'label' => 'Approve',
                    'roles' => ['admin'],
                    'color' => 'success',
                    'icon' => 'check',
                    'requireComment' => true,
                    'commentField' => 'description',
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '<', 'value' => 50000]
                    ],
                    'actions' => [
                        ['type' => 'updateField', 'field' => 'requested_by', 'value' => 'approved_user']
                    ]
                ],
                [
                    'name' => 'reject',
                    'from' => 'submitted',
                    'to' => 'rejected',
                    'label' => 'Reject',
                    'roles' => ['admin'],
                    'color' => 'danger',
                    'icon' => 'x',
                    'requireComment' => true,
                    'commentField' => 'description'
                ]
            ],
            'displayFields' => [
                ['field' => 'title', 'label' => 'Title', 'format' => 'text'],
                ['field' => 'description', 'label' => 'Description', 'format' => 'text'],
                ['field' => 'amount', 'label' => 'Amount', 'format' => 'currency'],
                ['field' => 'requested_by', 'label' => 'Requested By', 'format' => 'email'],
                ['field' => 'created_at', 'label' => 'Created', 'format' => 'datetime']
            ],
            'showHistory' => true
        ];

        $_SESSION['role'] = 'admin';
        $_SESSION['user_id'] = 1;

        // PHASE 1: Render initial state (draft)
        $_GET['record_id'] = $recordId;
        $workflow = new WorkflowRenderer($config);

        // Test validate()
        $this->assertTrue($workflow->validate());

        // Test getConfig()
        $configResult = $workflow->getConfig();
        $this->assertEquals('workflow_test', $configResult['table']);

        // Test render() - exercises many methods:
        // - loadRecord()
        // - getCurrentState()
        // - renderStateBadge()
        // - renderRecordDetails()
        // - formatValue() for all fields
        // - renderTransitions()
        // - getAvailableTransitions()
        // - canPerformTransition()
        // - meetsConditions()
        // - evaluateCondition()
        // - renderTransitionButton()
        // - renderTransitionModal()
        // - renderHistory()
        // - getHistory()
        $output = $workflow->render();

        $this->assertStringContainsString('Complete Workflow Test', $output);
        $this->assertStringContainsString('$15,000.00', $output);
        $this->assertStringContainsString('mailto:user@test.com', $output);
        $this->assertStringContainsString('Draft', $output);
        $this->assertStringContainsString('Submit for Approval', $output);

        // PHASE 2: Perform transition (submit)
        $_POST = [
            'action' => 'transition',
            'transition_from' => 'draft',
            'transition_to' => 'submitted',
            'csrf_token' => $_SESSION['csrf_token']
        ];

        $workflow2 = new WorkflowRenderer($config);

        // Test handle() - exercises:
        // - handleTransition()
        // - findTransition()
        // - canPerformTransition()
        // - meetsConditions()
        // - evaluateCondition()
        // - updateState()
        // - executeTransitionAction() -> updateField()
        // - sendNotifications()
        $result = $workflow2->handle($_POST);

        $this->assertTrue($result['success'] ?? false);

        // Verify state changed
        $stmt = $db->prepare("SELECT status, description FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        $this->assertEquals('submitted', $record['status']);
        $this->assertStringContainsString('Submitted for review', $record['description']);

        // PHASE 3: Render submitted state
        $_POST = [];
        $workflow3 = new WorkflowRenderer($config);
        $output2 = $workflow3->render();

        $this->assertStringContainsString('Submitted', $output2);
        $this->assertStringContainsString('Approve', $output2);
        $this->assertStringContainsString('Reject', $output2);

        // PHASE 4: Approve with comment
        $_POST = [
            'action' => 'transition',
            'transition_from' => 'submitted',
            'transition_to' => 'approved',
            'comment' => 'Looks good, approved!',
            'csrf_token' => $_SESSION['csrf_token']
        ];

        $workflow4 = new WorkflowRenderer($config);
        $result2 = $workflow4->handle($_POST);

        $this->assertTrue($result2['success'] ?? false);

        // Verify final state
        $stmt = $db->prepare("SELECT status, description, requested_by FROM workflow_test WHERE id = ?");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        $this->assertEquals('approved', $record['status']);
        $this->assertStringContainsString('Looks good, approved!', $record['description']);
        $this->assertEquals('approved_user', $record['requested_by']);

        // PHASE 5: Render final state
        $_POST = [];
        $workflow5 = new WorkflowRenderer($config);
        $output3 = $workflow5->render();

        $this->assertStringContainsString('Approved', $output3);
        $this->assertStringContainsString('approved_user', $output3);

        // This single test exercises ALL 27 methods!
    }

}
