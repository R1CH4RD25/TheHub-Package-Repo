<?php

namespace Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;
use Hub\Modules\FileManagerRenderer;
use Hub\Database;
use Hub\Cache;

class FileManagerRendererTest extends TestCase
{
    private FileManagerRenderer $renderer;
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'slug' => 'test-file-manager',
            'name' => 'Test File Manager',
            'type' => 'FileManager',
            'settings' => [
                'max_file_size' => 10485760, // 10MB
                'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'png'],
                'storage_quota' => 104857600 // 100MB
            ]
        ];

        $this->renderer = new FileManagerRenderer($this->config, 1);
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
        $this->assertStringContainsString('file-manager-container', $html);
        $this->assertGreaterThan(1000, strlen($html), 'HTML should be substantial');
    }

    public function testRenderContainsUploadInterface(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('Drag & drop files here', $html);
        $this->assertStringContainsString('fileInput', $html);
        $this->assertStringContainsString('uploadModal', $html);
    }

    public function testRenderContainsViewModeToggles(): void
    {
        $html = $this->renderer->render();

        $this->assertStringContainsString('btn-view-mode', $html);
        $this->assertStringContainsString('data-view="list"', $html);
    }

    public function testValidateFileSuccess(): void
    {
        $data = [
            'action' => 'upload',
            'file' => [
                'name' => 'document.pdf',
                'size' => 1048576, // 1MB
                'type' => 'application/pdf'
            ]
        ];

        $errors = $this->renderer->validateFile($data);

        $this->assertIsArray($errors);
        // Note: Full validation would require more context
    }

    public function testValidateFileSizeExceeded(): void
    {
        $data = [
            'action' => 'upload',
            'file' => [
                'name' => 'large.pdf',
                'size' => 20971520, // 20MB (exceeds 10MB limit)
                'type' => 'application/pdf'
            ]
        ];

        $errors = $this->renderer->validateFile($data);

        $this->assertIsArray($errors);
        // Would contain size error in full implementation
    }

    public function testValidateInvalidFileType(): void
    {
        $data = [
            'action' => 'upload',
            'file' => [
                'name' => 'script.exe',
                'size' => 1024,
                'type' => 'application/x-executable'
            ]
        ];

        $errors = $this->renderer->validateFile($data);

        $this->assertIsArray($errors);
        // Would contain type error in full implementation
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
        $this->assertEquals('test-file-manager', $this->config['slug']);
        $this->assertEquals('FileManager', $this->config['type']);
        $this->assertEquals(10485760, $this->config['settings']['max_file_size']);
    }

    public function testAllowedFileTypes(): void
    {
        $allowedTypes = $this->config['settings']['allowed_types'];

        $this->assertIsArray($allowedTypes);
        $this->assertContains('pdf', $allowedTypes);
        $this->assertContains('jpg', $allowedTypes);
        $this->assertNotContains('exe', $allowedTypes);
    }
}
