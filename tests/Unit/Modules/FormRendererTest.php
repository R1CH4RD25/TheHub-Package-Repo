<?php

namespace Tests\Unit\Modules;

use Hub\Modules\FormRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FormRenderer
 */
class FormRendererTest extends TestCase
{
    public function testValidateWithValidConfig(): void
    {
        $config = [
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true]
            ],
            'onSubmit' => [
                'insertInto' => 'test_table'
            ]
        ];
        
        $renderer = new FormRenderer($config);
        
        $this->assertTrue($renderer->validate());
    }
    
    public function testValidateFailsWithoutFields(): void
    {
        $config = [
            'onSubmit' => [
                'insertInto' => 'test_table'
            ]
        ];
        
        $renderer = new FormRenderer($config);
        
        $this->assertFalse($renderer->validate());
    }
    
    public function testValidateFailsWithInvalidFieldType(): void
    {
        $config = [
            'fields' => [
                ['name' => 'test', 'type' => 'invalid_type']
            ],
            'onSubmit' => [
                'insertInto' => 'test_table'
            ]
        ];
        
        $renderer = new FormRenderer($config);
        
        $this->assertFalse($renderer->validate());
    }
    
    public function testRenderProducesHtml(): void
    {
        $config = [
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true]
            ],
            'onSubmit' => [
                'insertInto' => 'test_table'
            ]
        ];
        
        $renderer = new FormRenderer($config);
        $html = $renderer->render();
        
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('required', $html);
    }
    
    public function testGetConfigReturnsConfiguration(): void
    {
        $config = [
            'fields' => [
                ['name' => 'test', 'type' => 'text']
            ],
            'onSubmit' => [
                'insertInto' => 'test_table'
            ]
        ];
        
        $renderer = new FormRenderer($config);
        
        $this->assertEquals($config, $renderer->getConfig());
    }
}
