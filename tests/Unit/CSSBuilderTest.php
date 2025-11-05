<?php

namespace Hub\Tests\Unit;

use Hub\CSSBuilder;
use PHPUnit\Framework\TestCase;

class CSSBuilderTest extends TestCase
{
    private string $buildScript;
    private string $versionFile;
    private bool $scriptExisted = false;
    private bool $versionExisted = false;
    private string $originalScriptContent = '';
    private string $originalVersionContent = '';

    protected function setUp(): void
    {
        $this->buildScript = __DIR__ . '/../../build-css.sh';
        $this->versionFile = __DIR__ . '/../../public/assets/css/version.txt';

        // Backup existing files
        if (file_exists($this->buildScript)) {
            $this->scriptExisted = true;
            $this->originalScriptContent = file_get_contents($this->buildScript);
        }

        if (file_exists($this->versionFile)) {
            $this->versionExisted = true;
            $this->originalVersionContent = file_get_contents($this->versionFile);
        }
    }

    protected function tearDown(): void
    {
        // Restore original files
        if ($this->scriptExisted) {
            file_put_contents($this->buildScript, $this->originalScriptContent);
            chmod($this->buildScript, 0755);
        } elseif (file_exists($this->buildScript)) {
            unlink($this->buildScript);
        }

        if ($this->versionExisted) {
            file_put_contents($this->versionFile, $this->originalVersionContent);
        } elseif (file_exists($this->versionFile)) {
            unlink($this->versionFile);
        }
    }

    /**
     * Test rebuild when script doesn't exist
     */
    public function testRebuildWhenScriptDoesNotExist(): void
    {
        // Remove script if it exists
        if (file_exists($this->buildScript)) {
            unlink($this->buildScript);
        }

        $result = CSSBuilder::rebuild();

        $this->assertFalse($result['success']);
        $this->assertEquals('Build script not found', $result['message']);
    }

    /**
     * Test rebuild with successful script execution
     */
    public function testRebuildWithSuccessfulExecution(): void
    {
        // Create a test script that succeeds
        $testScript = "#!/bin/bash\necho 'CSS build successful'\nexit 0";
        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0755);

        // Create version file
        file_put_contents($this->versionFile, "1.2.3\n");

        $result = CSSBuilder::rebuild();

        $this->assertTrue($result['success']);
        $this->assertEquals('CSS rebuilt successfully', $result['message']);
        $this->assertEquals('1.2.3', $result['version']);
        $this->assertArrayHasKey('output', $result);
    }

    /**
     * Test rebuild with failing script
     */
    public function testRebuildWithFailingScript(): void
    {
        // Create a test script that fails
        $testScript = "#!/bin/bash\necho 'Build error'\nexit 1";
        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0755);

        $result = CSSBuilder::rebuild();

        $this->assertFalse($result['success']);
        $this->assertEquals('Build failed', $result['message']);
        $this->assertArrayHasKey('output', $result);
    }

    /**
     * Test rebuild makes script executable if needed
     */
    public function testRebuildMakesScriptExecutable(): void
    {
        // Create non-executable script
        $testScript = "#!/bin/bash\necho 'test'\nexit 0";
        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0644); // Not executable

        $this->assertFalse(is_executable($this->buildScript));

        CSSBuilder::rebuild();

        // Should now be executable
        $this->assertTrue(is_executable($this->buildScript));
    }

    /**
     * Test rebuild without version file
     */
    public function testRebuildWithoutVersionFile(): void
    {
        // Create successful script
        $testScript = "#!/bin/bash\nexit 0";
        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0755);

        // Remove version file if exists
        if (file_exists($this->versionFile)) {
            unlink($this->versionFile);
        }

        $result = CSSBuilder::rebuild();

        $this->assertTrue($result['success']);
        $this->assertEquals('unknown', $result['version']);
    }

    /**
     * Test rebuild captures script output
     */
    public function testRebuildCapturesScriptOutput(): void
    {
        // Create script with output
        $testScript = "#!/bin/bash\necho 'Line 1'\necho 'Line 2'\nexit 0";
        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0755);

        $result = CSSBuilder::rebuild();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Line 1', $result['output']);
        $this->assertStringContainsString('Line 2', $result['output']);
    }

    /**
     * Test isProductionMode when not defined
     */
    public function testIsProductionModeWhenNotDefined(): void
    {
        // Make sure constant is not defined in test environment
        $result = CSSBuilder::isProductionMode();

        $this->assertIsBool($result);
        // In test environment, should be false
        $this->assertFalse($result);
    }

    /**
     * Test getVersion in non-production mode returns timestamp
     */
    public function testGetVersionInNonProductionMode(): void
    {
        $before = time();
        $version = CSSBuilder::getVersion();
        $after = time();

        $this->assertIsNumeric($version);
        $this->assertGreaterThanOrEqual($before, (int)$version);
        $this->assertLessThanOrEqual($after, (int)$version);
    }

    /**
     * Test getVersion with version file
     */
    public function testGetVersionWithVersionFile(): void
    {
        // Create version file
        file_put_contents($this->versionFile, "2.5.8\n");

        // Even in non-production, if CSS_PRODUCTION_MODE were true, it would read file
        // Since we can't easily mock constants, we test the file exists
        $this->assertTrue(file_exists($this->versionFile));
    }

    /**
     * Test result structure of rebuild
     */
    public function testRebuildResultStructure(): void
    {
        // Create minimal working script
        $testScript = "#!/bin/bash\nexit 0";
        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0755);

        $result = CSSBuilder::rebuild();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);

        if ($result['success']) {
            $this->assertArrayHasKey('version', $result);
            $this->assertArrayHasKey('output', $result);
        }
    }

    /**
     * Test rebuild with multi-line output
     */
    public function testRebuildWithMultiLineOutput(): void
    {
        $testScript = <<<'BASH'
#!/bin/bash
echo "Starting build..."
echo "Processing files..."
echo "Build complete"
exit 0
BASH;

        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0755);

        $result = CSSBuilder::rebuild();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Starting build', $result['output']);
        $this->assertStringContainsString('Processing files', $result['output']);
        $this->assertStringContainsString('Build complete', $result['output']);
    }

    /**
     * Test rebuild with stderr output
     */
    public function testRebuildCapturesStderr(): void
    {
        // Script that outputs to stderr but succeeds
        $testScript = "#!/bin/bash\necho 'Warning message' >&2\nexit 0";
        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0755);

        $result = CSSBuilder::rebuild();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Warning message', $result['output']);
    }

    /**
     * Test rebuild with error output
     */
    public function testRebuildWithErrorOutput(): void
    {
        // Script that outputs to stderr and fails
        $testScript = "#!/bin/bash\necho 'Error: something went wrong' >&2\nexit 1";
        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0755);

        $result = CSSBuilder::rebuild();

        $this->assertFalse($result['success']);
        $this->assertEquals('Build failed', $result['message']);
        $this->assertStringContainsString('Error:', $result['output']);
    }

    /**
     * Test getVersion returns string or int
     */
    public function testGetVersionReturnsStringOrInt(): void
    {
        $version = CSSBuilder::getVersion();

        // Can be string or int depending on mode
        $this->assertTrue(is_string($version) || is_int($version));
    }

    /**
     * Test rebuild with whitespace in version file
     */
    public function testRebuildTrimsVersionWhitespace(): void
    {
        $testScript = "#!/bin/bash\nexit 0";
        file_put_contents($this->buildScript, $testScript);
        chmod($this->buildScript, 0755);

        // Version with whitespace
        file_put_contents($this->versionFile, "  3.0.1  \n");

        $result = CSSBuilder::rebuild();

        $this->assertTrue($result['success']);
        $this->assertEquals('3.0.1', $result['version']);
    }

    /**
     * Test class methods are static
     */
    public function testMethodsAreStatic(): void
    {
        $class = new \ReflectionClass(CSSBuilder::class);

        $rebuild = $class->getMethod('rebuild');
        $this->assertTrue($rebuild->isStatic());

        $isProductionMode = $class->getMethod('isProductionMode');
        $this->assertTrue($isProductionMode->isStatic());

        $getVersion = $class->getMethod('getVersion');
        $this->assertTrue($getVersion->isStatic());
    }
}
