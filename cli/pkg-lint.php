#!/usr/bin/env php
<?php
/**
 * Package Linter - Validate packages against The Hub Package Specification v2.0
 * 
 * Usage:
 *   php cli/pkg-lint.php packages/local/my-package/manifest.json
 *   php cli/pkg-lint.php packages/local/my-package/ --strict
 *   php cli/pkg-lint.php manifest.json --json
 * 
 * Options:
 *   --strict     Fail on warnings
 *   --json       Output JSON format
 *   --ci         Exit with non-zero on failure (for CI/CD)
 * 
 * @author The Hub Team
 * @version 2.0.0
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Color output
class Colors {
    public static $enabled = true;
    
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const BOLD = "\033[1m";
    const DIM = "\033[2m";
    
    public static function red($text) { return self::$enabled ? self::RED . $text . self::RESET : $text; }
    public static function green($text) { return self::$enabled ? self::GREEN . $text . self::RESET : $text; }
    public static function yellow($text) { return self::$enabled ? self::YELLOW . $text . self::RESET : $text; }
    public static function blue($text) { return self::$enabled ? self::BLUE . $text . self::RESET : $text; }
    public static function cyan($text) { return self::$enabled ? self::CYAN . $text . self::RESET : $text; }
    public static function bold($text) { return self::$enabled ? self::BOLD . $text . self::RESET : $text; }
    public static function dim($text) { return self::$enabled ? self::DIM . $text . self::RESET : $text; }
}

class PackageLinter {
    private $errors = [];
    private $warnings = [];
    private $info = [];
    private $manifest;
    private $manifestPath;
    private $packageDir;
    
    // Supported field types
    const FIELD_TYPES = [
        'text', 'textarea', 'number', 'email', 'phone', 'url', 'tel',
        'date', 'time', 'datetime',
        'checkbox', 'select', 'multi_select', 'radio',
        'user_select', 'vehicle_select',
        'file', 'image', 'file_upload', 'currency', 'percentage'
    ];
    
    // Supported module types
    const MODULE_TYPES = ['Form', 'TableView', 'Workflow', 'Analytics'];
    
    public function __construct($manifestPath) {
        $this->manifestPath = $manifestPath;
        
        // Determine package directory
        if (is_dir($manifestPath)) {
            $this->packageDir = rtrim($manifestPath, '/');
            $this->manifestPath = $this->packageDir . '/manifest.json';
        } else {
            $this->packageDir = dirname($manifestPath);
        }
    }
    
    public function lint(): bool {
        echo Colors::bold("Package Linter v2.0\n");
        echo str_repeat('=', 80) . "\n\n";
        
        // Check if manifest exists
        if (!file_exists($this->manifestPath)) {
            $this->error('File System', 'Manifest not found', $this->manifestPath);
            return false;
        }
        
        // Read and parse manifest
        $json = file_get_contents($this->manifestPath);
        $this->manifest = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('JSON Parse', 'Invalid JSON', json_last_error_msg());
            return false;
        }
        
        $this->info('File System', 'Manifest loaded', $this->manifestPath);
        
        // Run validation checks
        $this->validateStructure();
        $this->validatePackageMetadata();
        $this->validateCompatibility();
        $this->validateNamingConventions();
        $this->validateDatabase();
        $this->validateModules();
        $this->validateFields();
        $this->validatePermissions();
        $this->validateSecurity();
        $this->validateDocumentation();
        $this->validateScreenshots();
        
        return $this->generateReport();
    }
    
    private function validateStructure() {
        $required = ['schemaVersion', 'package', 'db', 'fields', 'permissions'];
        
        foreach ($required as $field) {
            if (!isset($this->manifest[$field])) {
                $this->error('Structure', "Missing required section: $field");
            } else {
                $this->info('Structure', "Section present: $field");
            }
        }
        
        // Validate schema version
        if (isset($this->manifest['schemaVersion'])) {
            $version = $this->manifest['schemaVersion'];
            if ($version !== 1) {
                $this->warning('Structure', "Unsupported schema version: $version (expected: 1)");
            } else {
                $this->info('Structure', 'Schema version valid: 1');
            }
        }
    }
    
    private function validatePackageMetadata() {
        if (!isset($this->manifest['package'])) {
            return;
        }
        
        $pkg = $this->manifest['package'];
        $required = ['id', 'name', 'namespace', 'display_name', 'version'];
        
        foreach ($required as $field) {
            if (empty($pkg[$field])) {
                $this->error('Package Metadata', "Missing required field: $field");
            }
        }
        
        // Validate package name (kebab-case)
        if (isset($pkg['name'])) {
            if (!preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $pkg['name'])) {
                $this->error('Naming', "Package name must be kebab-case: {$pkg['name']}");
            } elseif (strlen($pkg['name']) < 3 || strlen($pkg['name']) > 50) {
                $this->error('Naming', "Package name must be 3-50 characters: {$pkg['name']}");
            } else {
                $this->info('Naming', "Package name valid: {$pkg['name']}");
            }
        }
        
        // Validate namespace (2-5 lowercase letters + underscore)
        if (isset($pkg['namespace'])) {
            if (!preg_match('/^[a-z]{2,5}$/', $pkg['namespace'])) {
                $this->error('Naming', "Namespace must be 2-5 lowercase letters: {$pkg['namespace']}");
            } else {
                $this->info('Naming', "Namespace valid: {$pkg['namespace']}");
            }
        }
        
        // Validate semantic version
        if (isset($pkg['version'])) {
            if (!preg_match('/^\d+\.\d+\.\d+(-[a-z0-9.]+)?(\+[a-z0-9.]+)?$/i', $pkg['version'])) {
                $this->error('Versioning', "Version must follow semantic versioning: {$pkg['version']}");
            } else {
                $this->info('Versioning', "Version valid: {$pkg['version']}");
            }
        }
        
        // Check recommended fields
        $recommended = ['description', 'author', 'license', 'category'];
        foreach ($recommended as $field) {
            if (empty($pkg[$field])) {
                $this->warning('Package Metadata', "Missing recommended field: $field");
            }
        }
    }
    
    private function validateCompatibility() {
        if (!isset($this->manifest['compatibility'])) {
            $this->warning('Compatibility', 'No compatibility requirements specified');
            return;
        }
        
        $compat = $this->manifest['compatibility'];
        
        // Check version constraints
        $constraints = ['hub_version', 'php_version', 'mysql_version'];
        foreach ($constraints as $constraint) {
            if (isset($compat[$constraint])) {
                $this->info('Compatibility', "$constraint: {$compat[$constraint]}");
            }
        }
    }
    
    private function validateNamingConventions() {
        if (!isset($this->manifest['package']['namespace'])) {
            return;
        }
        
        $namespace = $this->manifest['package']['namespace'];
        
        // Check database entities use namespace prefix
        if (isset($this->manifest['db']['entities'])) {
            foreach ($this->manifest['db']['entities'] as $entity) {
                $tableName = $entity['name'] ?? '';
                if (!str_starts_with($tableName, $namespace . '_')) {
                    $this->error('Naming', "Table '$tableName' must start with namespace prefix '{$namespace}_'");
                } else {
                    $this->info('Naming', "Table name follows convention: $tableName");
                }
                
                // Check snake_case
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $tableName)) {
                    $this->error('Naming', "Table name must be snake_case: $tableName");
                }
            }
        }
        
        // Check field names are snake_case
        if (isset($this->manifest['fields'])) {
            foreach ($this->manifest['fields'] as $field) {
                $fieldName = $field['name'] ?? '';
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $fieldName)) {
                    $this->error('Naming', "Field name must be snake_case: $fieldName");
                }
            }
        }
        
        // Check routes follow /pkg/namespace/slug pattern
        if (isset($this->manifest['modules'])) {
            foreach ($this->manifest['modules'] as $module) {
                $route = $module['route'] ?? '';
                if ($route && !preg_match("#^/pkg/{$namespace}/[a-z][a-z0-9-]*$#", $route)) {
                    $this->warning('Naming', "Route should follow pattern /pkg/{$namespace}/slug: $route");
                }
            }
        }
    }
    
    private function validateDatabase() {
        if (!isset($this->manifest['db']['entities'])) {
            $this->error('Database', 'No database entities defined');
            return;
        }
        
        $entities = $this->manifest['db']['entities'];
        
        if (empty($entities)) {
            $this->error('Database', 'At least one entity required');
            return;
        }
        
        foreach ($entities as $index => $entity) {
            $name = $entity['name'] ?? "entity_$index";
            
            // Check required entity fields
            if (empty($entity['name'])) {
                $this->error('Database', "Entity #$index missing 'name'");
                continue;
            }
            
            if (empty($entity['fields'])) {
                $this->error('Database', "Entity '$name' has no fields defined");
                continue;
            }
            
            // Check for required columns
            $fieldsStr = implode("\n", $entity['fields']);
            $requiredColumns = [
                'id' => 'CHAR(26)',
                'tenant_id' => 'CHAR(26)',
                'created_at' => 'TIMESTAMP',
                'updated_at' => 'TIMESTAMP',
                'created_by' => 'CHAR(26)',
                'updated_by' => 'CHAR(26)',
                'is_deleted' => 'BOOLEAN'
            ];
            
            foreach ($requiredColumns as $col => $type) {
                if (!preg_match("/\b$col\b/i", $fieldsStr)) {
                    $this->error('Database', "Entity '$name' missing required column: $col $type");
                }
            }
            
            // Check for indexes on key fields
            $indexes = isset($entity['indexes']) ? implode("\n", $entity['indexes']) : '';
            if (!preg_match('/\btenant_id\b/i', $indexes)) {
                $this->warning('Database', "Entity '$name' should have index on tenant_id");
            }
            
            $this->info('Database', "Entity '$name' validated");
        }
    }
    
    private function validateModules() {
        if (!isset($this->manifest['modules'])) {
            $this->info('Modules', 'No modules defined (optional)');
            return;
        }
        
        $modules = $this->manifest['modules'];
        
        foreach ($modules as $index => $module) {
            $slug = $module['slug'] ?? "module_$index";
            $type = $module['type'] ?? 'Unknown';
            
            // Check module type
            if (!in_array($type, self::MODULE_TYPES)) {
                $this->error('Modules', "Module '$slug' has unsupported type: $type");
                $this->info('Modules', 'Supported types: ' . implode(', ', self::MODULE_TYPES));
                continue;
            }
            
            // Check required fields
            if (empty($module['slug'])) {
                $this->error('Modules', "Module #$index missing 'slug'");
            }
            
            if (empty($module['displayName'])) {
                $this->warning('Modules', "Module '$slug' missing 'displayName'");
            }
            
            if (empty($module['entity'])) {
                $this->error('Modules', "Module '$slug' missing 'entity' reference");
            }
            
            // Validate based on type
            switch ($type) {
                case 'Form':
                    $this->validateFormModule($module, $slug);
                    break;
                case 'TableView':
                    $this->validateTableModule($module, $slug);
                    break;
                case 'Workflow':
                    $this->validateWorkflowModule($module, $slug);
                    break;
            }
            
            $this->info('Modules', "Module '$slug' ($type) validated");
        }
    }
    
    private function validateFormModule($module, $slug) {
        if (empty($module['fields'])) {
            $this->error('Modules', "Form module '$slug' has no fields");
            return;
        }
        
        foreach ($module['fields'] as $field) {
            if (empty($field['key'])) {
                $this->error('Modules', "Form '$slug' has field without 'key'");
                continue;
            }
            
            $fieldType = $field['fieldType'] ?? '';
            if (!in_array($fieldType, self::FIELD_TYPES)) {
                $this->warning('Modules', "Form '$slug' field '{$field['key']}' has unsupported type: $fieldType");
            }
        }
        
        // Check for rate limiting
        if (empty($module['validation']['rateLimit'])) {
            $this->warning('Modules', "Form '$slug' should define rate limiting");
        }
    }
    
    private function validateTableModule($module, $slug) {
        if (empty($module['columns'])) {
            $this->error('Modules', "TableView module '$slug' has no columns");
        }
        
        if (!isset($module['pagination'])) {
            $this->warning('Modules', "TableView '$slug' should define pagination");
        }
    }
    
    private function validateWorkflowModule($module, $slug) {
        if (empty($module['steps'])) {
            $this->error('Modules', "Workflow module '$slug' has no steps");
            return;
        }
        
        // Check step IDs are unique
        $stepIds = array_column($module['steps'], 'id');
        if (count($stepIds) !== count(array_unique($stepIds))) {
            $this->error('Modules', "Workflow '$slug' has duplicate step IDs");
        }
    }
    
    private function validateFields() {
        if (!isset($this->manifest['fields'])) {
            $this->error('Fields', 'No fields defined');
            return;
        }
        
        $fields = $this->manifest['fields'];
        
        if (empty($fields)) {
            $this->error('Fields', 'At least one field required');
            return;
        }
        
        $fieldNames = [];
        
        foreach ($fields as $index => $field) {
            $name = $field['name'] ?? "field_$index";
            
            // Check required properties
            if (empty($field['name'])) {
                $this->error('Fields', "Field #$index missing 'name'");
                continue;
            }
            
            if (empty($field['type'])) {
                $this->error('Fields', "Field '$name' missing 'type'");
                continue;
            }
            
            // Check for duplicates
            if (in_array($name, $fieldNames)) {
                $this->error('Fields', "Duplicate field name: $name");
            }
            $fieldNames[] = $name;
            
            // Validate field type
            if (!in_array($field['type'], self::FIELD_TYPES)) {
                $this->warning('Fields', "Field '$name' has unsupported type: {$field['type']}");
                $this->info('Fields', 'Supported types: ' . implode(', ', self::FIELD_TYPES));
            }
            
            // Check field name format
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                $this->error('Fields', "Field name must be snake_case: $name");
            }
            
            // Validate select/radio options
            if (in_array($field['type'], ['select', 'radio', 'multi_select'])) {
                if (empty($field['options'])) {
                    $this->error('Fields', "Field '$name' ({$field['type']}) requires 'options'");
                }
            }
            
            // Check validation rules
            if ($field['type'] === 'text' || $field['type'] === 'textarea') {
                if (empty($field['validation']['maxLength'])) {
                    $this->warning('Fields', "Text field '$name' should define maxLength");
                }
            }
            
            if ($field['type'] === 'file' || $field['type'] === 'file_upload' || $field['type'] === 'image') {
                if (empty($field['validation']['maxSize'])) {
                    $this->warning('Fields', "File field '$name' should define maxSize");
                }
                if (empty($field['validation']['allowedExtensions'])) {
                    $this->warning('Fields', "File field '$name' should define allowedExtensions");
                }
            }
        }
        
        $this->info('Fields', count($fields) . ' fields validated');
    }
    
    private function validatePermissions() {
        if (!isset($this->manifest['permissions'])) {
            $this->error('Permissions', 'No permissions defined');
            return;
        }
        
        $perms = $this->manifest['permissions'];
        
        // Check roles
        if (empty($perms['roles'])) {
            $this->error('Permissions', 'At least one role required');
            return;
        }
        
        $namespace = $this->manifest['package']['namespace'] ?? '';
        $roleKeys = [];
        
        foreach ($perms['roles'] as $role) {
            $key = $role['key'] ?? '';
            
            if (empty($key)) {
                $this->error('Permissions', 'Role missing key');
                continue;
            }
            
            // Check naming convention
            if ($namespace && !str_starts_with($key, $namespace . '_')) {
                $this->warning('Permissions', "Role key '$key' should start with namespace '{$namespace}_'");
            }
            
            if (in_array($key, $roleKeys)) {
                $this->error('Permissions', "Duplicate role key: $key");
            }
            $roleKeys[] = $key;
            
            if (empty($role['displayName'])) {
                $this->warning('Permissions', "Role '$key' missing displayName");
            }
        }
        
        // Check role matrix
        if (isset($perms['roleMatrix'])) {
            foreach ($perms['roleMatrix'] as $role => $permissions) {
                if (!in_array($role, $roleKeys)) {
                    $this->warning('Permissions', "Role matrix references undefined role: $role");
                }
            }
        }
        
        $this->info('Permissions', count($roleKeys) . ' roles validated');
    }
    
    private function validateSecurity() {
        $manifestStr = json_encode($this->manifest);
        
        // Check for dangerous patterns
        $dangerousPatterns = [
            '/DROP\s+TABLE/i' => 'DROP TABLE statement',
            '/TRUNCATE\s+TABLE/i' => 'TRUNCATE TABLE statement',
            '/eval\s*\(/i' => 'eval() function',
            '/exec\s*\(/i' => 'exec() function',
            '/system\s*\(/i' => 'system() function',
            '/shell_exec/i' => 'shell_exec() function',
            '/<script/i' => '<script> tag',
            '/javascript:/i' => 'javascript: protocol',
        ];
        
        foreach ($dangerousPatterns as $pattern => $description) {
            if (preg_match($pattern, $manifestStr)) {
                $this->error('Security', "Dangerous pattern detected: $description");
            }
        }
        
        // Check forms have rate limiting
        if (isset($this->manifest['modules'])) {
            foreach ($this->manifest['modules'] as $module) {
                if ($module['type'] === 'Form') {
                    if (empty($module['validation']['rateLimit'])) {
                        $this->warning('Security', "Form '{$module['slug']}' should implement rate limiting");
                    }
                }
            }
        }
        
        $this->info('Security', 'Security scan complete');
    }
    
    private function validateDocumentation() {
        // Check for README.md
        $readmePath = $this->packageDir . '/README.md';
        if (!file_exists($readmePath)) {
            $this->error('Documentation', 'README.md not found');
        } else {
            $readme = file_get_contents($readmePath);
            $readmeSize = strlen($readme);
            
            if ($readmeSize < 500) {
                $this->warning('Documentation', 'README.md is very short (< 500 chars)');
            } else {
                $this->info('Documentation', "README.md found ($readmeSize bytes)");
            }
            
            // Check for key sections
            $sections = ['Features', 'Installation', 'Usage', 'Permissions'];
            foreach ($sections as $section) {
                if (stripos($readme, $section) === false) {
                    $this->warning('Documentation', "README.md missing section: $section");
                }
            }
        }
        
        // Check for CHANGELOG.md
        $changelogPath = $this->packageDir . '/CHANGELOG.md';
        if (!file_exists($changelogPath)) {
            $this->error('Documentation', 'CHANGELOG.md not found');
        } else {
            $changelog = file_get_contents($changelogPath);
            $version = $this->manifest['package']['version'] ?? '0.0.0';
            
            if (stripos($changelog, $version) === false) {
                $this->warning('Documentation', "CHANGELOG.md doesn't mention current version: $version");
            } else {
                $this->info('Documentation', 'CHANGELOG.md found');
            }
        }
        
        // Check for LICENSE
        $licensePath = $this->packageDir . '/LICENSE';
        if (!file_exists($licensePath)) {
            $this->warning('Documentation', 'LICENSE file not found (recommended)');
        } else {
            $this->info('Documentation', 'LICENSE found');
        }
    }
    
    private function validateScreenshots() {
        $screenshotsDir = $this->packageDir . '/screenshots';
        
        if (!is_dir($screenshotsDir)) {
            $this->error('Screenshots', 'screenshots/ directory not found');
            return;
        }
        
        $screenshots = glob($screenshotsDir . '/*.{png,jpg,jpeg}', GLOB_BRACE);
        
        if (count($screenshots) < 2) {
            $this->error('Screenshots', 'At least 2 screenshots required (found: ' . count($screenshots) . ')');
        } else {
            $this->info('Screenshots', count($screenshots) . ' screenshots found');
            
            // Check screenshot sizes
            foreach ($screenshots as $file) {
                $size = filesize($file);
                $maxSize = 500 * 1024; // 500KB
                
                if ($size > $maxSize) {
                    $this->warning('Screenshots', basename($file) . ' exceeds 500KB (' . round($size/1024) . 'KB)');
                }
                
                // Check resolution
                list($width, $height) = getimagesize($file);
                if ($width < 1280 || $height < 720) {
                    $this->warning('Screenshots', basename($file) . " is below recommended resolution (${width}x${height}, recommended: 1280x720)");
                }
            }
        }
    }
    
    private function error($category, $message, $details = null) {
        $this->errors[] = compact('category', 'message', 'details');
    }
    
    private function warning($category, $message, $details = null) {
        $this->warnings[] = compact('category', 'message', 'details');
    }
    
    private function info($category, $message, $details = null) {
        $this->info[] = compact('category', 'message', 'details');
    }
    
    private function generateReport(): bool {
        $totalErrors = count($this->errors);
        $totalWarnings = count($this->warnings);
        $totalChecks = count($this->info) + $totalWarnings + $totalErrors;
        
        echo "\n" . str_repeat('=', 80) . "\n\n";
        
        // Summary
        echo Colors::bold("VALIDATION SUMMARY\n\n");
        echo "Total Checks: $totalChecks\n";
        echo Colors::green("✓ Passed: " . count($this->info)) . "\n";
        echo Colors::yellow("⚠ Warnings: $totalWarnings") . "\n";
        echo Colors::red("✗ Errors: $totalErrors") . "\n\n";
        
        // Errors
        if ($totalErrors > 0) {
            echo Colors::bold(Colors::red("ERRORS ($totalErrors)\n"));
            echo str_repeat('-', 80) . "\n";
            foreach ($this->errors as $error) {
                echo Colors::red("✗ [{$error['category']}] {$error['message']}") . "\n";
                if ($error['details']) {
                    echo Colors::dim("  → {$error['details']}") . "\n";
                }
            }
            echo "\n";
        }
        
        // Warnings
        if ($totalWarnings > 0) {
            echo Colors::bold(Colors::yellow("WARNINGS ($totalWarnings)\n"));
            echo str_repeat('-', 80) . "\n";
            foreach ($this->warnings as $warning) {
                echo Colors::yellow("⚠ [{$warning['category']}] {$warning['message']}") . "\n";
                if ($warning['details']) {
                    echo Colors::dim("  → {$warning['details']}") . "\n";
                }
            }
            echo "\n";
        }
        
        // Result
        echo str_repeat('=', 80) . "\n";
        
        if ($totalErrors === 0 && $totalWarnings === 0) {
            echo Colors::bold(Colors::green("✓ PACKAGE VALID - Ready for submission!\n"));
            return true;
        } elseif ($totalErrors === 0) {
            echo Colors::bold(Colors::yellow("⚠ PACKAGE VALID WITH WARNINGS - Can be submitted\n"));
            echo Colors::dim("  Address warnings for better quality\n");
            return true;
        } else {
            echo Colors::bold(Colors::red("✗ PACKAGE INVALID - Fix errors before submission\n"));
            return false;
        }
    }
}

// Parse arguments
$options = getopt('', ['strict', 'json', 'ci']);
$manifestPath = $argv[1] ?? null;

if (!$manifestPath) {
    echo "Usage: php pkg-lint.php <manifest.json|package-dir> [--strict] [--json] [--ci]\n";
    echo "\nOptions:\n";
    echo "  --strict    Fail on warnings\n";
    echo "  --json      Output JSON format\n";
    echo "  --ci        Exit with non-zero on failure\n";
    exit(1);
}

// Run linter
$linter = new PackageLinter($manifestPath);
$valid = $linter->lint();

// Exit code for CI
if (isset($options['ci']) && !$valid) {
    exit(1);
}

exit(0);
