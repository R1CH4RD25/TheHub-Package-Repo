<?php

namespace Tests\Integration;

use Hub\Modules\AnalyticsRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for AnalyticsRenderer
 * Tests analytics chart configuration, validation, and rendering
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Modules\AnalyticsRenderer::class)]
class AnalyticsRendererIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_GET = [];
    }
    
    protected function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }
    
    /**
     * Test analytics validation - valid config
     */
    public function testAnalyticsValidationValid(): void
    {
        $validConfig = [
            'chartType' => 'bar',
            'dataSource' => 'test_data',
            'labelField' => 'category',
            'valueField' => 'amount'
        ];
        $renderer = new AnalyticsRenderer($validConfig);
        $this->assertTrue($renderer->validate());
    }
    
    /**
     * Test analytics validation - missing chartType
     */
    public function testAnalyticsValidationMissingChartType(): void
    {
        $invalidConfig = [
            'dataSource' => 'test_data',
            'labelField' => 'category',
            'valueField' => 'amount'
        ];
        $renderer = new AnalyticsRenderer($invalidConfig);
        $this->assertFalse($renderer->validate());
    }
    
    /**
     * Test analytics validation - missing dataSource
     */
    public function testAnalyticsValidationMissingDataSource(): void
    {
        $invalidConfig = [
            'chartType' => 'bar',
            'labelField' => 'category',
            'valueField' => 'amount'
        ];
        $renderer = new AnalyticsRenderer($invalidConfig);
        $this->assertFalse($renderer->validate());
    }
    
    /**
     * Test analytics validation - missing labelField
     */
    public function testAnalyticsValidationMissingLabelField(): void
    {
        $invalidConfig = [
            'chartType' => 'bar',
            'dataSource' => 'test_data',
            'valueField' => 'amount'
        ];
        $renderer = new AnalyticsRenderer($invalidConfig);
        $this->assertFalse($renderer->validate());
    }
    
    /**
     * Test analytics validation - missing valueField
     */
    public function testAnalyticsValidationMissingValueField(): void
    {
        $invalidConfig = [
            'chartType' => 'bar',
            'dataSource' => 'test_data',
            'labelField' => 'category'
        ];
        $renderer = new AnalyticsRenderer($invalidConfig);
        $this->assertFalse($renderer->validate());
    }
    
    /**
     * Test analytics configuration retrieval
     */
    public function testGetConfig(): void
    {
        $config = [
            'chartType' => 'line',
            'dataSource' => 'test_data',
            'labelField' => 'date',
            'valueField' => 'value',
            'title' => 'Test Chart'
        ];
        
        $renderer = new AnalyticsRenderer($config);
        $retrievedConfig = $renderer->getConfig();
        
        $this->assertIsArray($retrievedConfig);
        $this->assertEquals('line', $retrievedConfig['chartType']);
        $this->assertEquals('test_data', $retrievedConfig['dataSource']);
        $this->assertEquals('Test Chart', $retrievedConfig['title']);
    }
    
    /**
     * Test handle method returns empty (read-only module)
     */
    public function testHandleMethodReadOnly(): void
    {
        $config = [
            'chartType' => 'bar',
            'dataSource' => 'test_data',
            'labelField' => 'category',
            'valueField' => 'amount'
        ];
        
        $renderer = new AnalyticsRenderer($config);
        $result = $renderer->handle();
        
        $this->assertEmpty($result);
    }
    
    /**
     * Test different chart types are valid
     */
    public function testDifferentChartTypes(): void
    {
        $chartTypes = ['bar', 'line', 'pie', 'doughnut', 'radar'];
        
        foreach ($chartTypes as $type) {
            $config = [
                'chartType' => $type,
                'dataSource' => 'test_data',
                'labelField' => 'label',
                'valueField' => 'value'
            ];
            
            $renderer = new AnalyticsRenderer($config);
            $this->assertTrue($renderer->validate(), "Chart type $type should be valid");
        }
    }
    
    /**
     * Test configuration with optional fields
     */
    public function testConfigurationWithOptionalFields(): void
    {
        $config = [
            'chartType' => 'bar',
            'dataSource' => 'test_data',
            'labelField' => 'category',
            'valueField' => 'amount',
            'title' => 'Sales Report',
            'aggregate' => 'SUM',
            'groupBy' => 'category',
            'height' => 400,
            'width' => 800
        ];
        
        $renderer = new AnalyticsRenderer($config);
        $this->assertTrue($renderer->validate());
        
        $retrievedConfig = $renderer->getConfig();
        $this->assertEquals('Sales Report', $retrievedConfig['title']);
        $this->assertEquals('SUM', $retrievedConfig['aggregate']);
        $this->assertEquals(400, $retrievedConfig['height']);
    }
}
