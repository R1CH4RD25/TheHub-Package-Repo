<?php

namespace Hub;

use Exception;

/**
 * PackageValidator - Comprehensive package compatibility and validation
 *
 * This class performs deep validation of section packages including:
 * - System requirements (Hub, PHP, MySQL versions)
 * - Dependency checking and resolution
 * - Conflict detection
 * - Security scanning
 * - Data migration validation
 *
 * @author The Hub Team
 * @version 1.0.0
 */
class PackageValidator
{
    private $db;
    private $checks = [];
    private $warnings = [];
    private $errors = [];
    private $critical = [];

    // Severity levels
    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_INFO = 'info';

    // Check statuses
    const STATUS_PASS = 'pass';
    const STATUS_FAIL = 'fail';
    const STATUS_WARNING = 'warning';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Validate a complete package
     *
     * @param array $packageData Decoded package JSON
     * @param string $installType 'new', 'upgrade', 'downgrade', 'reinstall'
     * @return array Validation results with status and details
     */
    public function validate(array $packageData, string $installType = 'new'): array
    {
        $this->resetChecks();

        try {
            // 1. Validate package structure
            $this->validateStructure($packageData);

            // 2. Validate Layer 2 compliance (Management Layer requirements)
            $this->validateLayer2Compliance($packageData);

            // 3. Check system requirements
            $this->checkSystemRequirements($packageData);

            // 4. Check dependencies
            $this->checkDependencies($packageData);

            // 5. Check for conflicts
            $this->checkConflicts($packageData);

            // 6. Validate fields and schema
            $this->validateFields($packageData);

            // 7. Check migrations (if upgrade)
            if ($installType === 'upgrade' || $installType === 'downgrade') {
                $this->validateMigrations($packageData, $installType);
            }

            // 8. Security scan
            $this->securityScan($packageData);

            // 9. Resource check (disk space, etc.)
            $this->checkResources($packageData);

            // 10. Validate globalRoleMapping keys against actual org_roles (runtime check)
            $this->validateGlobalRoleMappingRuntime($packageData);

        } catch (Exception $e) {
            $this->addCheck('exception', 'Exception During Validation', null, null,
                self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                'Validation failed: ' . $e->getMessage());
        }

        return $this->getResults();
    }

    /**
     * Validate package JSON structure
     */
    private function validateStructure(array $packageData): void
    {
        // Check for new Hub/Management format (schemaVersion 1+)
        $isNewFormat = isset($packageData['schemaVersion']) && $packageData['schemaVersion'] >= 1;
        
        if ($isNewFormat) {
            // New format requires: schemaVersion, package
            $required = ['schemaVersion', 'package'];
            
            foreach ($required as $field) {
                if (!isset($packageData[$field])) {
                    $this->addCheck('structure', "Required Field: $field", $field, 'missing',
                        self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                        "Package is missing required field: $field",
                        "Ensure package contains all required fields");
                }
            }
            
            // New format should have hub_cards OR management_sections (or both)
            // But packages can also use other structures (database, presentation, data, etc.)
            if (!isset($packageData['hub_cards']) && !isset($packageData['management_sections'])) {
                // Check if package has alternative content structures
                $altStructures = ['database', 'presentation', 'data', 'policy', 'access', 'fields'];
                $hasAltContent = false;
                foreach ($altStructures as $alt) {
                    if (isset($packageData[$alt])) {
                        $hasAltContent = true;
                        break;
                    }
                }

                if ($hasAltContent) {
                    $this->addCheck('structure', "Hub/Management Content", 'hub_cards or management_sections', 'alternative structure',
                        self::STATUS_WARNING, self::SEVERITY_WARNING,
                        "Package uses alternative content structure instead of hub_cards/management_sections",
                        "Consider migrating to hub_cards/management_sections format for full feature support");
                } else {
                    $this->addCheck('structure', "Hub/Management Content", 'hub_cards or management_sections', 'missing',
                        self::STATUS_FAIL, self::SEVERITY_WARNING,
                        "Package has no content sections (hub_cards, management_sections, or alternatives)",
                        "Add hub_cards for user-facing features or management_sections for admin features");
                }
            }
        } else {
            // Old format requires: format_version, package, fields, permissions
            $required = ['format_version', 'package', 'fields', 'permissions'];

            foreach ($required as $field) {
                if (!isset($packageData[$field])) {
                    $this->addCheck('structure', "Required Field: $field", $field, 'missing',
                        self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                        "Package is missing required field: $field",
                        "Ensure package contains all required fields");
                }
            }
        }

        // Check format version compatibility (for old format)
        if (isset($packageData['format_version'])) {
            $formatVersion = $packageData['format_version'];
            $supportedVersion = $this->getSystemRequirement('format_version');

            // Ensure version is a string
            if (!is_string($formatVersion)) {
                $this->addCheck('structure', 'Package Format Version', 'string', gettype($formatVersion),
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "Package format version must be a string, got " . gettype($formatVersion));
            } elseif (!$this->isVersionCompatible($formatVersion, $supportedVersion)) {
                $this->addCheck('structure', 'Package Format Version', $supportedVersion, $formatVersion,
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "Package format $formatVersion is not compatible with supported format $supportedVersion",
                    "Update package format or upgrade Hub to support this version");
            } else {
                $this->addCheck('structure', 'Package Format Version', $supportedVersion, $formatVersion,
                    self::STATUS_PASS, self::SEVERITY_INFO,
                    "Package format is compatible");
            }
        }

        // Validate package metadata
        if (isset($packageData['package'])) {
            $pkg = $packageData['package'];
            $requiredPkgFields = ['id', 'version'];

            foreach ($requiredPkgFields as $field) {
                if (empty($pkg[$field])) {
                    $this->addCheck('structure', "Package Metadata: $field", $field, 'missing',
                        self::STATUS_FAIL, self::SEVERITY_ERROR,
                        "Package metadata is missing: $field");
                }
            }

            // 'name' can fall back to display_name or id
            if (empty($pkg['name'])) {
                $fallback = $pkg['display_name'] ?? $pkg['id'] ?? null;
                if ($fallback) {
                    $this->addCheck('structure', 'Package Metadata: name', 'name', 'fallback to ' . $fallback,
                        self::STATUS_WARNING, self::SEVERITY_WARNING,
                        "Package metadata missing 'name', using '" . $fallback . "' as fallback");
                } else {
                    $this->addCheck('structure', 'Package Metadata: name', 'name', 'missing',
                        self::STATUS_FAIL, self::SEVERITY_ERROR,
                        "Package metadata is missing: name (and no display_name or id to fall back to)");
                }
            }

            // 'display_name' can fall back to name
            if (empty($pkg['display_name']) && !empty($pkg['name'])) {
                $this->addCheck('structure', 'Package Metadata: display_name', 'display_name', 'fallback to name',
                    self::STATUS_WARNING, self::SEVERITY_WARNING,
                    "Package metadata missing 'display_name', will use 'name'");
            }

            // Validate semantic version
            if (!empty($pkg['version'])) {
                if (!is_string($pkg['version'])) {
                    $this->addCheck('structure', 'Package Version Format', 'string', gettype($pkg['version']),
                        self::STATUS_FAIL, self::SEVERITY_ERROR,
                        "Package version must be a string, got " . gettype($pkg['version']));
                } elseif (!$this->isValidSemver($pkg['version'])) {
                    $this->addCheck('structure', 'Package Version Format', 'valid semver', $pkg['version'],
                        self::STATUS_FAIL, self::SEVERITY_ERROR,
                        "Package version must follow semantic versioning (e.g., 1.0.0)",
                        "Use format: MAJOR.MINOR.PATCH");
                }
            }
        }
    }

    /**
     * Validate Layer 2 (Management Layer) compliance
     * 
     * Packages with hub_cards MUST include:
     * - entities (governed database objects)
     * - workflow_states (submission lifecycle)
     * - manager_actions (field-level edit constraints)
     * - audit_events (required logging taxonomy)
     * - permissions with workflow_actions
     */
    private function validateLayer2Compliance(array $packageData): void
    {
        // Check if this package has Layer 1 submission entry points
        $hasHubCards = isset($packageData['hub_cards']) && !empty($packageData['hub_cards']);
        
        if (!$hasHubCards) {
            $this->addCheck('layer2', 'Layer 2 Compliance', 'not required', 'skipped',
                self::STATUS_PASS, self::SEVERITY_INFO,
                "Package has no hub_cards - Layer 2 compliance checks skipped");
            return;
        }

        // If hub_cards exist, validate required Layer 2 blocks
        $requiredBlocks = [
            'entities' => 'Governed database objects linking workflows to tables',
            'workflow_states' => 'Submission lifecycle states and transitions',
            'manager_actions' => 'Field-level edit boundaries and constraints',
            'audit_events' => 'Required logging taxonomy for manager actions',
            'permissions' => 'Role-to-capability mapping'
        ];

        foreach ($requiredBlocks as $block => $description) {
            if (!isset($packageData[$block]) || empty($packageData[$block])) {
                $this->addCheck('layer2', "Layer 2 Block: $block", 'required', 'missing',
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "Package has hub_cards but missing required Layer 2 block: $block",
                    "Add $block block to package.json - $description");
            } else {
                $this->addCheck('layer2', "Layer 2 Block: $block", 'required', 'present',
                    self::STATUS_PASS, self::SEVERITY_INFO,
                    "Layer 2 block present: $block");
            }
        }

        // Validate entities structure
        if (isset($packageData['entities']) && is_array($packageData['entities'])) {
            foreach ($packageData['entities'] as $index => $entity) {
                $entityId = $entity['id'] ?? "entity_$index";
                $requiredEntityFields = ['id', 'table', 'owner_field', 'status_field', 'workflow'];
                
                foreach ($requiredEntityFields as $field) {
                    if (empty($entity[$field])) {
                        $this->addCheck('layer2', "Entity '$entityId' Field: $field", 'required', 'missing',
                            self::STATUS_FAIL, self::SEVERITY_ERROR,
                            "Entity '$entityId' missing required field: $field");
                    }
                }
            }
        }

        // Validate workflow_states structure
        if (isset($packageData['workflow_states']) && is_array($packageData['workflow_states'])) {
            $workflowId = $packageData['workflow_states']['id'] ?? 'workflow';
            
            if (empty($packageData['workflow_states']['states'])) {
                $this->addCheck('layer2', "Workflow States", 'states array', 'missing',
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "workflow_states must include 'states' array");
            } else {
                // Check for orphaned states in transitions
                $states = array_column($packageData['workflow_states']['states'], 'id');
                $transitions = $packageData['workflow_states']['transitions'] ?? [];
                
                foreach ($transitions as $transition) {
                    if (!in_array($transition['from'] ?? '', $states)) {
                        $this->addCheck('layer2', "Workflow Transition", 'valid', 'orphaned',
                            self::STATUS_FAIL, self::SEVERITY_ERROR,
                            "Transition references non-existent state: {$transition['from']}");
                    }
                    if (!in_array($transition['to'] ?? '', $states)) {
                        $this->addCheck('layer2', "Workflow Transition", 'valid', 'orphaned',
                            self::STATUS_FAIL, self::SEVERITY_ERROR,
                            "Transition references non-existent state: {$transition['to']}");
                    }
                }
            }
        }

        // Validate manager_actions structure
        if (isset($packageData['manager_actions']) && is_array($packageData['manager_actions'])) {
            if (empty($packageData['manager_actions']['editable_fields'])) {
                $this->addCheck('layer2', "Manager Actions", 'editable_fields', 'missing',
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "manager_actions must include 'editable_fields' array",
                    "Define which fields managers can edit and under what conditions");
            } else {
                // Validate each editable field has validation rules
                foreach ($packageData['manager_actions']['editable_fields'] as $field) {
                    $fieldName = $field['field'] ?? 'unnamed';
                    
                    if (empty($field['allowed_states'])) {
                        $this->addCheck('layer2', "Editable Field: $fieldName", 'allowed_states', 'missing',
                            self::STATUS_FAIL, self::SEVERITY_ERROR,
                            "Field '$fieldName' must specify allowed_states");
                    }

                    if (isset($field['requires_reason']) && $field['requires_reason'] === true) {
                        $this->addCheck('layer2', "Editable Field: $fieldName", 'requires_reason', 'true',
                            self::STATUS_PASS, self::SEVERITY_INFO,
                            "Field '$fieldName' requires correction reason - good for audit trail");
                    }
                }
            }

            if (empty($packageData['manager_actions']['immutable_fields'])) {
                $this->addCheck('layer2', "Manager Actions", 'immutable_fields', 'missing',
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "manager_actions must include 'immutable_fields' array",
                    "Define fields that cannot be changed (e.g., created_by_user_id, created_at)");
            }
        }

        // Validate audit_events structure
        if (isset($packageData['audit_events']) && is_array($packageData['audit_events'])) {
            if (empty($packageData['audit_events']['events'])) {
                $this->addCheck('layer2', "Audit Events", 'events', 'missing',
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "audit_events must include 'events' array");
            } else {
                // Validate each event has required_fields
                foreach ($packageData['audit_events']['events'] as $event) {
                    $eventType = $event['event_type'] ?? 'unknown';
                    
                    if (empty($event['required_fields'])) {
                        $this->addCheck('layer2', "Audit Event: $eventType", 'required_fields', 'missing',
                            self::STATUS_FAIL, self::SEVERITY_ERROR,
                            "Event '$eventType' must specify required_fields for logging");
                    }
                }
            }

            // Validate global audit log is enabled
            $auditReqs = $packageData['audit_events']['audit_requirements'] ?? [];
            if (empty($auditReqs['use_global_log']) || $auditReqs['use_global_log'] !== true) {
                $this->addCheck('layer2', "Audit Requirements", 'use_global_log', 'false or missing',
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "audit_requirements must have 'use_global_log: true' for compliance",
                    "All packages MUST log to core audit_logs table");
            }
        }

        // Validate permissions include workflow_actions
        if (isset($packageData['permissions']) && is_array($packageData['permissions'])) {
            $hasWorkflowActions = false;
            
            foreach ($packageData['permissions'] as $role => $config) {
                if (isset($config['workflow_actions']) && !empty($config['workflow_actions'])) {
                    $hasWorkflowActions = true;
                    break;
                }
            }

            if (!$hasWorkflowActions) {
                $this->addCheck('layer2', "Permissions", 'workflow_actions', 'missing',
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "permissions must include 'workflow_actions' for at least one role",
                    "Define which roles can perform workflow actions (review, correct, approve, reject)");
            }
        }
    }

    /**
     * Check system requirements (Hub, PHP, MySQL versions)
     */
    private function checkSystemRequirements(array $packageData): void
    {
        // Hub version
        if (isset($packageData['compatibility']['hub_version'])) {
            $required = $packageData['compatibility']['hub_version'];
            $current = $this->getSystemRequirement('hub_version');

            if (!$this->meetsVersionConstraint($current, $required)) {
                $this->addCheck('system', 'Hub Version', $required, $current,
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "Hub version $current does not meet requirement: $required",
                    "Upgrade Hub to a compatible version or use an older package version");
            } else {
                $this->addCheck('system', 'Hub Version', $required, $current,
                    self::STATUS_PASS, self::SEVERITY_INFO,
                    "Hub version is compatible");
            }
        }

        // PHP version
        if (isset($packageData['compatibility']['php_version'])) {
            $required = $packageData['compatibility']['php_version'];
            $current = PHP_VERSION;

            if (!$this->meetsVersionConstraint($current, $required)) {
                $this->addCheck('system', 'PHP Version', $required, $current,
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "PHP version $current does not meet requirement: $required",
                    "Upgrade PHP to version $required or higher");
            } else {
                $this->addCheck('system', 'PHP Version', $required, $current,
                    self::STATUS_PASS, self::SEVERITY_INFO,
                    "PHP version is compatible");
            }
        }

        // MySQL version
        if (isset($packageData['compatibility']['mysql_version'])) {
            $required = $packageData['compatibility']['mysql_version'];
            $current = $this->getMySQLVersion();

            if (!$this->meetsVersionConstraint($current, $required)) {
                $this->addCheck('system', 'MySQL Version', $required, $current,
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "MySQL version $current does not meet requirement: $required",
                    "Upgrade MySQL/MariaDB to version $required or higher");
            } else {
                $this->addCheck('system', 'MySQL Version', $required, $current,
                    self::STATUS_PASS, self::SEVERITY_INFO,
                    "MySQL version is compatible");
            }
        }

        // PHP Extensions
        if (isset($packageData['requirements']['php_extensions'])) {
            foreach ($packageData['requirements']['php_extensions'] as $extension) {
                if (!extension_loaded($extension)) {
                    $this->addCheck('system', "PHP Extension: $extension", 'installed', 'missing',
                        self::STATUS_FAIL, self::SEVERITY_ERROR,
                        "Required PHP extension '$extension' is not installed",
                        "Install extension: sudo apt install php-$extension (or equivalent)");
                } else {
                    $this->addCheck('system', "PHP Extension: $extension", 'installed', 'installed',
                        self::STATUS_PASS, self::SEVERITY_INFO,
                        "PHP extension is available");
                }
            }
        }
    }

    /**
     * Check package dependencies
     */
    private function checkDependencies(array $packageData): void
    {
        // Check required packages
        if (isset($packageData['requirements']['packages'])) {
            foreach ($packageData['requirements']['packages'] as $pkgId => $constraint) {
                $isOptional = false;
                $versionConstraint = '*';

                if (is_array($constraint)) {
                    $isOptional = $constraint['optional'] ?? false;
                    $versionConstraint = $constraint['version'] ?? '*';
                } else {
                    $versionConstraint = $constraint;
                }

                $installed = $this->isPackageInstalled($pkgId);

                if (!$installed) {
                    $severity = $isOptional ? self::SEVERITY_WARNING : self::SEVERITY_ERROR;
                    $status = $isOptional ? self::STATUS_WARNING : self::STATUS_FAIL;

                    $this->addCheck('dependencies', "Package: $pkgId", 'installed', 'not installed',
                        $status, $severity,
                        ($isOptional ? 'Optional' : 'Required') . " package '$pkgId' is not installed",
                        "Install package '$pkgId' first" . ($isOptional ? ' (optional, but recommended)' : ''));
                } else {
                    // Check version compatibility
                    $installedVersion = $this->getInstalledPackageVersion($pkgId);

                    if (!$this->meetsVersionConstraint($installedVersion, $versionConstraint)) {
                        $this->addCheck('dependencies', "Package: $pkgId Version", $versionConstraint, $installedVersion,
                            self::STATUS_FAIL, self::SEVERITY_ERROR,
                            "Installed version $installedVersion does not meet constraint: $versionConstraint",
                            "Upgrade '$pkgId' to a compatible version");
                    } else {
                        $this->addCheck('dependencies', "Package: $pkgId", $versionConstraint, $installedVersion,
                            self::STATUS_PASS, self::SEVERITY_INFO,
                            "Dependency is installed and compatible");
                    }
                }
            }
        }

        // Check required modules
        if (isset($packageData['requirements']['core_modules'])) {
            foreach ($packageData['requirements']['core_modules'] as $module) {
                if (!$this->isCoreModuleAvailable($module)) {
                    $this->addCheck('dependencies', "Core Module: $module", 'available', 'missing',
                        self::STATUS_FAIL, self::SEVERITY_ERROR,
                        "Required core module '$module' is not available",
                        "Enable or install the '$module' module");
                } else {
                    $this->addCheck('dependencies', "Core Module: $module", 'available', 'available',
                        self::STATUS_PASS, self::SEVERITY_INFO,
                        "Core module is available");
                }
            }
        }

        // Check database tables
        if (isset($packageData['requirements']['database_tables'])) {
            foreach ($packageData['requirements']['database_tables'] as $table) {
                if (!$this->tableExists($table)) {
                    $this->addCheck('dependencies', "Database Table: $table", 'exists', 'missing',
                        self::STATUS_FAIL, self::SEVERITY_ERROR,
                        "Required database table '$table' does not exist",
                        "Install required module or run migrations that create this table");
                } else {
                    $this->addCheck('dependencies', "Database Table: $table", 'exists', 'exists',
                        self::STATUS_PASS, self::SEVERITY_INFO,
                        "Required table exists");
                }
            }
        }
    }

    /**
     * Check for conflicts with existing packages
     */
    private function checkConflicts(array $packageData): void
    {
        $packageId = $packageData['package']['id'] ?? 'unknown';
        $packageSlug = $packageData['package']['name'] ?? '';

        // Check for slug conflicts
        if ($packageSlug) {
            $existing = $this->db->fetchOne("SELECT id, display_name FROM sections WHERE slug = ?", [$packageSlug]);

            if ($existing) {
                $this->addCheck('conflicts', 'Section Slug Conflict', 'unique', 'duplicate',
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "A section with slug '$packageSlug' already exists: {$existing['display_name']}",
                    "Uninstall existing section or rename this package");
            }
        }

        // Check declared conflicts
        if (isset($packageData['conflicts'])) {
            foreach ($packageData['conflicts'] as $conflictPkg => $versionConstraint) {
                if ($this->isPackageInstalled($conflictPkg)) {
                    $installedVersion = $this->getInstalledPackageVersion($conflictPkg);

                    if ($versionConstraint === '*' || $this->meetsVersionConstraint($installedVersion, $versionConstraint)) {
                        $this->addCheck('conflicts', "Conflicting Package: $conflictPkg", 'not installed', "installed ($installedVersion)",
                            self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                            "This package conflicts with installed package: $conflictPkg $installedVersion",
                            "Uninstall '$conflictPkg' before installing this package");
                    }
                }
            }
        }
    }

    /**
     * Validate field definitions
     */
    private function validateFields(array $packageData): void
    {
        // Skip field validation for new Hub/Management format
        $isNewFormat = isset($packageData['schemaVersion']) && $packageData['schemaVersion'] >= 1;
        if ($isNewFormat) {
            // New format doesn't use fields, it uses hub_cards and management_sections
            $this->addCheck('fields', 'Field Validation', 'new format', 'skipped',
                self::STATUS_PASS, self::SEVERITY_INFO,
                "Field validation skipped - using Hub/Management format (schemaVersion {$packageData['schemaVersion']})");
            return;
        }
        
        if (!isset($packageData['fields']) || !is_array($packageData['fields'])) {
            $this->addCheck('fields', 'Field Definitions', 'array', 'missing',
                self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                "Package must include field definitions");
            return;
        }

        $fieldNames = [];
        $supportedTypes = $this->getSupportedFieldTypes();

        foreach ($packageData['fields'] as $index => $field) {
            // Required field properties
            if (empty($field['name'])) {
                $this->addCheck('fields', "Field #$index", 'name', 'missing',
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "Field at index $index is missing 'name' property");
                continue;
            }

            $fieldName = $field['name'];

            // Check for duplicate field names
            if (in_array($fieldName, $fieldNames)) {
                $this->addCheck('fields', "Field: $fieldName", 'unique', 'duplicate',
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "Duplicate field name: $fieldName");
            }
            $fieldNames[] = $fieldName;

            // Validate field type
            if (empty($field['type'])) {
                $this->addCheck('fields', "Field: $fieldName", 'type', 'missing',
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "Field '$fieldName' is missing type");
            } elseif (!in_array($field['type'], $supportedTypes)) {
                $this->addCheck('fields', "Field: $fieldName Type", implode(', ', $supportedTypes), $field['type'],
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "Field type '{$field['type']}' is not supported",
                    "Supported types: " . implode(', ', $supportedTypes));
            }

            // Validate field name format (alphanumeric + underscores)
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $fieldName)) {
                $this->addCheck('fields', "Field: $fieldName Format", 'valid format', 'invalid',
                    self::STATUS_FAIL, self::SEVERITY_ERROR,
                    "Field name must start with letter and contain only lowercase letters, numbers, and underscores");
            }
        }

        // Check for at least one field
        if (empty($fieldNames)) {
            $this->addCheck('fields', 'Field Count', '>=1', '0',
                self::STATUS_FAIL, self::SEVERITY_ERROR,
                "Package must define at least one field");
        } else {
            $this->addCheck('fields', 'Field Definitions', 'valid', count($fieldNames) . ' fields',
                self::STATUS_PASS, self::SEVERITY_INFO,
                count($fieldNames) . " fields validated successfully");
        }
    }

    /**
     * Validate migration scripts (for upgrades)
     */
    private function validateMigrations(array $packageData, string $installType): void
    {
        // TODO: Implement migration validation
        // Check that migration paths exist
        // Validate SQL syntax
        // Check for rollback scripts
    }

    /**
     * Security scan for malicious code
     */
    private function securityScan(array $packageData): void
    {
        $jsonString = json_encode($packageData);

        // Check for SQL injection patterns
        $sqlPatterns = [
            '/DROP\s+TABLE/i',
            '/TRUNCATE\s+TABLE/i',
            '/DELETE\s+FROM.*WHERE.*1\s*=\s*1/i',
            '/;\s*DROP/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $jsonString)) {
                $this->addCheck('security', 'SQL Injection Pattern', 'none', 'detected',
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "Potential SQL injection pattern detected in package",
                    "Review package for malicious code");
                break;
            }
        }

        // Check for PHP code execution patterns
        $phpPatterns = [
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec/i',
            '/passthru/i',
            '/`[^`]+`/i',  // Backtick execution
        ];

        foreach ($phpPatterns as $pattern) {
            if (preg_match($pattern, $jsonString)) {
                $this->addCheck('security', 'Code Execution Pattern', 'none', 'detected',
                    self::STATUS_FAIL, self::SEVERITY_CRITICAL,
                    "Potential code execution pattern detected in package",
                    "Review package for malicious code");
                break;
            }
        }

        // Check for suspicious URLs
        if (preg_match('/https?:\/\/[^"\']+/i', $jsonString, $matches)) {
            foreach ($matches as $url) {
                // Simple check - could be enhanced
                if (strpos($url, '.ru') !== false || strpos($url, '.cn') !== false) {
                    $this->addCheck('security', 'Suspicious URL', 'trusted', $url,
                        self::STATUS_WARNING, self::SEVERITY_WARNING,
                        "Package contains URL from untrusted domain: $url",
                        "Verify URL is legitimate");
                }
            }
        }

        $this->addCheck('security', 'Security Scan', 'clean', 'scanned',
            self::STATUS_PASS, self::SEVERITY_INFO,
            "Basic security scan completed");
    }

    /**
     * Check system resources
     */
    private function checkResources(array $packageData): void
    {
        // Check disk space (at least 100MB free)
        $freeSpace = disk_free_space('/');
        $requiredSpace = 100 * 1024 * 1024; // 100MB

        if ($freeSpace < $requiredSpace) {
            $this->addCheck('resources', 'Disk Space', '>= 100MB', $this->formatBytes($freeSpace),
                self::STATUS_FAIL, self::SEVERITY_ERROR,
                "Insufficient disk space: " . $this->formatBytes($freeSpace) . " available",
                "Free up disk space before installing");
        } else {
            $this->addCheck('resources', 'Disk Space', '>= 100MB', $this->formatBytes($freeSpace),
                self::STATUS_PASS, self::SEVERITY_INFO,
                "Sufficient disk space available");
        }
    }

    // ========================================================================
    // Runtime Role Validation (checks against actual org_roles table)
    // ========================================================================

    /**
     * Validate globalRoleMapping keys against actual org_roles in the database.
     * Org roles are admin-configured so we warn (not fail) on unknown names.
     */
    private function validateGlobalRoleMappingRuntime(array $packageData): void
    {
        $mapping = $packageData['policy']['globalRoleMapping'] ?? [];
        if (empty($mapping)) {
            return;
        }

        try {
            $rows = $this->db->fetchAll("SELECT name FROM org_roles WHERE is_active = 1");
            $dbRoles = array_column($rows, 'name');
        } catch (Exception $e) {
            // org_roles table may not exist yet — skip
            $this->addCheck('roles', 'Org Roles Table', 'accessible', 'unavailable',
                self::STATUS_WARNING, self::SEVERITY_WARNING,
                'Could not verify globalRoleMapping against org_roles — table may not exist yet');
            return;
        }

        if (empty($dbRoles)) {
            $this->addCheck('roles', 'Org Roles', '>0 roles', '0 roles',
                self::STATUS_WARNING, self::SEVERITY_WARNING,
                'No org_roles defined — globalRoleMapping keys will be ignored until roles are configured',
                'Add organization roles in Admin → Settings before installing');
            return;
        }

        $unmapped = [];
        foreach (array_keys($mapping) as $roleName) {
            if (!in_array($roleName, $dbRoles)) {
                $unmapped[] = $roleName;
            }
        }

        if (!empty($unmapped)) {
            $this->addCheck('roles', 'globalRoleMapping → org_roles', 'all mapped', count($unmapped) . ' unmatched',
                self::STATUS_WARNING, self::SEVERITY_WARNING,
                'globalRoleMapping references org roles not in this Hub: ' . implode(', ', $unmapped),
                'Create these roles in Admin → Settings, or they will be ignored at install');
        } else {
            $this->addCheck('roles', 'globalRoleMapping → org_roles', 'all mapped', 'all matched',
                self::STATUS_PASS, self::SEVERITY_INFO,
                'All globalRoleMapping keys match existing org_roles');
        }
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Check if version meets constraint (supports semver)
     */
    private function meetsVersionConstraint(string $version, string $constraint): bool
    {
        // Handle simple constraints
        if ($constraint === '*') return true;

        // >= operator
        if (preg_match('/^>=\s*(.+)$/', $constraint, $matches)) {
            return version_compare($version, $matches[1], '>=');
        }

        // > operator
        if (preg_match('/^>\s*(.+)$/', $constraint, $matches)) {
            return version_compare($version, $matches[1], '>');
        }

        // <= operator
        if (preg_match('/^<=\s*(.+)$/', $constraint, $matches)) {
            return version_compare($version, $matches[1], '<=');
        }

        // < operator
        if (preg_match('/^<\s*(.+)$/', $constraint, $matches)) {
            return version_compare($version, $matches[1], '<');
        }

        // Range: >=1.0.0 <2.0.0
        if (preg_match('/^>=\s*([^\s]+)\s+<\s*([^\s]+)$/', $constraint, $matches)) {
            return version_compare($version, $matches[1], '>=') &&
                   version_compare($version, $matches[2], '<');
        }

        // Caret ^1.0.0 (>=1.0.0 <2.0.0)
        if (preg_match('/^\^(.+)$/', $constraint, $matches)) {
            $minVersion = $matches[1];
            $parts = explode('.', $minVersion);
            $major = $parts[0];
            $nextMajor = ((int)$major + 1) . '.0.0';

            return version_compare($version, $minVersion, '>=') &&
                   version_compare($version, $nextMajor, '<');
        }

        // Tilde ~1.2.0 (>=1.2.0 <1.3.0)
        if (preg_match('/^~(.+)$/', $constraint, $matches)) {
            $minVersion = $matches[1];
            $parts = explode('.', $minVersion);
            $major = $parts[0];
            $minor = $parts[1] ?? '0';
            $nextMinor = "$major." . ((int)$minor + 1) . ".0";

            return version_compare($version, $minVersion, '>=') &&
                   version_compare($version, $nextMinor, '<');
        }

        // Exact version
        return version_compare($version, $constraint, '==');
    }

    /**
     * Check if version is valid semver
     */
    private function isValidSemver(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(-[a-z0-9.]+)?(\+[a-z0-9.]+)?$/i', $version);
    }

    /**
     * Check if versions are compatible
     */
    private function isVersionCompatible(string $v1, string $v2): bool
    {
        return version_compare($v1, $v2, '==');
    }

    /**
     * Get system requirement value
     */
    private function getSystemRequirement(string $key): string
    {
        $result = $this->db->fetchOne(
            "SELECT requirement_value FROM system_requirements WHERE requirement_key = ?",
            [$key]
        );

        return $result['requirement_value'] ?? '0.0.0';
    }

    /**
     * Get MySQL version
     */
    private function getMySQLVersion(): string
    {
        $result = $this->db->fetchOne("SELECT VERSION() as version");
        return $result['version'] ?? '0.0.0';
    }

    /**
     * Check if package is installed
     */
    private function isPackageInstalled(string $packageId): bool
    {
        $result = $this->db->fetchOne(
            "SELECT id FROM section_installations WHERE package_id = ?",
            [$packageId]
        );

        return $result !== null;
    }

    /**
     * Get installed package version
     */
    private function getInstalledPackageVersion(string $packageId): string
    {
        $result = $this->db->fetchOne(
            "SELECT installed_version FROM section_installations WHERE package_id = ?",
            [$packageId]
        );

        return $result['installed_version'] ?? '0.0.0';
    }

    /**
     * Check if core module is available
     */
    private function isCoreModuleAvailable(string $module): bool
    {
        // Check if module table/class exists
        // For now, check common modules
        $availableModules = ['vehicles', 'users', 'sections'];
        return in_array($module, $availableModules);
    }

    /**
     * Check if database table exists
     */
    private function tableExists(string $table): bool
    {
        try {
            $result = $this->db->fetchOne("SHOW TABLES LIKE ?", [$table]);
            return $result !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get supported field types
     */
    private function getSupportedFieldTypes(): array
    {
        return [
            'text', 'textarea', 'number', 'email', 'phone', 'url',
            'date', 'time', 'datetime',
            'checkbox', 'select', 'multi_select', 'radio',
            'user_select', 'vehicle_select',
            'file_upload', 'currency', 'percentage'
        ];
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Add a check result
     */
    private function addCheck(
        string $checkType,
        string $checkName,
        ?string $requiredValue,
        ?string $actualValue,
        string $status,
        string $severity,
        string $message,
        string $resolution = null
    ): void {
        $check = [
            'check_type' => $checkType,
            'check_name' => $checkName,
            'required_value' => $requiredValue,
            'actual_value' => $actualValue,
            'status' => $status,
            'severity' => $severity,
            'message' => $message,
            'resolution' => $resolution,
            'checked_at' => date('Y-m-d H:i:s')
        ];

        $this->checks[] = $check;

        // Categorize
        if ($status === self::STATUS_FAIL) {
            if ($severity === self::SEVERITY_CRITICAL) {
                $this->critical[] = $check;
            } else {
                $this->errors[] = $check;
            }
        } elseif ($status === self::STATUS_WARNING) {
            $this->warnings[] = $check;
        }
    }

    /**
     * Reset checks for new validation
     */
    private function resetChecks(): void
    {
        $this->checks = [];
        $this->warnings = [];
        $this->errors = [];
        $this->critical = [];
    }

    /**
     * Get validation results
     */
    private function getResults(): array
    {
        $totalChecks = count($this->checks);
        $passed = count(array_filter($this->checks, fn($c) => $c['status'] === self::STATUS_PASS));
        $failed = count($this->errors) + count($this->critical);
        $warnings = count($this->warnings);

        // Determine overall status
        $canInstall = count($this->critical) === 0 && count($this->errors) === 0;
        $overallStatus = 'fail';

        if ($canInstall && $warnings === 0) {
            $overallStatus = 'pass';
        } elseif ($canInstall && $warnings > 0) {
            $overallStatus = 'warning';
        }

        return [
            'can_install' => $canInstall,
            'overall_status' => $overallStatus,
            'summary' => [
                'total_checks' => $totalChecks,
                'passed' => $passed,
                'failed' => $failed,
                'warnings' => $warnings,
                'critical' => count($this->critical)
            ],
            'checks' => $this->checks,
            'critical_issues' => $this->critical,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Generate human-readable compatibility report
     */
    public function generateReport(array $validationResults): string
    {
        $report = "PACKAGE COMPATIBILITY REPORT\n";
        $report .= str_repeat('=', 80) . "\n\n";

        $summary = $validationResults['summary'];
        $report .= "Total Checks: {$summary['total_checks']}\n";
        $report .= "✅ Passed: {$summary['passed']}\n";
        $report .= "❌ Failed: {$summary['failed']}\n";
        $report .= "⚠️  Warnings: {$summary['warnings']}\n";
        $report .= "🔴 Critical: {$summary['critical']}\n\n";

        $report .= "Overall Status: " . strtoupper($validationResults['overall_status']) . "\n";
        $report .= "Can Install: " . ($validationResults['can_install'] ? 'YES' : 'NO') . "\n\n";

        // Critical issues
        if (!empty($validationResults['critical_issues'])) {
            $report .= "CRITICAL FAILURES\n";
            $report .= str_repeat('-', 80) . "\n";
            foreach ($validationResults['critical_issues'] as $issue) {
                $report .= "❌ {$issue['check_name']}\n";
                $report .= "   {$issue['message']}\n";
                if ($issue['resolution']) {
                    $report .= "   → {$issue['resolution']}\n";
                }
                $report .= "\n";
            }
        }

        // Errors
        if (!empty($validationResults['errors'])) {
            $report .= "ERRORS\n";
            $report .= str_repeat('-', 80) . "\n";
            foreach ($validationResults['errors'] as $error) {
                $report .= "❌ {$error['check_name']}\n";
                $report .= "   {$error['message']}\n";
                if ($error['resolution']) {
                    $report .= "   → {$error['resolution']}\n";
                }
                $report .= "\n";
            }
        }

        // Warnings
        if (!empty($validationResults['warnings'])) {
            $report .= "WARNINGS\n";
            $report .= str_repeat('-', 80) . "\n";
            foreach ($validationResults['warnings'] as $warning) {
                $report .= "⚠️  {$warning['check_name']}\n";
                $report .= "   {$warning['message']}\n";
                if ($warning['resolution']) {
                    $report .= "   → {$warning['resolution']}\n";
                }
                $report .= "\n";
            }
        }

        $report .= str_repeat('=', 80) . "\n";
        $report .= "Generated: " . $validationResults['timestamp'] . "\n";

        return $report;
    }
}
