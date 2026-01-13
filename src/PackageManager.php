<?php

namespace Hub;

use Exception;
use PDO;
use ZipArchive;
use Hub\Cache;

/**
 * PackageManager - Complete section package lifecycle management
 * 
 * Handles:
 * - Package upload and extraction
 * - Installation with atomic transactions
 * - Upgrades with migrations
 * - Rollback on failure
 * - Uninstallation with cleanup
 * - Dependency resolution
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class PackageManager
{
    private $db;
    private $validator;
    private $auditLogger;
    
    const UPLOAD_DIR = '/var/www/woodson/thehub/uploads/sections/imports/';
    const PACKAGE_DIR = '/var/www/woodson/thehub/packages/';
    const TEMP_DIR = '/var/www/woodson/thehub/packages/temp/';
    
    const STATUS_PENDING = 'pending';
    const STATUS_INSTALLING = 'installing';
    const STATUS_INSTALLED = 'installed';
    const STATUS_FAILED = 'failed';
    const STATUS_UPGRADING = 'upgrading';
    const STATUS_UNINSTALLING = 'uninstalling';

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->validator = new PackageValidator();
        $this->auditLogger = new AuditLogger();
    }

    /**
     * Upload and validate a package file
     * 
     * @param array $file $_FILES array element
     * @param int $uploadedBy User ID
     * @return array Result with package_id or error
     */
    public function uploadPackage(array $file, int $uploadedBy): array
    {
        try {
            // Validate file upload
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed: ' . $this->getUploadErrorMessage($file['error']));
            }
            
            // Check file size (max 50MB)
            $maxSize = 50 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                throw new Exception('Package file too large. Maximum size: 50MB');
            }
            
            // Validate extension
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'hubpkg') {
                throw new Exception('Invalid package file. Must be .hubpkg');
            }
            
            // Generate unique filename
            $filename = uniqid('pkg_') . '.hubpkg';
            $uploadPath = self::UPLOAD_DIR . $filename;
            
            // Create upload directory if not exists
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0775, true);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            // Read and parse package
            $packageJson = file_get_contents($uploadPath);
            $packageData = json_decode($packageJson, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                unlink($uploadPath);
                throw new Exception('Invalid package JSON: ' . json_last_error_msg());
            }
            
            // Extract package metadata
            $pkg = $packageData['package'] ?? [];
            $packageId = $pkg['id'] ?? null;
            $version = $pkg['version'] ?? '0.0.0';
            $displayName = $pkg['display_name'] ?? 'Unknown Package';
            
            if (!$packageId) {
                unlink($uploadPath);
                throw new Exception('Package missing required ID');
            }
            
            // Check if package already uploaded
            $existing = $this->db->fetchOne(
                "SELECT id, version FROM section_packages WHERE package_id = ? ORDER BY uploaded_at DESC LIMIT 1",
                [$packageId]
            );
            
            // Validate package
            $installType = $existing ? 'upgrade' : 'new';
            $validation = $this->validator->validate($packageData, $installType);
            
            // Store package record with 'pending' status initially
            $this->db->execute(
                "INSERT INTO section_packages (package_id, name, version, display_name, description, author, license, file_path, file_size, package_data, uploaded_by, uploaded_at, validation_status, can_install) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $packageId,
                    $pkg['name'],
                    $version,
                    $displayName,
                    $pkg['description'] ?? null,
                    $pkg['author'] ?? null,
                    $pkg['license'] ?? 'proprietary',
                    $uploadPath,
                    $file['size'],
                    $packageJson,
                    $uploadedBy,
                    date('Y-m-d H:i:s'),
                    'pending', // Always starts as pending - must be explicitly validated
                    0 // Cannot install until validated
                ]
            );
            
            $packageRecordId = $this->db->lastInsertId();
            
            // Store compatibility check results
            $this->storeCompatibilityChecks($packageRecordId, $validation);
            
            // Log upload
            $this->auditLogger->log('package_upload', 'section_packages', $packageRecordId, null, [
                'package_id' => $packageId,
                'version' => $version,
                'display_name' => $displayName,
                'file_size' => $file['size'],
                'validation' => $validation['overall_status']
            ], $uploadedBy);
            
            return [
                'success' => true,
                'package_record_id' => $packageRecordId,
                'package_id' => $packageId,
                'version' => $version,
                'display_name' => $displayName,
                'validation' => $validation,
                'message' => $validation['can_install'] 
                    ? 'Package uploaded successfully and passed all checks'
                    : 'Package uploaded but failed compatibility checks'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Install a package
     * 
     * @param int $packageRecordId section_packages.id
     * @param int $installedBy User ID
     * @return array Installation result
     */
    public function installPackage(int $packageRecordId, int $installedBy): array
    {
        $this->db->beginTransaction();
        
        try {
            // Get package data
            $pkgRecord = $this->db->fetchOne(
                "SELECT * FROM section_packages WHERE id = ?",
                [$packageRecordId]
            );
            
            if (!$pkgRecord) {
                throw new Exception('Package not found');
            }
            
            if (!$pkgRecord['can_install']) {
                throw new Exception('Package failed compatibility checks and cannot be installed');
            }
            
            // Parse package data
            $packageData = json_decode($pkgRecord['package_data'], true);
            $pkg = $packageData['package'];
            $fields = $packageData['fields'] ?? [];
            $permissions = $packageData['permissions'] ?? [];
            $menuItems = $packageData['menu_items'] ?? [];
            
            // Check if already installed
            $existing = $this->db->fetchOne(
                "SELECT id FROM section_installations WHERE package_id = ?",
                [$pkg['id']]
            );
            
            if ($existing) {
                throw new Exception('Package already installed. Use upgrade instead.');
            }
            
            // Create section record (inactive by default - admin must enable)
            $sectionId = $this->db->insert('sections', [
                'name' => $pkg['name'],
                'slug' => $pkg['name'],
                'display_name' => $pkg['display_name'],
                'description' => $pkg['description'] ?? null,
                'icon' => $pkg['icon'] ?? 'bi-box',
                'base_url' => $pkg['base_url'] ?? "/modules/sections/{$pkg['name']}/",
                'order_position' => $this->getNextSectionOrder(),
                'is_active' => 0,  // Start inactive - admin must enable in Manage Sections
                'is_dynamic' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Create installation record
            $installId = $this->db->insert('section_installations', [
                'section_id' => $sectionId,
                'package_id' => $pkg['id'],
                'package_record_id' => $packageRecordId,
                'installed_version' => $pkg['version'],
                'installed_by' => $installedBy,
                'installed_at' => date('Y-m-d H:i:s'),
                'status' => self::STATUS_INSTALLED
            ]);
            
            // Install fields
            foreach ($fields as $field) {
                $this->installField($sectionId, $field);
            }
            
            // Install permissions (role access)
            $this->installPermissions($sectionId, $permissions, $installedBy);
            
            // Install menu items
            foreach ($menuItems as $menuItem) {
                $this->installMenuItem($sectionId, $menuItem);
            }
            
            // Store installation in package_installs table for history
            $installHistoryId = $this->db->insert('section_package_installs', [
                'package_id' => $pkg['id'],
                'package_version' => $pkg['version'],
                'status' => 'success',
                'installation_type' => 'new',
                'attempted_by' => $installedBy,
                'attempted_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
                'section_id' => $sectionId
            ]);
            
            // Move package to permanent storage
            $permanentPath = self::PACKAGE_DIR . 'local/' . $pkg['id'] . '_' . $pkg['version'] . '.hubpkg';
            if (!is_dir(dirname($permanentPath))) {
                mkdir(dirname($permanentPath), 0775, true);
            }
            copy($pkgRecord['file_path'], $permanentPath);
            
            $this->db->commit();
            
            // Log installation
            $this->auditLogger->log('package_install', 'section_installations', $installId, null, [
                'package_id' => $pkg['id'],
                'version' => $pkg['version'],
                'section_id' => $sectionId,
                'section_name' => $pkg['name']
            ], $installedBy);
            
            return [
                'success' => true,
                'section_id' => $sectionId,
                'installation_id' => $installId,
                'message' => "Package '{$pkg['display_name']}' installed successfully"
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            // Log failure
            if (isset($installHistoryId)) {
                $this->db->execute(
                    "UPDATE section_package_installs SET status = 'failed', error_message = ?, completed_at = ? WHERE id = ?",
                    [$e->getMessage(), date('Y-m-d H:i:s'), $installHistoryId]
                );
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upgrade a package to new version
     */
    public function upgradePackage(int $packageRecordId, int $upgradedBy): array
    {
        $this->db->beginTransaction();
        
        try {
            // Get package data
            $newPkgRecord = $this->db->fetchOne(
                "SELECT * FROM section_packages WHERE id = ?",
                [$packageRecordId]
            );
            
            if (!$newPkgRecord || !$newPkgRecord['can_install']) {
                throw new Exception('Package not found or failed compatibility checks');
            }
            
            $newPackageData = json_decode($newPkgRecord['package_data'], true);
            $newPkg = $newPackageData['package'];
            
            // Get current installation
            $installation = $this->db->fetchOne(
                "SELECT * FROM section_installations WHERE package_id = ?",
                [$newPkg['id']]
            );
            
            if (!$installation) {
                throw new Exception('Package not currently installed. Use install instead.');
            }
            
            $currentVersion = $installation['installed_version'];
            
            // Check if upgrade or downgrade
            $isUpgrade = version_compare($newPkg['version'], $currentVersion, '>');
            $isDowngrade = version_compare($newPkg['version'], $currentVersion, '<');
            
            if (!$isUpgrade && !$isDowngrade) {
                throw new Exception('Same version already installed');
            }
            
            // Update installation status
            $this->db->update('section_installations', $installation['id'], [
                'status' => self::STATUS_UPGRADING,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Run migrations if provided
            if (isset($newPackageData['migrations'])) {
                $this->runMigrations($installation['section_id'], $newPackageData['migrations'], $currentVersion, $newPkg['version']);
            }
            
            // Update fields (add new, update existing)
            $this->updateFields($installation['section_id'], $newPackageData['fields'] ?? []);
            
            // Update permissions
            $this->installPermissions($installation['section_id'], $newPackageData['permissions'] ?? [], $upgradedBy);
            
            // Update section metadata
            $this->db->update('sections', $installation['section_id'], [
                'display_name' => $newPkg['display_name'],
                'description' => $newPkg['description'] ?? null,
                'icon' => $newPkg['icon'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update installation record
            $this->db->update('section_installations', $installation['id'], [
                'package_record_id' => $packageRecordId,
                'installed_version' => $newPkg['version'],
                'status' => self::STATUS_INSTALLED,
                'upgraded_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log upgrade
            $this->db->insert('section_package_installs', [
                'package_id' => $newPkg['id'],
                'package_version' => $newPkg['version'],
                'status' => 'success',
                'installation_type' => $isUpgrade ? 'upgrade' : 'downgrade',
                'attempted_by' => $upgradedBy,
                'attempted_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
                'section_id' => $installation['section_id']
            ]);
            
            $this->db->commit();
            
            // Log in audit
            $this->auditLogger->log(
                $isUpgrade ? 'package_upgrade' : 'package_downgrade',
                'section_installations',
                $installation['id'],
                ['version' => $currentVersion],
                ['version' => $newPkg['version']],
                $upgradedBy
            );
            
            return [
                'success' => true,
                'action' => $isUpgrade ? 'upgrade' : 'downgrade',
                'from_version' => $currentVersion,
                'to_version' => $newPkg['version'],
                'message' => "Package upgraded from {$currentVersion} to {$newPkg['version']}"
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            // Restore status
            if (isset($installation)) {
                $this->db->update('section_installations', $installation['id'], [
                    'status' => self::STATUS_INSTALLED
                ]);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Uninstall a package
     */
    public function uninstallPackage(string $packageId, int $uninstalledBy, bool $keepData = false): array
    {
        $this->db->beginTransaction();
        
        try {
            // Get installation
            $installation = $this->db->fetchOne(
                "SELECT si.*, s.slug FROM section_installations si 
                 JOIN sections s ON si.section_id = s.id 
                 WHERE si.package_id = ?",
                [$packageId]
            );
            
            if (!$installation) {
                throw new Exception('Package not installed');
            }
            
            $sectionId = $installation['section_id'];
            
            // Check for dependent packages
            $dependents = $this->db->fetchAll(
                "SELECT package_id FROM section_package_dependencies 
                 WHERE dependency_type = 'package' AND dependency_name = ?",
                [$packageId]
            );
            
            if (!empty($dependents)) {
                $dependentIds = array_column($dependents, 'package_id');
                throw new Exception('Cannot uninstall: Other packages depend on this (' . implode(', ', $dependentIds) . ')');
            }
            
            // Update installation status
            $this->db->update('section_installations', $installation['id'], [
                'status' => self::STATUS_UNINSTALLING
            ]);
            
            if (!$keepData) {
                // Delete records (CASCADE will auto-delete history and attachments)
                $this->db->execute(
                    "DELETE FROM section_records WHERE section_id = ?",
                    [$sectionId]
                );
            }
            
            // Delete fields
            $this->db->execute(
                "DELETE FROM section_field_definitions WHERE section_id = ?",
                [$sectionId]
            );
            
            // Delete permissions
            $this->db->execute(
                "DELETE FROM section_role_access WHERE section_id = ?",
                [$sectionId]
            );
            
            $this->db->execute(
                "DELETE FROM section_access WHERE section_id = ?",
                [$sectionId]
            );
            
            // Delete administrators
            $this->db->execute(
                "DELETE FROM section_administrators WHERE section_id = ?",
                [$sectionId]
            );
            
            // Delete menu items
            $this->db->execute(
                "DELETE FROM section_menu_items WHERE section_id = ?",
                [$sectionId]
            );
            
            // Soft delete section
            $this->db->update('sections', $sectionId, [
                'is_active' => 0
            ]);
            
            // Delete installation record
            $this->db->execute(
                "DELETE FROM section_installations WHERE id = ?",
                [$installation['id']]
            );
            
            // Log uninstall
            $this->db->insert('section_package_installs', [
                'package_id' => $packageId,
                'package_version' => $installation['installed_version'],
                'status' => 'success',
                'installation_type' => 'reinstall',
                'attempted_by' => $uninstalledBy,
                'attempted_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
                'section_id' => $sectionId,
                'installation_log' => $keepData ? 'Data preserved' : 'Data deleted'
            ]);
            
            $this->db->commit();
            
            // Audit log
            $this->auditLogger->log('package_uninstall', 'section_installations', $installation['id'], [
                'package_id' => $packageId,
                'version' => $installation['installed_version'],
                'keep_data' => $keepData
            ], null, $uninstalledBy);
            
            return [
                'success' => true,
                'message' => 'Package uninstalled successfully' . ($keepData ? ' (data preserved)' : '')
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all uploaded packages
     */
    public function getPackages(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['package_id'])) {
            $where[] = 'package_id = ?';
            $params[] = $filters['package_id'];
        }
        
        if (!empty($filters['can_install'])) {
            $where[] = 'can_install = 1';
        }
        
        $sql = "SELECT * FROM section_packages WHERE " . implode(' AND ', $where) . " ORDER BY uploaded_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get installed packages
     */
    public function getInstalledPackages(): array
    {
        // Try cache first (5 minute TTL)
        $cached = Cache::get('packages:installed');
        if ($cached !== null) {
            return $cached;
        }
        
        $packages = $this->db->fetchAll("
            SELECT si.*, s.slug, s.display_name, s.icon, s.is_active, sp.version as latest_available_version
            FROM section_installations si
            JOIN sections s ON si.section_id = s.id
            LEFT JOIN section_packages sp ON si.package_id = sp.package_id AND sp.id = (
                SELECT id FROM section_packages WHERE package_id = si.package_id ORDER BY uploaded_at DESC LIMIT 1
            )
            WHERE si.status = 'installed'
            ORDER BY si.installed_at DESC
        ");
        
        // Cache for 5 minutes
        Cache::set('packages:installed', $packages, 300);
        
        return $packages;
    }

    /**
     * Check for package updates
     */
    public function checkForUpdates(): array
    {
        $updates = [];
        $installed = $this->getInstalledPackages();
        
        foreach ($installed as $pkg) {
            if ($pkg['latest_available_version'] && 
                version_compare($pkg['latest_available_version'], $pkg['installed_version'], '>')) {
                $updates[] = [
                    'package_id' => $pkg['package_id'],
                    'display_name' => $pkg['display_name'],
                    'current_version' => $pkg['installed_version'],
                    'available_version' => $pkg['latest_available_version'],
                    'installation_id' => $pkg['id']
                ];
            }
        }
        
        return $updates;
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Install a field definition
     */
    private function installField(int $sectionId, array $fieldDef): void
    {
        // Support both old and new field name formats for backward compatibility
        $sortOrder = $fieldDef['sort_order'] ?? $fieldDef['order'] ?? 0;
        $isRequired = $fieldDef['is_required'] ?? $fieldDef['required'] ?? false;
        $isSearchable = $fieldDef['is_searchable'] ?? $fieldDef['searchable'] ?? false;
        $showInList = $fieldDef['show_in_list'] ?? $fieldDef['visible_in_list'] ?? true;
        $fieldConfig = $fieldDef['field_config'] ?? $fieldDef['options'] ?? null;
        
        $this->db->insert('section_field_definitions', [
            'section_id' => $sectionId,
            'field_name' => $fieldDef['name'],
            'field_type' => $fieldDef['type'],
            'field_label' => $fieldDef['label'] ?? ucfirst($fieldDef['name']),
            'sort_order' => $sortOrder,
            'is_required' => (int)$isRequired,
            'is_searchable' => (int)$isSearchable,
            'show_in_list' => (int)$showInList,
            'validation_rules' => isset($fieldDef['validation']) ? json_encode($fieldDef['validation']) : null,
            'field_config' => $fieldConfig ? json_encode($fieldConfig) : null,
            'default_value' => $fieldDef['default_value'] ?? $fieldDef['default'] ?? null,
            'help_text' => $fieldDef['help_text'] ?? null
        ]);
    }

    /**
     * Update fields (for upgrades)
     */
    private function updateFields(int $sectionId, array $fields): void
    {
        // Get existing fields
        $existing = $this->db->fetchAll(
            "SELECT field_name FROM section_field_definitions WHERE section_id = ?",
            [$sectionId]
        );
        $existingNames = array_column($existing, 'field_name');
        
        foreach ($fields as $field) {
            if (in_array($field['name'], $existingNames)) {
                // Update existing
                $this->db->execute("
                    UPDATE section_field_definitions SET
                        field_type = ?,
                        field_label = ?,
                        sort_order = ?,
                        is_required = ?,
                        validation_rules = ?,
                        field_config = ?,
                        updated_at = ?
                    WHERE section_id = ? AND field_name = ?
                ", [
                    $field['type'],
                    $field['label'] ?? ucfirst($field['name']),
                    $field['order'] ?? 0,
                    $field['required'] ?? 0,
                    isset($field['validation']) ? json_encode($field['validation']) : null,
                    isset($field['options']) ? json_encode($field['options']) : null,
                    date('Y-m-d H:i:s'),
                    $sectionId,
                    $field['name']
                ]);
            } else {
                // Insert new
                $this->installField($sectionId, $field);
            }
        }
    }

    /**
     * Install permissions
     */
    private function installPermissions(int $sectionId, array $permissions, int $installedBy): void
    {
        // Clear existing
        $this->db->execute("DELETE FROM section_role_access WHERE section_id = ?", [$sectionId]);
        
        // Install new
        foreach ($permissions as $role => $access) {
            if ($access) {
                $this->db->insert('section_role_access', [
                    'section_id' => $sectionId,
                    'role' => $role,
                    'granted_by' => $installedBy,
                    'granted_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * Install menu item
     */
    private function installMenuItem(int $sectionId, array $menuItem): void
    {
        // Truncate icon to fit varchar(10) column
        $icon = $menuItem['icon'] ?? 'bi-circle';
        if (strlen($icon) > 10) {
            $icon = substr($icon, 0, 10);
        }
        
        $this->db->insert('section_menu_items', [
            'section_id' => $sectionId,
            'label' => $menuItem['label'],
            'route' => $menuItem['url'] ?? $menuItem['route'] ?? '',
            'icon' => $icon,
            'parent_id' => $menuItem['parent_id'] ?? null,
            'sort_order' => $menuItem['order'] ?? $menuItem['sort_order'] ?? 0,
            'required_permission' => $menuItem['minimum_role'] ?? $menuItem['required_permission'] ?? null,
            'is_active' => 1
        ]);
    }

    /**
     * Run migration scripts
     */
    private function runMigrations(int $sectionId, array $migrations, string $fromVersion, string $toVersion): void
    {
        // Find applicable migrations
        foreach ($migrations as $migration) {
            $migrationVersion = $migration['version'];
            
            // Run if migration version is between current and target
            if (version_compare($migrationVersion, $fromVersion, '>') && 
                version_compare($migrationVersion, $toVersion, '<=')) {
                
                // Execute migration SQL
                if (!empty($migration['up'])) {
                    $this->db->execute($migration['up']);
                }
                
                // Log migration
                $this->db->insert('section_package_migrations', [
                    'section_id' => $sectionId,
                    'migration_version' => $migrationVersion,
                    'migration_sql' => $migration['up'],
                    'rollback_sql' => $migration['down'] ?? null,
                    'executed_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * Store compatibility check results
     */
    private function storeCompatibilityChecks(int $packageRecordId, array $validation): void
    {
        foreach ($validation['checks'] as $check) {
            $this->db->insert('section_compatibility_checks', [
                'package_record_id' => $packageRecordId,
                'check_type' => $check['check_type'],
                'check_name' => $check['check_name'],
                'required_value' => $check['required_value'],
                'actual_value' => $check['actual_value'],
                'status' => $check['status'],
                'severity' => $check['severity'],
                'message' => $check['message'],
                'resolution' => $check['resolution'],
                'checked_at' => $check['checked_at']
            ]);
        }
    }

    /**
     * Get next section order position
     */
    private function getNextSectionOrder(): int
    {
        $result = $this->db->fetchOne("SELECT MAX(order_position) as max_order FROM sections");
        return ($result['max_order'] ?? 0) + 1;
    }

    /**
     * Enable a section (set is_active = 1)
     * 
     * @param string $packageId Package identifier
     * @param int $enabledBy User ID performing the action
     * @return array Result with success status
     */
    public function enableSection(string $packageId, int $enabledBy): array
    {
        try {
            // Get installation
            $installation = $this->db->fetchOne(
                "SELECT si.*, s.id as section_id, s.display_name, s.is_active 
                 FROM section_installations si 
                 JOIN sections s ON si.section_id = s.id 
                 WHERE si.package_id = ?",
                [$packageId]
            );
            
            if (!$installation) {
                throw new Exception('Package not installed');
            }
            
            if ($installation['is_active']) {
                return [
                    'success' => true,
                    'message' => 'Section is already enabled',
                    'was_already_enabled' => true
                ];
            }
            
            // Enable section
            $this->db->update('sections', $installation['section_id'], [
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log action
            $this->auditLogger->log(
                'section_enabled', 
                'sections', 
                $installation['section_id'], 
                ['is_active' => 0], 
                ['is_active' => 1], 
                $enabledBy
            );
            
            return [
                'success' => true,
                'message' => "Section '{$installation['display_name']}' has been enabled",
                'section_id' => $installation['section_id']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Disable a section (set is_active = 0)
     * 
     * @param string $packageId Package identifier
     * @param int $disabledBy User ID performing the action
     * @return array Result with success status
     */
    public function disableSection(string $packageId, int $disabledBy): array
    {
        try {
            // Get installation
            $installation = $this->db->fetchOne(
                "SELECT si.*, s.id as section_id, s.display_name, s.is_active 
                 FROM section_installations si 
                 JOIN sections s ON si.section_id = s.id 
                 WHERE si.package_id = ?",
                [$packageId]
            );
            
            if (!$installation) {
                throw new Exception('Package not installed');
            }
            
            if (!$installation['is_active']) {
                return [
                    'success' => true,
                    'message' => 'Section is already disabled',
                    'was_already_disabled' => true
                ];
            }
            
            // Disable section
            $this->db->update('sections', $installation['section_id'], [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log action
            $this->auditLogger->log(
                'section_disabled', 
                'sections', 
                $installation['section_id'], 
                ['is_active' => 1], 
                ['is_active' => 0], 
                $disabledBy
            );
            
            return [
                'success' => true,
                'message' => "Section '{$installation['display_name']}' has been disabled",
                'section_id' => $installation['section_id']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
}
