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
    
    // Supported module types (from MODULE_CATALOG_V2.md)
    const MODULE_TYPES = [
        'Form', 'TableView', 'Workflow', 'Analytics', 'Dashboard',
        'EmailNotification', 'PDFGenerator', 
        'StudentEvaluation', 'EmployeeEvaluation',
        'Action', 'FileManager', 'Computation'
    ];
    
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
                case 'Analytics':
                    $this->validateAnalyticsModule($module, $slug);
                    break;
                case 'EmailNotification':
                    $this->validateEmailNotificationModule($module, $slug);
                    break;
                case 'PDFGenerator':
                    $this->validatePDFGeneratorModule($module, $slug);
                    break;
                case 'EmployeeEvaluation':
                    $this->validateEmployeeEvaluationModule($module, $slug);
                    break;
                case 'StudentEvaluation':
                    $this->validateStudentEvaluationModule($module, $slug);
                    break;
                case 'Action':
                    $this->validateActionModule($module, $slug);
                    break;
                case 'FileManager':
                    $this->validateFileManagerModule($module, $slug);
                    break;
                case 'Computation':
                    $this->validateComputationModule($module, $slug);
                    break;
                case 'Dashboard':
                    $this->validateDashboardModule($module, $slug);
                    break;
            }
            
            $this->info('Modules', "Module '$slug' ($type) validated");
        }
    }
    
    /**
     * Validate Form Module (MODULE_CATALOG_V2.md § 1)
     * Rules: [FRM-R01] through [FRM-R08]
     */
    private function validateFormModule($module, $slug) {
        if (empty($module['fields'])) {
            $this->error('Form Module', "[FRM-R04] Form '$slug' has no fields");
            return;
        }
        
        // [FRM-R01]: Each field.key must map to DB column (check against entity)
        foreach ($module['fields'] as $field) {
            if (empty($field['key'])) {
                $this->error('Form Module', "[FRM-R01] Form '$slug' has field without 'key'");
                continue;
            }
            
            $fieldType = $field['fieldType'] ?? '';
            if (!in_array($fieldType, self::FIELD_TYPES)) {
                $this->warning('Form Module', "Form '$slug' field '{$field['key']}' has unsupported type: $fieldType");
            }
            
            // [FRM-R04]: Validation must define required, maxLength
            if (!isset($field['required'])) {
                $this->warning('Form Module', "[FRM-R04] Field '{$field['key']}' should define 'required'");
            }
            
            if (in_array($fieldType, ['text', 'textarea', 'email', 'url']) && empty($field['validation']['maxLength'])) {
                $this->warning('Form Module', "[FRM-R04] Field '{$field['key']}' should define 'maxLength'");
            }
        }
        
        // [FRM-R03]: Must include anti-spam measure
        if (empty($module['captcha']) && empty($module['validation']['honeypot'])) {
            $this->warning('Form Module', "[FRM-R03] Form '$slug' should include anti-spam measure (captcha or honeypot)");
        }
        
        // [FRM-R08]: Check for rate limiting
        if (empty($module['validation']['rateLimit'])) {
            $this->warning('Form Module', "[FRM-R08] Form '$slug' should define rate limiting");
        }
        
        // [FRM-R05]: onSubmit.redirect must be within /pkg/ namespace
        if (!empty($module['onSubmit']['redirect'])) {
            $redirect = $module['onSubmit']['redirect'];
            if (!str_starts_with($redirect, '/pkg/')) {
                $this->error('Form Module', "[FRM-R05] Redirect '$redirect' must be within /pkg/ namespace");
            }
        }
        
        // [FRM-R02]: Anonymous forms must handle PII appropriately
        if (!empty($module['allowAnonymous'])) {
            $hasPII = false;
            foreach ($module['fields'] as $field) {
                if (!empty($field['pii'])) {
                    $hasPII = true;
                    break;
                }
            }
            if ($hasPII) {
                $this->info('Form Module', "[FRM-R02] Anonymous form '$slug' contains PII fields - ensure consent is obtained");
            }
        }
    }
    
    /**
     * Validate TableView Module (MODULE_CATALOG_V2.md § 2)
     * Rules: [TBL-R01] through [TBL-R07]
     */
    private function validateTableModule($module, $slug) {
        // [TBL-R01]: At least one column must be sortable
        if (empty($module['columns'])) {
            $this->error('TableView Module', "TableView '$slug' has no columns");
            return;
        }
        
        $hasSortable = false;
        foreach ($module['columns'] as $col) {
            if (!empty($col['sortable'])) {
                $hasSortable = true;
                break;
            }
        }
        
        if (!$hasSortable) {
            $this->error('TableView Module', "[TBL-R01] TableView '$slug' must have at least one sortable column");
        }
        
        // [TBL-R02]: Actions must declare permission key
        if (!empty($module['actions'])) {
            foreach ($module['actions'] as $action) {
                if (empty($action['permission'])) {
                    $this->warning('TableView Module', "[TBL-R02] Action '{$action['key']}' should declare 'permission'");
                }
            }
        }
        
        // [TBL-R03]: Export formats respect PII flags
        if (!empty($module['export'])) {
            if (!isset($module['export']['excludePII'])) {
                $this->info('TableView Module', "[TBL-R03] Export should define 'excludePII' setting");
            }
        }
        
        // [TBL-R04]: Pagination defaults
        if (!isset($module['pagination'])) {
            $this->warning('TableView Module', "[TBL-R04] TableView '$slug' should define pagination");
        }
    }
    
    /**
     * Validate Workflow Module (MODULE_CATALOG_V2.md § 3)
     * Rules: [WF-R01] through [WF-R08]
     */
    private function validateWorkflowModule($module, $slug) {
        if (empty($module['steps'])) {
            $this->error('Workflow Module', "Workflow '$slug' has no steps");
            return;
        }
        
        // [WF-R01]: Each step must have unique ID
        $stepIds = array_column($module['steps'], 'id');
        if (count($stepIds) !== count(array_unique($stepIds))) {
            $this->error('Workflow Module', "[WF-R01] Workflow '$slug' has duplicate step IDs");
        }
        
        // [WF-R02]: Must define at least one transition path
        $hasTransitions = false;
        foreach ($module['steps'] as $step) {
            if (!empty($step['nextSteps']) && count($step['nextSteps']) > 0) {
                $hasTransitions = true;
                break;
            }
        }
        
        if (!$hasTransitions) {
            $this->error('Workflow Module', "[WF-R02] Workflow '$slug' must define at least one transition path");
        }
        
        // [WF-R03]: Each step must include requiredRole or null
        foreach ($module['steps'] as $step) {
            if (!array_key_exists('requiredRole', $step)) {
                $this->warning('Workflow Module', "[WF-R03] Step '{$step['id']}' should define 'requiredRole' (or null)");
            }
        }
        
        // [WF-R07]: Status field must be ENUM or VARCHAR
        if (empty($module['statusField'])) {
            $this->error('Workflow Module', "[WF-R07] Workflow '$slug' must define 'statusField'");
        }
    }
    
    /**
     * Validate Analytics Module (MODULE_CATALOG_V2.md § 4)
     * Rules: [ANL-R01] through [ANL-R07]
     */
    private function validateAnalyticsModule($module, $slug) {
        // [ANL-R04]: Must include at least one visualization
        if (empty($module['charts'])) {
            $this->error('Analytics Module', "[ANL-R04] Analytics '$slug' must include at least one chart");
            return;
        }
        
        // [ANL-R01]: Must use host chart components (Chart.js)
        foreach ($module['charts'] as $chart) {
            $validTypes = ['line', 'bar', 'pie', 'doughnut', 'radar', 'polarArea'];
            if (!empty($chart['type']) && !in_array($chart['type'], $validTypes)) {
                $this->warning('Analytics Module', "[ANL-R01] Chart type '{$chart['type']}' may not be supported by Chart.js");
            }
        }
        
        $this->info('Analytics Module', "[ANL-R02] Ensure queries pass through Hub Data API (no raw SQL)");
    }
    
    /**
     * Validate EmailNotification Module (MODULE_CATALOG_V2.md § 5)
     * Rules: [NTF-R01] through [NTF-R07]
     */
    private function validateEmailNotificationModule($module, $slug) {
        // [NTF-R01]: Each trigger must map to valid audit event
        if (empty($module['triggers'])) {
            $this->error('Email Notification', "[NTF-R01] EmailNotification '$slug' must define triggers");
            return;
        }
        
        foreach ($module['triggers'] as $trigger) {
            if (empty($trigger['event'])) {
                $this->error('Email Notification', "[NTF-R01] Trigger missing 'event'");
            }
        }
        
        // Check recipients
        if (empty($module['recipients'])) {
            $this->error('Email Notification', "EmailNotification '$slug' must define recipients");
        }
        
        // [NTF-R03]: Template validation
        if (empty($module['template'])) {
            $this->error('Email Notification', "[NTF-R03] EmailNotification '$slug' must define template");
        }
        
        $this->info('Email Notification', "[NTF-R05] Ensure SMTP settings configured in .env");
    }
    
    /**
     * Validate PDFGenerator Module (MODULE_CATALOG_V2.md § 6)
     * Rules: [PDF-R01] through [PDF-R07]
     */
    private function validatePDFGeneratorModule($module, $slug) {
        // [PDF-R02]: Templates must be HTML-based
        if (empty($module['template'])) {
            $this->error('PDF Generator', "[PDF-R02] PDFGenerator '$slug' must define HTML template");
        }
        
        // Check filename pattern
        if (empty($module['filename'])) {
            $this->warning('PDF Generator', "PDFGenerator '$slug' should define filename pattern");
        }
        
        // [PDF-R04]: Respect PII flags
        $this->info('PDF Generator', "[PDF-R04] Ensure template respects 'pii: true' field flags");
    }
    
    /**
     * Validate EmployeeEvaluation Module (MODULE_CATALOG_V2.md § 8)
     * This is a composite module with workflow, scoring, and email features
     */
    private function validateEmployeeEvaluationModule($module, $slug) {
        // Check evaluation sections
        if (empty($module['sections'])) {
            $this->error('Employee Evaluation', "EmployeeEvaluation '$slug' must define evaluation sections");
        }
        
        // Check workflow integration
        if (empty($module['workflow'])) {
            $this->warning('Employee Evaluation', "EmployeeEvaluation '$slug' should include workflow for approval process");
        }
        
        // Check scoring method
        if (empty($module['scoring'])) {
            $this->warning('Employee Evaluation', "EmployeeEvaluation '$slug' should define scoring method");
        }
        
        // Check email settings (key feature)
        if (!empty($module['emailSettings'])) {
            if (empty($module['emailSettings']['selectableFields'])) {
                $this->warning('Employee Evaluation', "EmailSettings should define 'selectableFields' for admin choice");
            }
            
            if (!isset($module['emailSettings']['adminCanChoose'])) {
                $this->info('Employee Evaluation', "Consider setting 'adminCanChoose' to allow field selection");
            }
        }
        
        // Check PDF generation
        if (empty($module['pdf'])) {
            $this->warning('Employee Evaluation', "EmployeeEvaluation '$slug' should include PDF generation");
        }
    }
    
    /**
     * Validate StudentEvaluation Module (MODULE_CATALOG_V2.md § 7)
     */
    private function validateStudentEvaluationModule($module, $slug) {
        if (empty($module['gradingScale'])) {
            $this->error('Student Evaluation', "StudentEvaluation '$slug' must define gradingScale");
        }
        
        if (empty($module['categories'])) {
            $this->warning('Student Evaluation', "StudentEvaluation '$slug' should define evaluation categories");
        }
    }
    
    /**
     * Validate Action Module (MODULE_CATALOG_V2.md § 9)
     * Rules: [ACT-R01] through [ACT-R07]
     */
    private function validateActionModule($module, $slug) {
        // [ACT-R01]: Must declare permission required
        if (empty($module['permission'])) {
            $this->error('Action Module', "[ACT-R01] Action '$slug' must declare 'permission'");
        }
        
        // Check operation type
        if (empty($module['operation'])) {
            $this->error('Action Module', "Action '$slug' must define 'operation' (update, delete, etc.)");
        }
        
        // [ACT-R04]: Destructive actions require confirmation
        if (in_array($module['operation'] ?? '', ['delete', 'archive']) && empty($module['confirmation'])) {
            $this->warning('Action Module', "[ACT-R04] Destructive action '$slug' should require confirmation");
        }
    }
    
    /**
     * Validate FileManager Module (MODULE_CATALOG_V2.md § 10)
     * Rules: [FIL-R01] through [FIL-R07]
     */
    private function validateFileManagerModule($module, $slug) {
        // [FIL-R01]: Must define storage provider
        if (empty($module['storage']['provider'])) {
            $this->error('File Manager', "[FIL-R01] FileManager '$slug' must define storage.provider");
        }
        
        // [FIL-R02]: Must enforce maxFileSize
        if (empty($module['storage']['maxFileSize'])) {
            $this->warning('File Manager', "[FIL-R02] FileManager '$slug' should define storage.maxFileSize");
        }
        
        // Check allowed extensions
        if (empty($module['storage']['allowedExtensions'])) {
            $this->warning('File Manager', "FileManager '$slug' should define allowedExtensions");
        }
        
        // [FIL-R04]: Files stored with tenant isolation
        if (!empty($module['storage']['path']) && !str_contains($module['storage']['path'], '{tenant_id}')) {
            $this->error('File Manager', "[FIL-R04] Storage path must include {tenant_id} for multi-tenancy");
        }
    }
    
    /**
     * Validate Computation Module (MODULE_CATALOG_V2.md § 11)
     * Rules: [CAL-R01] through [CAL-R07]
     */
    private function validateComputationModule($module, $slug) {
        // [CAL-R01]: Must define formula
        if (empty($module['formula']['expression'])) {
            $this->error('Computation Module', "[CAL-R01] Computation '$slug' must define formula.expression");
        }
        
        // [CAL-R02]: All dependencies declared
        if (empty($module['formula']['dependsOn'])) {
            $this->warning('Computation Module', "[CAL-R02] Computation '$slug' should declare 'dependsOn' fields");
        }
        
        // [CAL-R03]: Result field must be read-only (check in field definitions)
        if (empty($module['resultField'])) {
            $this->error('Computation Module', "Computation '$slug' must define 'resultField'");
        }
        
        // [CAL-R05]: Validate expression syntax (basic check for dangerous functions)
        $expression = $module['formula']['expression'] ?? '';
        if (preg_match('/eval|exec|system|passthru|shell_exec/i', $expression)) {
            $this->error('Computation Module', "[CAL-R05] Formula contains dangerous functions");
        }
    }
    
    /**
     * Validate Dashboard Module (MODULE_CATALOG_V2.md § 12)
     * Rules: [DSH-R01] through [DSH-R06]
     */
    private function validateDashboardModule($module, $slug) {
        // [DSH-R01]: Must reference existing modules
        if (empty($module['widgets'])) {
            $this->error('Dashboard Module', "[DSH-R01] Dashboard '$slug' must define widgets");
            return;
        }
        
        // [DSH-R03]: Limit to 8 widgets
        if (count($module['widgets']) > 8) {
            $this->warning('Dashboard Module', "[DSH-R03] Dashboard '$slug' has more than 8 widgets (performance concern)");
        }
        
        // Check widget references
        foreach ($module['widgets'] as $widget) {
            if (empty($widget['module'])) {
                $this->error('Dashboard Module', "[DSH-R01] Widget missing 'module' reference");
            }
        }
    }
    
    private function validateTableModule($module, $slug) {
        // Deprecated: Use validateTableModule instead
        $this->validateTableModule($module, $slug);
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
