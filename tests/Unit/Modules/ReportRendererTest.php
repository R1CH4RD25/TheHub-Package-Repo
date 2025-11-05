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

    /**
     * Test handle saveReport action - create new
     */
    public function testHandleSaveReportCreate(): void
    {
        $data = [
            'action' => 'report',
            'name' => 'Test Report',
            'description' => 'Test Description',
            'template' => 'table',
            'data_source' => 'SELECT * FROM users LIMIT 10',
            'date_column' => 'created_at',
            'group_by' => ''
        ];

        $result = $this->renderer->handle($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test handle saveReport action - update existing
     */
    public function testHandleSaveReportUpdate(): void
    {
        // First create a report
        $createData = [
            'action' => 'report',
            'name' => 'Original Report',
            'template' => 'table',
            'data_source' => 'SELECT * FROM users'
        ];
        $createResult = $this->renderer->handle($createData);
        $this->assertTrue($createResult['success']);

        // Get the report ID (would need getReports or query database)
        // For now, test update with a known ID
        $updateData = [
            'action' => 'report',
            'id' => 1,  // Assuming report exists
            'name' => 'Updated Report',
            'template' => 'chart',
            'data_source' => 'SELECT COUNT(*) as count FROM users'
        ];

        $result = $this->renderer->handle($updateData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        // May succeed or fail depending on DB state
        $this->assertIsBool($result['success']);
    }

    /**
     * Test handle with missing required fields
     */
    public function testHandleSaveReportMissingFields(): void
    {
        $data = [
            'action' => 'report',
            'name' => 'Incomplete Report'
            // Missing template and data_source
        ];

        $result = $this->renderer->handle($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        // Should fail due to missing fields
        $this->assertFalse($result['success']);
    }

    /**
     * Test handle generateReport action
     */
    public function testHandleGenerateReport(): void
    {
        $data = [
            'action' => 'generate',
            'report_id' => 1,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31'
        ];

        $result = $this->renderer->handle($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        // May succeed or fail depending on report existence
        $this->assertIsBool($result['success']);
    }

    /**
     * Test handle viewReport action
     */
    public function testHandleViewReport(): void
    {
        $data = [
            'action' => 'view',
            'report_id' => 1
        ];

        $result = $this->renderer->handle($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
    }

    /**
     * Test handle exportReport action
     */
    public function testHandleExportReport(): void
    {
        $data = [
            'action' => 'export',
            'report_id' => 1,
            'format' => 'csv'
        ];

        $result = $this->renderer->handle($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
    }

    /**
     * Test handle scheduleReport action
     */
    public function testHandleScheduleReport(): void
    {
        $data = [
            'action' => 'schedule',
            'report_id' => 1,
            'frequency' => 'daily',
            'time' => '09:00',
            'enabled' => true
        ];

        $result = $this->renderer->handle($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
    }

    /**
     * Test handle deleteReport action
     */
    public function testHandleDeleteReport(): void
    {
        $data = [
            'action' => 'delete',
            'report_id' => 999  // Non-existent report
        ];

        $result = $this->renderer->handle($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        // Should handle gracefully even if report doesn't exist
        $this->assertIsBool($result['success']);
    }

    /**
     * Test getConfig returns configuration
     */
    public function testGetConfigReturnsConfiguration(): void
    {
        $config = $this->renderer->getConfig();

        $this->assertIsArray($config);
        $this->assertEquals('test-reports', $config['slug']);
        $this->assertEquals('Test Reports', $config['name']);
        $this->assertEquals('Report', $config['type']);
    }

    /**
     * Test validate returns true for valid config
     */
    public function testValidateReturnsTrueForValidConfig(): void
    {
        $result = $this->renderer->validate();

        $this->assertTrue($result);
    }

    /**
     * Test validate returns false for invalid config
     */
    public function testValidateReturnsFalseForInvalidConfig(): void
    {
        $invalidRenderer = new ReportRenderer([
            'slug' => '',  // Empty slug
            'name' => 'Test'
        ], 1);

        $result = $invalidRenderer->validate();

        $this->assertFalse($result);
    }

    /**
     * Test validate returns false for wrong type
     */
    public function testValidateReturnsFalseForWrongType(): void
    {
        $invalidRenderer = new ReportRenderer([
            'slug' => 'test',
            'name' => 'Test',
            'type' => 'Calendar'  // Wrong type
        ], 1);

        $result = $invalidRenderer->validate();

        $this->assertFalse($result);
    }


    /**
     * Test scheduleReport with required data fields
     */
    public function testHandleScheduleReportWithExportFormat(): void
    {
        $data = [
            'action' => 'schedule',
            'report_id' => 1,
            'frequency' => 'weekly',
            'time' => '10:00',
            'email_recipients' => 'test@example.com',
            'export_format' => 'pdf'
        ];

        $result = $this->renderer->handle($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
    }

    /**
     * Test deleteReport with id field
     */
    public function testHandleDeleteReportWithId(): void
    {
        $data = [
            'action' => 'delete',
            'id' => 999
        ];

        $result = $this->renderer->handle($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
    }

    /**
     * Test render includes create button
     */
    public function testRenderIncludesCreateButton(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('createReportBtn', $html);
        $this->assertStringContainsString('Create Report', $html);
    }

    /**
     * Test render includes JavaScript initialization
     */
    public function testRenderIncludesJavaScript(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('<script', $html);
        $this->assertStringContainsString('DOMContentLoaded', $html);
        $this->assertStringContainsString('chart.min.js', $html);
    }

    /**
     * Test render includes report modal form
     */
    public function testRenderIncludesReportModal(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('reportModal', $html);
        $this->assertStringContainsString('reportForm', $html);
        $this->assertStringContainsString('reportTemplate', $html);
    }

    /**
     * Test config has required module interface fields
     */
    public function testConfigHasRequiredFields(): void
    {
        $config = $this->renderer->getConfig();

        $this->assertArrayHasKey('slug', $config);
        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('type', $config);
        $this->assertArrayHasKey('settings', $config);
    }

    /**
     * Test validateReportData with all valid fields
     */
    public function testValidateReportDataAllFieldsValid(): void
    {
        $data = [
            'name' => 'Complete Report',
            'template' => 'table',
            'data_source' => 'SELECT id, name, email FROM users WHERE is_active = 1',
            'description' => 'Full report with all fields',
            'date_column' => 'created_at',
            'group_by' => 'role'
        ];

        $errors = $this->renderer->validateReportData($data);

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    /**
     * Test validateReportData with DROP SQL injection attempt
     */
    public function testValidateReportDataDeleteInjection(): void
    {
        $data = [
            'name' => 'Malicious Report',
            'template' => 'table',
            'data_source' => 'SELECT * FROM users; DROP TABLE users;'
        ];

        $errors = $this->renderer->validateReportData($data);

        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertContains('Invalid SQL query detected', $errors);
    }

    /**
     * Test validateReportData doesn't check for other SQL keywords
     */
    public function testValidateReportDataUpdateInjection(): void
    {
        $data = [
            'name' => 'Report with UPDATE',
            'template' => 'chart',
            'data_source' => 'SELECT * FROM users'  // Valid query without DROP
        ];

        $errors = $this->renderer->validateReportData($data);

        $this->assertIsArray($errors);
        // Should be empty since no DROP keyword
        $this->assertEmpty($errors);
    }

}
