<?php

namespace Tests\Integration;

use Hub\Modules\AnalyticsRenderer;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\TestDatabase;

/**
 * Integration tests for AnalyticsRenderer
 * Tests validation, chart types, and configuration
 * 
 * NOTE: Uses database transactions - all changes are rolled back after each test
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Modules\AnalyticsRenderer::class)]
class AnalyticsRendererIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Start transaction for test isolation - will be rolled back in tearDown
        TestDatabase::beginTransaction();
        
        $_GET = [];
    }
    
    protected function tearDown(): void
    {
        // Roll back all database changes made during test
        TestDatabase::rollBack();
        
        $_GET = [];
        parent::tearDown();
    }
    
    public function testAnalyticsValidationValid(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'bar', 'title' => 'User Count']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertTrue($analytics->validate());
    }
    
    public function testAnalyticsValidationMissingCharts(): void
    {
        $config = ['entity' => 'users'];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertFalse($analytics->validate());
    }
    
    public function testAnalyticsValidationMissingEntity(): void
    {
        $config = [
            'charts' => [
                ['type' => 'bar', 'title' => 'Test Chart']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertFalse($analytics->validate());
    }
    
    public function testAnalyticsValidationEmptyCharts(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => []
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertFalse($analytics->validate());
    }
    
    public function testAnalyticsValidationMissingChartTitle(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'bar']  // Missing title
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertFalse($analytics->validate());
    }
    
    public function testGetConfig(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'line', 'title' => 'User Growth']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $retrievedConfig = $analytics->getConfig();
        
        $this->assertIsArray($retrievedConfig);
        $this->assertEquals('users', $retrievedConfig['entity']);
        $this->assertCount(1, $retrievedConfig['charts']);
    }
    
    public function testRenderMethodReturnsHtml(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'bar', 'title' => 'User Statistics']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $html = $analytics->render();
        
        $this->assertIsString($html);
        $this->assertStringContainsString('chart', strtolower($html));
    }
    
    public function testDifferentChartTypes(): void
    {
        $chartTypes = ['bar', 'line', 'pie', 'doughnut'];
        
        foreach ($chartTypes as $type) {
            $config = [
                'entity' => 'users',
                'charts' => [
                    ['type' => $type, 'title' => ucfirst($type) . ' Chart']
                ]
            ];
            
            $analytics = new AnalyticsRenderer($config);
            $this->assertTrue($analytics->validate());
            $html = $analytics->render();
            
            $this->assertIsString($html);
            $this->assertNotEmpty($html);
        }
    }
    
    public function testConfigurationWithMultipleCharts(): void
    {
        $config = [
            'entity' => 'users',
            'charts' => [
                ['type' => 'bar', 'title' => 'User Count'],
                ['type' => 'line', 'title' => 'Growth Trend'],
                ['type' => 'pie', 'title' => 'Distribution']
            ]
        ];
        
        $analytics = new AnalyticsRenderer($config);
        $this->assertTrue($analytics->validate());
        
        $retrievedConfig = $analytics->getConfig();
        $this->assertCount(3, $retrievedConfig['charts']);
    }
}
