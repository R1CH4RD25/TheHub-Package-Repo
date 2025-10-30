<?php

namespace Tests\Unit\Modules;

use Hub\Modules\ModuleFactory;
use Hub\Modules\FormRenderer;
use Hub\Modules\TableViewRenderer;
use Hub\Modules\AnalyticsRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ModuleFactory
 */
class ModuleFactoryTest extends TestCase
{
    public function testCreateFormRenderer(): void
    {
        $config = [
            'type' => 'Form',
            'fields' => [['name' => 'test', 'type' => 'text']],
            'onSubmit' => ['insertInto' => 'test']
        ];
        
        $module = ModuleFactory::create($config);
        
        $this->assertInstanceOf(FormRenderer::class, $module);
    }
    
    public function testCreateTableViewRenderer(): void
    {
        $config = [
            'type' => 'TableView',
            'dataSource' => ['table' => 'test'],
            'columns' => [['field' => 'id', 'label' => 'ID']]
        ];
        
        $module = ModuleFactory::create($config);
        
        $this->assertInstanceOf(TableViewRenderer::class, $module);
    }
    
    public function testCreateAnalyticsRenderer(): void
    {
        $config = [
            'type' => 'Analytics',
            'entity' => 'test',
            'charts' => [
                ['type' => 'line', 'title' => 'Test Chart', 'xAxis' => 'date', 'yAxis' => 'COUNT(*)']
            ]
        ];
        
        $module = ModuleFactory::create($config);
        
        $this->assertInstanceOf(AnalyticsRenderer::class, $module);
    }
    
    public function testCreateThrowsExceptionForUnsupportedType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported module type: InvalidType');
        
        ModuleFactory::create(['type' => 'InvalidType']);
    }
    
    public function testCreateThrowsExceptionWithoutType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Module type not specified');
        
        ModuleFactory::create([]);
    }
    
    public function testIsSupportedReturnsTrueForValidTypes(): void
    {
        $this->assertTrue(ModuleFactory::isSupported('Form'));
        $this->assertTrue(ModuleFactory::isSupported('TableView'));
        $this->assertTrue(ModuleFactory::isSupported('Analytics'));
        $this->assertTrue(ModuleFactory::isSupported('Dashboard'));
    }
    
    public function testIsSupportedReturnsFalseForInvalidTypes(): void
    {
        $this->assertFalse(ModuleFactory::isSupported('InvalidType'));
        $this->assertFalse(ModuleFactory::isSupported('Random'));
    }
    
    public function testGetSupportedTypesReturnsArray(): void
    {
        $types = ModuleFactory::getSupportedTypes();
        
        $this->assertIsArray($types);
        $this->assertContains('Form', $types);
        $this->assertContains('TableView', $types);
        $this->assertContains('Analytics', $types);
    }
}
