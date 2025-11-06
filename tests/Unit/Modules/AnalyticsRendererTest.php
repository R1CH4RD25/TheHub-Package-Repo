<?php

namespace Hub\Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;
use Hub\Modules\AnalyticsRenderer;

class AnalyticsRendererTest extends TestCase
{
    /**
     * Test constructor initializes with config
     */
    public function testConstructorInitializesWithConfig(): void
    {
        $config = ['entity' => 'test_entity', 'charts' => []];
        $renderer = new AnalyticsRenderer($config);

        $this->assertInstanceOf(AnalyticsRenderer::class, $renderer);
    }

    /**
     * Test getConfig returns configuration
     */
    public function testGetConfigReturnsConfiguration(): void
    {
        $config = [
            'entity' => 'incidents',
            'displayName' => 'Incident Analytics',
            'charts' => [
                ['type' => 'line', 'title' => 'Trends']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $result = $renderer->getConfig();

        $this->assertEquals($config, $result);
        $this->assertEquals('incidents', $result['entity']);
        $this->assertEquals('Incident Analytics', $result['displayName']);
        $this->assertCount(1, $result['charts']);
    }

    /**
     * Test validate returns false when charts missing
     */
    public function testValidateReturnsFalseWhenChartsMissing(): void
    {
        $config = ['entity' => 'test_entity'];
        $renderer = new AnalyticsRenderer($config);

        $result = $renderer->validate();

        $this->assertFalse($result);
    }

    /**
     * Test validate returns false when entity missing
     */
    public function testValidateReturnsFalseWhenEntityMissing(): void
    {
        $config = ['charts' => [['type' => 'line', 'title' => 'Test']]];
        $renderer = new AnalyticsRenderer($config);

        $result = $renderer->validate();

        $this->assertFalse($result);
    }

    /**
     * Test validate returns false when charts empty array
     */
    public function testValidateReturnsFalseWhenChartsEmptyArray(): void
    {
        $config = ['entity' => 'test_entity', 'charts' => []];
        $renderer = new AnalyticsRenderer($config);

        $result = $renderer->validate();

        $this->assertFalse($result);
    }

    /**
     * Test validate returns true with valid configuration
     */
    public function testValidateReturnsTrueWithValidConfiguration(): void
    {
        $config = [
            'entity' => 'incidents',
            'charts' => [
                ['type' => 'line', 'title' => 'Incident Trends']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $result = $renderer->validate();

        $this->assertTrue($result);
    }

    /**
     * Test validate returns true with multiple charts
     */
    public function testValidateReturnsTrueWithMultipleCharts(): void
    {
        $config = [
            'entity' => 'incidents',
            'charts' => [
                ['type' => 'line', 'title' => 'Trends'],
                ['type' => 'bar', 'title' => 'By Category'],
                ['type' => 'pie', 'title' => 'Distribution']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $result = $renderer->validate();

        $this->assertTrue($result);
    }

    /**
     * Test validate returns false when chart missing type
     */
    public function testValidateReturnsFalseWhenChartMissingType(): void
    {
        $config = [
            'entity' => 'incidents',
            'charts' => [
                ['title' => 'Trends']  // Missing 'type'
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $result = $renderer->validate();

        $this->assertFalse($result);
    }

    /**
     * Test validate returns false when chart missing title
     */
    public function testValidateReturnsFalseWhenChartMissingTitle(): void
    {
        $config = [
            'entity' => 'incidents',
            'charts' => [
                ['type' => 'line']  // Missing 'title'
            ]
        ];

        $renderer = new AnalyticsRenderer($config);
        $result = $renderer->validate();

        $this->assertFalse($result);
    }

    /**
     * Test validate accepts line chart type
     */
    public function testValidateAcceptsLineChartType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'line', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    /**
     * Test validate accepts bar chart type
     */
    public function testValidateAcceptsBarChartType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'bar', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    /**
     * Test validate accepts pie chart type
     */
    public function testValidateAcceptsPieChartType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'pie', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    /**
     * Test validate accepts doughnut chart type
     */
    public function testValidateAcceptsDoughnutChartType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'doughnut', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    /**
     * Test validate accepts radar chart type
     */
    public function testValidateAcceptsRadarChartType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'radar', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    /**
     * Test validate accepts polarArea chart type
     */
    public function testValidateAcceptsPolarAreaChartType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'polarArea', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertTrue($renderer->validate());
    }

    /**
     * Test validate returns false for invalid chart type
     */
    public function testValidateReturnsFalseForInvalidChartType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'invalid_type', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    /**
     * Test validate validates all charts in array
     */
    public function testValidateValidatesAllChartsInArray(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [
                ['type' => 'line', 'title' => 'Valid'],
                ['type' => 'invalid', 'title' => 'Invalid']  // One invalid
            ]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    /**
     * Test validate with empty chart type
     */
    public function testValidateWithEmptyChartType(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => '', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    /**
     * Test validate with empty chart title
     */
    public function testValidateWithEmptyChartTitle(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'line', 'title' => '']]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertFalse($renderer->validate());
    }

    /**
     * Test handle returns read-only message
     */
    public function testHandleReturnsReadOnlyMessage(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'line', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $result = $renderer->handle(['some' => 'data']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Analytics module is read-only', $result['message']);
    }

    /**
     * Test handle with empty data
     */
    public function testHandleWithEmptyData(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'line', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $result = $renderer->handle([]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Analytics module is read-only', $result['message']);
    }

    /**
     * Test render returns error for invalid configuration
     */
    public function testRenderReturnsErrorForInvalidConfiguration(): void
    {
        $config = ['entity' => 'test'];  // No charts - invalid
        $renderer = new AnalyticsRenderer($config);

        $html = $renderer->render();

        $this->assertStringContainsString('Invalid analytics configuration', $html);
        $this->assertStringContainsString('alert-danger', $html);
    }

    /**
     * Test render with valid configuration includes title
     */
    public function testRenderWithValidConfigurationIncludesTitle(): void
    {
        $config = [
            'entity' => 'test',
            'displayName' => 'Test Analytics Dashboard',
            'charts' => [['type' => 'line', 'title' => 'Test Chart']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Test Analytics Dashboard', $html);
        $this->assertStringContainsString('<h2>', $html);
    }

    /**
     * Test render with description
     */
    public function testRenderWithDescription(): void
    {
        $config = [
            'entity' => 'test',
            'displayName' => 'Dashboard',
            'description' => 'This is a test analytics dashboard',
            'charts' => [['type' => 'line', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('This is a test analytics dashboard', $html);
        $this->assertStringContainsString('text-muted', $html);
    }

    /**
     * Test render without description
     */
    public function testRenderWithoutDescription(): void
    {
        $config = [
            'entity' => 'test',
            'displayName' => 'Dashboard',
            'charts' => [['type' => 'line', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringContainsString('analytics-container', $html);
    }

    /**
     * Test render includes Chart.js script tag
     */
    public function testRenderIncludesChartJsScriptTag(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [['type' => 'line', 'title' => 'Test']]
        ];

        $renderer = new AnalyticsRenderer($config);
        $html = $renderer->render();

        $this->assertStringContainsString('chart.js', $html);
        $this->assertStringContainsString('<script', $html);
    }

    /**
     * Test validate with all valid chart types
     */
    public function testValidateWithAllValidChartTypes(): void
    {
        $config = [
            'entity' => 'test',
            'charts' => [
                ['type' => 'line', 'title' => 'Line Chart'],
                ['type' => 'bar', 'title' => 'Bar Chart'],
                ['type' => 'pie', 'title' => 'Pie Chart'],
                ['type' => 'doughnut', 'title' => 'Doughnut Chart'],
                ['type' => 'radar', 'title' => 'Radar Chart'],
                ['type' => 'polarArea', 'title' => 'Polar Area Chart']
            ]
        ];

        $renderer = new AnalyticsRenderer($config);

        $this->assertTrue($renderer->validate());
        $this->assertCount(6, $renderer->getConfig()['charts']);
    }
}
