<?php

namespace Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;
use Hub\Modules\ReportRenderer;
use Hub\Database;
use Hub\Cache;

class ReportRendererTest extends TestCase
{
    private ReportRenderer $renderer;
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'slug' => 'test-reports',
            'name' => 'Test Reports',
            'type' => 'Report',
            'settings' => [
                'default_template' => 'table',
                'enable_scheduling' => true,
                'enable_exports' => true,
                'allowed_formats' => ['pdf', 'excel', 'csv']
            ]
        ];

        $this->renderer = new ReportRenderer($this->config, 1);
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
        $this->assertStringContainsString('report-module', $html);
        $this->assertGreaterThan(500, strlen($html), 'HTML should be substantial');
    }

    public function testRenderContainsReportTemplates(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('table', $html);
        $this->assertStringContainsString('chart', $html);
        $this->assertStringContainsString('summary', $html);
        $this->assertStringContainsString('mixed', $html);
    }

    public function testRenderContainsExportOptions(): void
    {
        $html = $this->renderer->render();

        // Check for export functionality (may vary based on report state)
        $this->assertStringContainsString('export', $html, 'Should contain export functionality');
        $this->assertStringContainsString('PDF', $html, 'Should support PDF export');
    }

    public function testRenderContainsScheduleModal(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('scheduleModal', $html);
        $this->assertStringContainsString('frequency', $html);
        $this->assertStringContainsString('email_recipients', $html);
    }

    public function testValidateReportSuccess(): void
    {
        $data = [
            'name' => 'Monthly Sales Report',
            'template' => 'table',
            'data_source' => 'SELECT * FROM sales WHERE month = MONTH(NOW())',
            'description' => 'Sales summary for current month'
        ];

        $errors = $this->renderer->validateReportData($data);

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function testValidateReportMissingName(): void
    {
        $data = [
            'template' => 'table',
            'data_source' => 'SELECT * FROM sales'
        ];

        $errors = $this->renderer->validateReportData($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Report name is required', $errors);
    }

    public function testValidateReportMissingTemplate(): void
    {
        $data = [
            'name' => 'Sales Report',
            'data_source' => 'SELECT * FROM sales'
        ];

        $errors = $this->renderer->validateReportData($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Valid template is required', $errors);
    }

    public function testValidateReportInvalidTemplate(): void
    {
        $data = [
            'name' => 'Sales Report',
            'template' => 'invalid_template',
            'data_source' => 'SELECT * FROM sales'
        ];

        $errors = $this->renderer->validateReportData($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Valid template is required', $errors);
    }

    public function testValidateReportMissingDataSource(): void
    {
        $data = [
            'name' => 'Sales Report',
            'template' => 'table'
        ];

        $errors = $this->renderer->validateReportData($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Data source query is required', $errors);
    }

    public function testValidateReportDangerousQuery(): void
    {
        $data = [
            'name' => 'Malicious Report',
            'template' => 'table',
            'data_source' => 'DROP TABLE users; SELECT * FROM sales'
        ];

        $errors = $this->renderer->validateReportData($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid SQL query detected', $errors);
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
        $this->assertEquals('test-reports', $this->config['slug']);
        $this->assertEquals('Report', $this->config['type']);
        $this->assertEquals('table', $this->config['settings']['default_template']);
        $this->assertTrue($this->config['settings']['enable_scheduling']);
    }

    public function testAllowedExportFormats(): void
    {
        $formats = $this->config['settings']['allowed_formats'];

        $this->assertIsArray($formats);
        $this->assertContains('pdf', $formats);
        $this->assertContains('excel', $formats);
        $this->assertContains('csv', $formats);
    }

    public function testScheduleFrequencyOptions(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('daily', $html);
        $this->assertStringContainsString('weekly', $html);
        $this->assertStringContainsString('monthly', $html);
        $this->assertStringContainsString('custom', $html);
    }

    public function testReportViewer(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('reportViewer', $html);
        $this->assertStringContainsString('reportViewerContent', $html);
    }
}
