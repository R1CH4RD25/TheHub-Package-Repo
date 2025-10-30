#!/usr/bin/env php
<?php
/**
 * Package Builder - Build .hubpkg files from package source
 * 
 * Usage:
 *   php cli/pkg-build.php packages/local/my-package/
 *   php cli/pkg-build.php packages/local/my-package/ --output=dist/
 *   php cli/pkg-build.php . (from within package directory)
 * 
 * Options:
 *   --output       Output directory (default: package directory)
 *   --validate     Run pkg-lint before building (default: true)
 *   --no-validate  Skip validation
 * 
 * @author The Hub Team
 * @version 2.0.0
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

class PackageBuilder {
    private $packageDir;
    private $outputDir;
    private $validate;
    private $manifest;
    
    public function __construct($packageDir, $options = []) {
        $this->packageDir = rtrim($packageDir, '/');
        $this->outputDir = $options['output'] ?? $this->packageDir;
        $this->validate = !isset($options['no-validate']);
        
        if (!is_dir($this->packageDir)) {
            $this->error("Package directory not found: {$this->packageDir}");
        }
        
        if (!file_exists($this->packageDir . '/manifest.json')) {
            $this->error("manifest.json not found in {$this->packageDir}");
        }
    }
    
    public function build() {
        echo "\033[1m\033[34mPackage Builder v2.0\033[0m\n";
        echo str_repeat('=', 80) . "\n\n";
        
        // Load manifest
        $this->loadManifest();
        
        $packageName = $this->manifest['package']['name'] ?? 'unknown';
        $version = $this->manifest['package']['version'] ?? '0.0.0';
        
        echo "Building package: \033[1m{$packageName} v{$version}\033[0m\n";
        echo "Source: {$this->packageDir}\n";
        echo "Output: {$this->outputDir}\n\n";
        
        // Validate if enabled
        if ($this->validate) {
            echo "Running validation...\n";
            $this->runValidation();
        } else {
            echo "\033[33mSkipping validation (--no-validate)\033[0m\n";
        }
        
        // Build .hubpkg
        $hubpkgPath = $this->buildHubpkg($packageName, $version);
        
        echo "\n\033[32m✓ Package built successfully!\033[0m\n";
        echo "\nPackage file: \033[1m{$hubpkgPath}\033[0m\n";
        echo "Size: " . $this->formatBytes(filesize($hubpkgPath)) . "\n\n";
        
        echo "Next steps:\n";
        echo "  1. Test installation on a staging Hub instance\n";
        echo "  2. Verify all features work correctly\n";
        echo "  3. Submit to package repository\n\n";
    }
    
    private function loadManifest() {
        $json = file_get_contents($this->packageDir . '/manifest.json');
        $this->manifest = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid manifest.json: ' . json_last_error_msg());
        }
        
        echo "\033[32m✓\033[0m Manifest loaded\n";
    }
    
    private function runValidation() {
        $linterPath = __DIR__ . '/pkg-lint.php';
        
        if (!file_exists($linterPath)) {
            echo "\033[33mWarning: pkg-lint.php not found, skipping validation\033[0m\n";
            return;
        }
        
        $cmd = "php " . escapeshellarg($linterPath) . " " . escapeshellarg($this->packageDir . '/manifest.json');
        
        // Capture output
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        // Print validation output
        echo implode("\n", $output) . "\n\n";
        
        if ($returnCode !== 0) {
            echo "\033[31mValidation failed! Fix errors before building.\033[0m\n";
            exit(1);
        }
        
        echo "\033[32m✓\033[0m Validation passed\n";
    }
    
    private function buildHubpkg($packageName, $version) {
        // The .hubpkg is just the manifest.json renamed with version in filename
        $filename = "{$packageName}_{$version}.hubpkg";
        $outputPath = $this->outputDir . '/' . $filename;
        
        // Ensure output directory exists
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0775, true);
        }
        
        // Copy manifest.json to .hubpkg
        if (!copy($this->packageDir . '/manifest.json', $outputPath)) {
            $this->error("Failed to create .hubpkg file");
        }
        
        echo "\033[32m✓\033[0m Created: {$filename}\n";
        
        // Optionally create a full package archive with all files
        // This would include README, CHANGELOG, screenshots, etc.
        // For now, we keep it simple with just the manifest
        
        return $outputPath;
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    private function error($message) {
        echo "\033[31mError: $message\033[0m\n";
        exit(1);
    }
}

// Parse arguments
$options = getopt('', ['output:', 'no-validate']);
$packageDir = $argv[1] ?? '.';

if ($packageDir === '--help' || $packageDir === '-h') {
    echo "Package Builder - Build .hubpkg files from package source\n\n";
    echo "Usage:\n";
    echo "  php cli/pkg-build.php <package-dir> [options]\n";
    echo "  php cli/pkg-build.php . (from within package directory)\n\n";
    echo "Options:\n";
    echo "  --output=<dir>    Output directory (default: package directory)\n";
    echo "  --no-validate     Skip validation before building\n\n";
    echo "Example:\n";
    echo "  php cli/pkg-build.php packages/local/bullying-report/\n";
    echo "  php cli/pkg-build.php packages/local/my-package/ --output=dist/\n\n";
    exit(0);
}

// Run builder
$builder = new PackageBuilder($packageDir, $options);
$builder->build();
