<?php

namespace Tests\Integration;

use Hub\Modules\FormRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for FormRenderer  
 * Tests form rendering, validation, and configuration
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Modules\FormRenderer::class)]
class FormRendererIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_POST = [];
        $_GET = [];
        $_SESSION['csrf_token'] = 'test-csrf-token';
        $_SESSION['user_id'] = 1;
    }
    
    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        parent::tearDown();
    }
    
    /**
     * Test basic form rendering
     */
    public function testBasicFormRendering(): void
    {
        $config = [
            'fields' => [
                [
                    'name' => 'name',
                    'label' => 'Full Name',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'email',
                    'label' => 'Email Address',
                    'type' => 'email',
                    'required' => true
                ]
            ],
            'submit' => 'Create User'
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('Full Name', $html);
        $this->assertStringContainsString('Email Address', $html);
        $this->assertStringContainsString('Create User', $html);
    }
    
    /**
     * Test form with all field types
     */
    public function testFormWithMultipleFieldTypes(): void
    {
        $config = [
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'age', 'label' => 'Age', 'type' => 'number'],
                ['name' => 'bio', 'label' => 'Bio', 'type' => 'textarea'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
                ['name' => 'agree', 'label' => 'Agree to terms', 'type' => 'checkbox'],
                ['name' => 'birthdate', 'label' => 'Birth Date', 'type' => 'date'],
                ['name' => 'user_id', 'type' => 'hidden', 'value' => '123']
            ]
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('type="hidden"', $html);
    }
    
    /**
     * Test form validation
     */
    public function testFormValidation(): void
    {
        // Valid config
        $validConfig = [
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text']
            ]
        ];
        $form = new FormRenderer($validConfig);
        $this->assertTrue($form->validate());
        
        // Missing fields
        $invalidConfig = [];
        $form = new FormRenderer($invalidConfig);
        $this->assertFalse($form->validate());
        
        // Empty fields array
        $invalidConfig = ['fields' => []];
        $form = new FormRenderer($invalidConfig);
        $this->assertFalse($form->validate());
        
        // Fields not array
        $invalidConfig = ['fields' => 'not an array'];
        $form = new FormRenderer($invalidConfig);
        $this->assertFalse($form->validate());
    }
    
    /**
     * Test form with default values
     */
    public function testFormWithDefaultValues(): void
    {
        $config = [
            'fields' => [
                [
                    'name' => 'status',
                    'label' => 'Status',
                    'type' => 'text',
                    'value' => 'active'
                ]
            ]
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('value="active"', $html);
    }
    
    /**
     * Test form with placeholders
     */
    public function testFormWithPlaceholders(): void
    {
        $config = [
            'fields' => [
                [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'placeholder' => 'Enter your email'
                ]
            ]
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('placeholder="Enter your email"', $html);
    }
    
    /**
     * Test form with help text
     */
    public function testFormWithHelpText(): void
    {
        $config = [
            'fields' => [
                [
                    'name' => 'password',
                    'label' => 'Password',
                    'type' => 'password',
                    'help' => 'Must be at least 8 characters'
                ]
            ]
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('Must be at least 8 characters', $html);
    }
    
    /**
     * Test form configuration retrieval
     */
    public function testGetConfig(): void
    {
        $config = [
            'fields' => [
                ['name' => 'test', 'label' => 'Test', 'type' => 'text']
            ]
        ];
        
        $form = new FormRenderer($config);
        $retrievedConfig = $form->getConfig();
        
        $this->assertIsArray($retrievedConfig);
        $this->assertArrayHasKey('fields', $retrievedConfig);
        $this->assertCount(1, $retrievedConfig['fields']);
    }
    
    /**
     * Test form with required fields
     */
    public function testFormWithRequiredFields(): void
    {
        $config = [
            'fields' => [
                [
                    'name' => 'email',
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => true
                ]
            ]
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('required', $html);
    }
}
