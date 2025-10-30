<?php

namespace Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;
use Hub\Modules\CalendarRenderer;
use Hub\Database;
use Hub\Cache;

class CalendarRendererTest extends TestCase
{
    private CalendarRenderer $renderer;
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'slug' => 'test-calendar',
            'name' => 'Test Calendar',
            'type' => 'Calendar',
            'settings' => [
                'default_view' => 'month',
                'enable_recurring' => true,
                'enable_reminders' => true
            ]
        ];

        $this->renderer = new CalendarRenderer($this->config, 1);
    }

    public function testRendererImplementsInterface(): void
    {
        $this->assertInstanceOf(
            'Hub\Modules\ModuleRendererInterface',
            $this->renderer
        );
    }

    public function testRenderReturnsHtml(): void
    {
        $html = $this->renderer->render();

        $this->assertIsString($html);
        $this->assertStringContainsString('calendar-module', $html);
        $this->assertStringContainsString('Test Calendar', $html);
    }

    public function testRenderContainsViewModes(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('month-view', $html);
        $this->assertStringContainsString('week-view', $html);
        $this->assertStringContainsString('day-view', $html);
        $this->assertStringContainsString('agenda-view', $html);
    }

    public function testRenderContainsEventCategories(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('meeting', $html);
        $this->assertStringContainsString('deadline', $html);
        $this->assertStringContainsString('appointment', $html);
        $this->assertStringContainsString('event', $html);
    }

    public function testRenderContainsEventModal(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('eventModal', $html);
        $this->assertStringContainsString('eventTitle', $html);
        $this->assertStringContainsString('eventDate', $html);
    }

    public function testValidateEventSuccess(): void
    {
        $data = [
            'title' => 'Team Meeting',
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'category' => 'meeting'
        ];

        $errors = $this->renderer->validate($data);

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function testValidateEventMissingTitle(): void
    {
        $data = [
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'category' => 'meeting'
        ];

        $errors = $this->renderer->validate($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Event title is required', $errors);
    }

    public function testValidateEventMissingDate(): void
    {
        $data = [
            'title' => 'Team Meeting',
            'start_time' => '10:00',
            'category' => 'meeting'
        ];

        $errors = $this->renderer->validate($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Event date is required', $errors);
    }

    public function testValidateEventInvalidCategory(): void
    {
        $data = [
            'title' => 'Team Meeting',
            'date' => '2024-01-15',
            'start_time' => '10:00',
            'category' => 'invalid_category'
        ];

        $errors = $this->renderer->validate($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid event category', $errors);
    }

    public function testValidateRecurringEventRequiresFrequency(): void
    {
        $data = [
            'title' => 'Weekly Standup',
            'date' => '2024-01-15',
            'start_time' => '09:00',
            'category' => 'meeting',
            'is_recurring' => '1'
            // Missing recurrence_frequency
        ];

        $errors = $this->renderer->validate($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Recurrence frequency is required for recurring events', $errors);
    }

    public function testHandleUnknownAction(): void
    {
        $result = $this->renderer->handle('invalid_action', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testConfigurationValues(): void
    {
        $this->assertEquals('test-calendar', $this->config['slug']);
        $this->assertEquals('Calendar', $this->config['type']);
        $this->assertEquals('month', $this->config['settings']['default_view']);
        $this->assertTrue($this->config['settings']['enable_recurring']);
    }

    public function testAllDayEventValidation(): void
    {
        $data = [
            'title' => 'Holiday',
            'date' => '2024-12-25',
            'all_day' => '1',
            'category' => 'event'
        ];

        $errors = $this->renderer->validate($data);

        $this->assertIsArray($errors);
        $this->assertEmpty($errors); // All-day events don't need times
    }
}
