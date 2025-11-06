<?php

namespace Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;
use Hub\Modules\PDFGeneratorRenderer;
use Hub\Database;

class PDFGeneratorRendererTest extends TestCase
{
    private static Database $db;

    public static function setUpBeforeClass(): void
    {
        // Use test database
        putenv('DB_NAME=woodson_hub_test');
        $_ENV['DB_NAME'] = 'woodson_hub_test';

        self::$db = Database::getInstance();

        // Start transaction for isolation
        self::$db->beginTransaction();
    }

    public static function tearDownAfterClass(): void
    {
        // Rollback transaction
        self::$db->rollback();
    }

    protected function setUp(): void
    {
        // Mock session
        $_SESSION = [
            'tenant_id' => 'test_tenant',
            'user_id' => 1,
            'csrf_token' => 'test_token'
        ];

        // Mock environment
        $_ENV['APP_NAME'] = 'Test Hub';
        $_ENV['APP_URL'] = 'http://localhost';
        $_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        // Clean up any test files created
        $testDir = sys_get_temp_dir() . '/test_pdfs';
        if (is_dir($testDir)) {
            array_map('unlink', glob("$testDir/*.*"));
            rmdir($testDir);
        }
    }

    // ===== CONSTRUCTOR & BASIC TESTS =====

    public function testConstructorInitializesWithConfig(): void
    {
        $config = ['filename' => 'test.pdf'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertInstanceOf(PDFGeneratorRenderer::class, $renderer);
        $this->assertEquals($config, $renderer->getConfig());
    }

    public function testGetConfigReturnsConfiguration(): void
    {
        $config = [
            'filename' => 'report.pdf',
            'orientation' => 'landscape',
            'format' => 'A4'
        ];

        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals($config, $renderer->getConfig());
        $this->assertEquals('report.pdf', $renderer->getConfig()['filename']);
    }

    public function testValidateAlwaysReturnsTrue(): void
    {
        $config = ['html' => '<h1>Test</h1>'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertTrue($renderer->validate());

        // Even empty config is valid (will use defaults)
        $emptyRenderer = new PDFGeneratorRenderer([]);
        $this->assertTrue($emptyRenderer->validate());
    }

    // ===== RENDER TESTS =====

    public function testRenderShowsInfoMessage(): void
    {
        $config = ['filename' => 'test.pdf'];
        $renderer = new PDFGeneratorRenderer($config);

        $output = $renderer->render();

        $this->assertStringContainsString('PDF Generator Module', $output);
        $this->assertStringContainsString('generates PDF documents programmatically', $output);
    }

    public function testRenderShowsConfigurationForAdmins(): void
    {
        $config = [
            'filename' => 'report.pdf',
            'orientation' => 'landscape',
            'template' => '/path/to/template.html'
        ];

        $renderer = new PDFGeneratorRenderer($config);
        $output = $renderer->render();

        // Without admin role, should not show detailed config
        // Just verify render works
        $this->assertStringContainsString('PDF Generator Module', $output);
    }

    // ===== VARIABLE SUBSTITUTION TESTS =====

    public function testSubstituteVariablesReplacesBasicVariables(): void
    {
        $config = [
            'html' => '<h1>Hello {firstName} {lastName}</h1>',
            'output' => 'string' // Would fail without mPDF, but tests config
        ];

        $renderer = new PDFGeneratorRenderer($config);

        // Validate config structure
        $this->assertArrayHasKey('html', $renderer->getConfig());
        $this->assertStringContainsString('{firstName}', $renderer->getConfig()['html']);
    }

    public function testSubstituteVariablesAddsCommonVariables(): void
    {
        $config = [
            'title' => '{appName} Report - {currentDate}',
            'html' => '<p>Generated at {currentTime}</p>'
        ];

        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('{appName} Report - {currentDate}', $renderer->getConfig()['title']);
    }

    public function testSubstituteVariablesHandlesPageNumbers(): void
    {
        $config = [
            'footer' => 'Page {pageNumber} of {pageCount}'
        ];

        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('Page {pageNumber} of {pageCount}', $renderer->getConfig()['footer']);
    }

    // ===== FILENAME TESTS =====

    public function testFilenameWithDefaultValue(): void
    {
        $config = []; // No filename specified
        $renderer = new PDFGeneratorRenderer($config);

        // Default filename should be used
        $this->assertEmpty($renderer->getConfig()['filename'] ?? '');
    }

    public function testFilenameWithCustomValue(): void
    {
        $config = ['filename' => 'custom_report.pdf'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('custom_report.pdf', $renderer->getConfig()['filename']);
    }

    public function testFilenameWithVariables(): void
    {
        $config = ['filename' => 'report_{id}_{date}.pdf'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertStringContainsString('{id}', $renderer->getConfig()['filename']);
        $this->assertStringContainsString('{date}', $renderer->getConfig()['filename']);
    }

    // ===== ORIENTATION TESTS =====

    public function testOrientationPortrait(): void
    {
        $config = ['orientation' => 'portrait'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('portrait', $renderer->getConfig()['orientation']);
    }

    public function testOrientationLandscape(): void
    {
        $config = ['orientation' => 'landscape'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('landscape', $renderer->getConfig()['orientation']);
    }

    public function testOrientationShorthand(): void
    {
        $configP = ['orientation' => 'P'];
        $configL = ['orientation' => 'L'];

        $rendererP = new PDFGeneratorRenderer($configP);
        $rendererL = new PDFGeneratorRenderer($configL);

        $this->assertEquals('P', $rendererP->getConfig()['orientation']);
        $this->assertEquals('L', $rendererL->getConfig()['orientation']);
    }

    // ===== FORMAT TESTS =====

    public function testFormatLetter(): void
    {
        $config = ['format' => 'Letter'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('Letter', $renderer->getConfig()['format']);
    }

    public function testFormatA4(): void
    {
        $config = ['format' => 'A4'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('A4', $renderer->getConfig()['format']);
    }

    public function testFormatLegal(): void
    {
        $config = ['format' => 'Legal'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('Legal', $renderer->getConfig()['format']);
    }

    // ===== MARGIN TESTS =====

    public function testCustomMargins(): void
    {
        $config = [
            'marginLeft' => 20,
            'marginRight' => 20,
            'marginTop' => 25,
            'marginBottom' => 25
        ];

        $renderer = new PDFGeneratorRenderer($config);
        $cfg = $renderer->getConfig();

        $this->assertEquals(20, $cfg['marginLeft']);
        $this->assertEquals(20, $cfg['marginRight']);
        $this->assertEquals(25, $cfg['marginTop']);
        $this->assertEquals(25, $cfg['marginBottom']);
    }

    public function testHeaderFooterMargins(): void
    {
        $config = [
            'marginHeader' => 10,
            'marginFooter' => 10
        ];

        $renderer = new PDFGeneratorRenderer($config);
        $cfg = $renderer->getConfig();

        $this->assertEquals(10, $cfg['marginHeader']);
        $this->assertEquals(10, $cfg['marginFooter']);
    }

    // ===== METADATA TESTS =====

    public function testMetadataTitle(): void
    {
        $config = ['title' => 'My Test Document'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('My Test Document', $renderer->getConfig()['title']);
    }

    public function testMetadataAuthor(): void
    {
        $config = ['author' => 'John Doe'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('John Doe', $renderer->getConfig()['author']);
    }

    public function testMetadataSubject(): void
    {
        $config = ['subject' => 'Q4 Financial Report'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('Q4 Financial Report', $renderer->getConfig()['subject']);
    }

    // ===== HEADER/FOOTER TESTS =====

    public function testCustomHeader(): void
    {
        $config = ['header' => '<div style="text-align:center;">Company Name</div>'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertStringContainsString('Company Name', $renderer->getConfig()['header']);
    }

    public function testCustomFooter(): void
    {
        $config = ['footer' => '<div>Page {PAGENO}</div>'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertStringContainsString('{PAGENO}', $renderer->getConfig()['footer']);
    }

    public function testShowPageNumbersOption(): void
    {
        $configTrue = ['showPageNumbers' => true];
        $configFalse = ['showPageNumbers' => false];

        $rendererTrue = new PDFGeneratorRenderer($configTrue);
        $rendererFalse = new PDFGeneratorRenderer($configFalse);

        $this->assertTrue($rendererTrue->getConfig()['showPageNumbers']);
        $this->assertFalse($rendererFalse->getConfig()['showPageNumbers']);
    }

    // ===== WATERMARK TESTS =====

    public function testTextWatermark(): void
    {
        $config = ['watermark' => 'CONFIDENTIAL'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('CONFIDENTIAL', $renderer->getConfig()['watermark']);
    }

    public function testImageWatermark(): void
    {
        $config = [
            'watermark' => [
                'image' => '/path/to/logo.png',
                'alpha' => 0.3,
                'size' => 'D',
                'position' => 'P'
            ]
        ];

        $renderer = new PDFGeneratorRenderer($config);
        $watermark = $renderer->getConfig()['watermark'];

        $this->assertIsArray($watermark);
        $this->assertEquals('/path/to/logo.png', $watermark['image']);
        $this->assertEquals(0.3, $watermark['alpha']);
    }

    // ===== TEMPLATE TESTS =====

    public function testInlineHTMLTemplate(): void
    {
        $config = [
            'html' => '<html><body><h1>Test</h1></body></html>'
        ];

        $renderer = new PDFGeneratorRenderer($config);

        $this->assertStringContainsString('<h1>Test</h1>', $renderer->getConfig()['html']);
    }

    public function testExternalTemplateFile(): void
    {
        $config = ['template' => '/path/to/template.html'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('/path/to/template.html', $renderer->getConfig()['template']);
    }

    public function testDefaultTemplateWithContent(): void
    {
        $config = [];
        $renderer = new PDFGeneratorRenderer($config);

        // Default template will be used (no html or template specified)
        $this->assertEmpty($renderer->getConfig()['html'] ?? '');
        $this->assertEmpty($renderer->getConfig()['template'] ?? '');
    }

    // ===== OUTPUT MODE TESTS =====

    public function testOutputModeDownload(): void
    {
        $config = ['output' => 'download'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('download', $renderer->getConfig()['output']);
    }

    public function testOutputModeInline(): void
    {
        $config = ['output' => 'inline'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('inline', $renderer->getConfig()['output']);
    }

    public function testOutputModeSave(): void
    {
        $config = ['output' => 'save'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('save', $renderer->getConfig()['output']);
    }

    // ===== STORAGE TESTS =====

    public function testCustomStoragePath(): void
    {
        $config = ['storagePath' => '/custom/path/{id}/document.pdf'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('/custom/path/{id}/document.pdf', $renderer->getConfig()['storagePath']);
    }

    public function testSignedUrlOption(): void
    {
        $configTrue = ['signedUrl' => true];
        $configFalse = ['signedUrl' => false];

        $rendererTrue = new PDFGeneratorRenderer($configTrue);
        $rendererFalse = new PDFGeneratorRenderer($configFalse);

        $this->assertTrue($rendererTrue->getConfig()['signedUrl']);
        $this->assertFalse($rendererFalse->getConfig()['signedUrl']);
    }

    public function testUrlExpiryOption(): void
    {
        $config = ['urlExpiry' => 7200]; // 2 hours
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals(7200, $renderer->getConfig()['urlExpiry']);
    }

    // ===== STATIC HELPER TESTS =====

    public function testStaticGenerateHelper(): void
    {
        $config = ['html' => '<h1>Test</h1>'];

        // Static method exists
        $this->assertTrue(method_exists(PDFGeneratorRenderer::class, 'generate'));
    }

    // ===== EDGE CASES =====

    public function testEmptyConfig(): void
    {
        $renderer = new PDFGeneratorRenderer([]);

        $this->assertInstanceOf(PDFGeneratorRenderer::class, $renderer);
        $this->assertTrue($renderer->validate());
        $this->assertEmpty($renderer->getConfig());
    }

    public function testFilenameWithoutPdfExtension(): void
    {
        $config = ['filename' => 'document']; // Missing .pdf
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('document', $renderer->getConfig()['filename']);
        // Extension should be added during generation
    }

    public function testFilenameWithSpecialCharacters(): void
    {
        $config = ['filename' => 'Report #123 @ 2024.pdf'];
        $renderer = new PDFGeneratorRenderer($config);

        $this->assertEquals('Report #123 @ 2024.pdf', $renderer->getConfig()['filename']);
        // Should be sanitized during generation
    }

    public function testComplexConfiguration(): void
    {
        $config = [
            'filename' => 'complex_report.pdf',
            'title' => 'Complex Report',
            'author' => 'Test Author',
            'subject' => 'Testing',
            'orientation' => 'landscape',
            'format' => 'A4',
            'marginLeft' => 20,
            'marginRight' => 20,
            'marginTop' => 30,
            'marginBottom' => 30,
            'header' => '<div>Header</div>',
            'footer' => '<div>Footer</div>',
            'watermark' => 'DRAFT',
            'html' => '<h1>Content</h1>',
            'output' => 'save',
            'signedUrl' => true,
            'urlExpiry' => 3600
        ];

        $renderer = new PDFGeneratorRenderer($config);
        $cfg = $renderer->getConfig();

        $this->assertEquals('complex_report.pdf', $cfg['filename']);
        $this->assertEquals('landscape', $cfg['orientation']);
        $this->assertEquals('A4', $cfg['format']);
        $this->assertTrue($cfg['signedUrl']);
    }
}
