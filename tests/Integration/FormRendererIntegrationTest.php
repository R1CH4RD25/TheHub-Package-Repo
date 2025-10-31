<?php

namespace Tests\Integration;

use Hub\Modules\FormRenderer;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\TestDatabase;

/**
 * Integration tests for FormRenderer  
 * Tests form rendering, validation, and configuration
 * 
 * NOTE: Uses database transactions - all changes are rolled back after each test
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Modules\FormRenderer::class)]
class FormRendererIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Start transaction for test isolation - will be rolled back in tearDown
        TestDatabase::beginTransaction();
        
        $_POST = [];
        $_GET = [];
        $_SESSION['csrf_token'] = 'test-csrf-token';
        $_SESSION['user_id'] = 1;
    }
    
    protected function tearDown(): void
    {
        // Roll back all database changes made during test
        TestDatabase::rollBack();
        
        $_POST = [];
        $_GET = [];
        parent::tearDown();
    }
    
    public function testBasicFormRendering(): void
    {
        $config = [
            'dataSource' => 'users',
            'onSubmit' => ['insertInto' => 'users'],
            'fields' => [
                ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'required' => true]
            ],
            'submitText' => 'Create User'
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('Full Name', $html);
        $this->assertStringContainsString('Email Address', $html);
        $this->assertStringContainsString('Create User', $html);
    }
    
    public function testFormWithMultipleFieldTypes(): void
    {
        $config = [
            'dataSource' => 'users',
            'onSubmit' => ['insertInto' => 'users'],
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'age', 'label' => 'Age', 'type' => 'number'],
                ['name' => 'bio', 'label' => 'Bio', 'type' => 'textarea'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
                ['name' => 'agree', 'label' => 'Agree to terms', 'type' => 'checkbox'],
                ['name' => 'birthdate', 'label' => 'Birth Date', 'type' => 'date'],
                ['name' => 'user_id', 'type' => 'hidden', 'default' => '123']
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
    
    public function testFormValidation(): void
    {
        // Valid config
        $validConfig = [
            'dataSource' => 'users',
            'onSubmit' => ['insertInto' => 'users'],
            'fields' => [['name' => 'name', 'label' => 'Name', 'type' => 'text']]
        ];
        $form = new FormRenderer($validConfig);
        $this->assertTrue($form->validate());
        
        // Missing dataSource and onSubmit
        $invalidConfig = ['fields' => [['name' => 'name', 'label' => 'Name', 'type' => 'text']]];
        $form = new FormRenderer($invalidConfig);
        $this->assertFalse($form->validate());
        
        // Missing fields (has onSubmit but no fields)
        $invalidConfig = ['dataSource' => 'users', 'onSubmit' => ['insertInto' => 'users']];
        $form = new FormRenderer($invalidConfig);
        $this->assertFalse($form->validate());
        
        // Empty fields array (has onSubmit but empty fields)
        $invalidConfig = ['dataSource' => 'users', 'onSubmit' => ['insertInto' => 'users'], 'fields' => []];
        $form = new FormRenderer($invalidConfig);
        $this->assertFalse($form->validate());
    }
    
    public function testFormWithDefaultValues(): void
    {
        $config = [
            'dataSource' => 'users',
            'onSubmit' => ['insertInto' => 'users'],
            'fields' => [
                ['name' => 'status', 'label' => 'Status', 'type' => 'text', 'default' => 'active']
            ]
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('value="active"', $html);
    }
    
    public function testFormWithPlaceholders(): void
    {
        $config = [
            'dataSource' => 'users',
            'onSubmit' => ['insertInto' => 'users'],
            'fields' => [
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'placeholder' => 'Enter your email']
            ]
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('placeholder="Enter your email"', $html);
    }
    
    public function testFormWithHelpText(): void
    {
        $config = [
            'dataSource' => 'users',
            'onSubmit' => ['insertInto' => 'users'],
            'fields' => [
                ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'helpText' => 'Must be at least 8 characters']
            ]
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('Must be at least 8 characters', $html);
    }
    
    public function testGetConfig(): void
    {
        $config = [
            'dataSource' => 'users',
            'onSubmit' => ['insertInto' => 'users'],
            'fields' => [['name' => 'test', 'label' => 'Test', 'type' => 'text']]
        ];
        
        $form = new FormRenderer($config);
        $retrievedConfig = $form->getConfig();
        
        $this->assertIsArray($retrievedConfig);
        $this->assertArrayHasKey('fields', $retrievedConfig);
        $this->assertCount(1, $retrievedConfig['fields']);
    }
    
    public function testFormWithRequiredFields(): void
    {
        $config = [
            'dataSource' => 'users',
            'onSubmit' => ['insertInto' => 'users'],
            'fields' => [
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]
            ]
        ];
        
        $form = new FormRenderer($config);
        $html = $form->render();
        
        $this->assertStringContainsString('required', $html);
    }
}
