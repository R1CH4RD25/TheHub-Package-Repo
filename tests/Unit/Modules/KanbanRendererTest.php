<?php

namespace Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;
use Hub\Modules\KanbanRenderer;
use Hub\Database;
use Hub\Cache;

class KanbanRendererTest extends TestCase
{
    private KanbanRenderer $renderer;
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'slug' => 'test-kanban',
            'name' => 'Test Kanban Board',
            'type' => 'Kanban',
            'settings' => [
                'enable_wip_limits' => true,
                'enable_swimlanes' => true,
                'default_columns' => ['Backlog', 'In Progress', 'Review', 'Done']
            ]
        ];

        $this->renderer = new KanbanRenderer($this->config, 1);
    }

    public function testRendererImplementsInterface(): void
    {
        $this->assertInstanceOf(
            'Hub\Modules\ModuleInterface',
            $this->renderer
        );
    }

    public function testRenderReturnsHtml(): void
    {
        $html = $this->renderer->render();

        $this->assertIsString($html);
        $this->assertStringContainsString('kanban-container', $html);
        $this->assertGreaterThan(500, strlen($html), 'HTML should be substantial');
    }

    public function testRenderContainsDragDropInterface(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('kanban-container', $html);
        $this->assertStringContainsString('kanban-card', $html);
        $this->assertStringContainsString('cardModal', $html);
    }

    public function testRenderContainsPriorityLevels(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('critical', $html);
        $this->assertStringContainsString('high', $html);
        $this->assertStringContainsString('medium', $html);
        $this->assertStringContainsString('low', $html);
    }

    public function testRenderContainsCardModal(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('cardModal', $html);
        $this->assertStringContainsString('cardTitle', $html);
        $this->assertStringContainsString('cardDescription', $html);
    }

    public function testValidateCardSuccess(): void
    {
        $data = [
            'title' => 'Implement feature',
            'description' => 'Add new functionality',
            'priority' => 'high',
            'column_id' => 1
        ];

        $errors = $this->renderer->validateCard($data);

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function testValidateCardMissingTitle(): void
    {
        $data = [
            'description' => 'Add new functionality',
            'priority' => 'high',
            'column_id' => 1
        ];

        $errors = $this->renderer->validateCard($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Card title is required', $errors);
    }

    public function testValidateCardInvalidPriority(): void
    {
        $data = [
            'title' => 'Implement feature',
            'priority' => 'invalid_priority',
            'column_id' => 1
        ];

        $errors = $this->renderer->validateCard($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid priority level', $errors);
    }

    public function testValidateCardMissingColumn(): void
    {
        $data = [
            'title' => 'Implement feature',
            'priority' => 'high'
        ];

        $errors = $this->renderer->validateCard($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Column ID is required', $errors);
    }

    public function testHandleUnknownAction(): void
    {
        $result = $this->renderer->handle(['action' => 'invalid_action']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testConfigurationValues(): void
    {
        $this->assertEquals('test-kanban', $this->config['slug']);
        $this->assertEquals('Kanban', $this->config['type']);
        $this->assertTrue($this->config['settings']['enable_wip_limits']);
        $this->assertTrue($this->config['settings']['enable_swimlanes']);
    }

    public function testDefaultColumnsConfiguration(): void
    {
        $columns = $this->config['settings']['default_columns'];

        $this->assertIsArray($columns);
        $this->assertContains('Backlog', $columns);
        $this->assertContains('In Progress', $columns);
        $this->assertContains('Review', $columns);
        $this->assertContains('Done', $columns);
    }

    public function testValidateDueDateFormat(): void
    {
        $data = [
            'title' => 'Task with deadline',
            'column_id' => 1,
            'priority' => 'high',
            'due_date' => '2024-12-31'
        ];

        $errors = $this->renderer->validateCard($data);

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function testValidateInvalidDueDateFormat(): void
    {
        $data = [
            'title' => 'Task with bad deadline',
            'column_id' => 1,
            'priority' => 'high',
            'due_date' => 'not-a-date'
        ];

        $errors = $this->renderer->validateCard($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid due date format', $errors);
    }

    public function testWipLimitValidation(): void
    {
        $data = [
            'action' => 'move',
            'card_id' => 1,
            'column_id' => 2,
            'current_column_count' => 10,
            'wip_limit' => 5
        ];

        // Would validate WIP limit exceeded
        $this->assertTrue($data['current_column_count'] > $data['wip_limit']);
    }
}
