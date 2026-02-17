<?php

namespace Hub;

use Exception;
use PDO;
use ZipArchive;
use Hub\Cache;
use Hub\Package\Schema\PackageSchemaValidator;

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

            // Validate package (runtime checks: system reqs, deps, conflicts, security)
            $installType = $existing ? 'upgrade' : 'new';
            $validation = $this->validator->validate($packageData, $installType);

            // Schema validation (v3 structure: pages, fields, resources, policy, access, showIf)
            $schemaVersion = $packageData['schemaVersion'] ?? null;
            if ($schemaVersion && version_compare($schemaVersion, '3.0.0', '>=')) {
                $schemaValidator = new PackageSchemaValidator();
                $schemaResult = $schemaValidator->validate($packageData);

                // Merge schema errors into runtime validation
                if (!$schemaResult['valid']) {
                    $validation['can_install'] = false;
                    $validation['overall_status'] = 'fail';
                    foreach ($schemaResult['errors'] as $err) {
                        $validation['checks'][] = [
                            'check_type' => 'schema',
                            'check_name' => $err['path'],
                            'required_value' => null,
                            'actual_value' => null,
                            'status' => 'fail',
                            'severity' => 'error',
                            'message' => $err['message'],
                            'resolution' => $err['fix'] ?? null,
                            'checked_at' => date('Y-m-d H:i:s'),
                        ];
                        $validation['errors'][] = end($validation['checks']);
                    }
                    $validation['summary']['failed'] = ($validation['summary']['failed'] ?? 0) + count($schemaResult['errors']);
                }

                // Merge schema warnings
                foreach ($schemaResult['warnings'] as $warn) {
                    $validation['checks'][] = [
                        'check_type' => 'schema',
                        'check_name' => $warn['path'],
                        'required_value' => null,
                        'actual_value' => null,
                        'status' => 'warning',
                        'severity' => 'warning',
                        'message' => $warn['message'],
                        'resolution' => $warn['fix'] ?? null,
                        'checked_at' => date('Y-m-d H:i:s'),
                    ];
                    $validation['warnings'][] = end($validation['checks']);
                }
                $validation['summary']['warnings'] = ($validation['summary']['warnings'] ?? 0) + count($schemaResult['warnings']);
                $validation['summary']['total_checks'] = count($validation['checks']);
                $validation['schema_validation'] = $schemaResult;
            }

            // Store package record with 'pending' status initially
            $pkgName = $pkg['name'] ?? $pkg['id'] ?? $packageId;
            $this->db->execute(
                "INSERT INTO section_packages (package_id, name, version, display_name, description, author, license, file_path, file_size, package_data, uploaded_by, uploaded_at, validation_status, can_install)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $packageId,
                    $pkgName,
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

            // Detect schema version
            $schemaVersion = $packageData['schemaVersion'] ?? $packageData['schema_version'] ?? '1.0.0';
            $isV3 = version_compare($schemaVersion, '3.0.0', '>=');

            // Normalize package identity — v3 uses 'id' + 'display_name', older uses 'name'
            $pkgId = $pkg['id'] ?? $pkg['name'] ?? $pkgRecord['package_id'];
            $pkgName = $pkg['name'] ?? $pkg['id'] ?? $pkgRecord['name'];
            $pkgDisplayName = $pkg['display_name'] ?? $pkg['name'] ?? $pkgId;
            $pkgSlug = $this->generateSlug($pkgName);

            // v2 schema fields (may be empty for v3)
            $fields = $packageData['fields'] ?? [];
            $permissions = $packageData['permissions'] ?? [];
            $menuItems = $packageData['menu_items'] ?? [];

            // Check if already installed
            $existing = $this->db->fetchOne(
                "SELECT id FROM section_installations WHERE package_id = ?",
                [$pkgId]
            );

            if ($existing) {
                throw new Exception('Package already installed. Use upgrade instead.');
            }

            // Determine section_type from package data
            $sectionType = $pkg['section_type'] ?? 'other';
            $validSectionTypes = ['recording', 'request_form', 'scheduled_report', 'hub', 'other'];
            if (!in_array($sectionType, $validSectionTypes)) {
                $sectionType = 'other';
            }

            // Create section record (inactive by default - admin must enable)
            $sectionId = $this->db->insert('sections', [
                'name' => $pkgName,
                'slug' => $pkgSlug,
                'display_name' => $pkgDisplayName,
                'description' => $pkg['description'] ?? null,
                'icon' => $pkg['icon'] ?? 'bi-box',
                'base_url' => $pkg['base_url'] ?? "/p/{$pkgId}/",
                'section_type' => $sectionType,
                'order_position' => $this->getNextSectionOrder(),
                'is_active' => 0,  // Start inactive - admin must enable in Manage Sections
                'is_dynamic' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Create installation record
            $installId = $this->db->insert('section_installations', [
                'section_id' => $sectionId,
                'package_id' => $pkgId,
                'package_record_id' => $packageRecordId,
                'installed_version' => $pkg['version'],
                'installed_by' => $installedBy,
                'installed_at' => date('Y-m-d H:i:s'),
                'status' => self::STATUS_INSTALLED
            ]);

            // ========================================
            // V2 Schema: fields, permissions, hub_cards, management_sections, menu_items
            // ========================================

            // Install fields (v2)
            foreach ($fields as $field) {
                $this->installField($sectionId, $field);
            }

            // Install permissions/role access (v2)
            if (!empty($permissions)) {
                $this->installPermissions($sectionId, $permissions, $installedBy);
            }

            // V2 Spec: Process hub_cards access to create section_role_access entries
            if (!empty($packageData['hub_cards'])) {
                $this->installHubCardAccess($sectionId, $packageData['hub_cards'], $installedBy);
            }

            // Install menu items - support both legacy and v2 spec
            $menuOrder = 1;

            // Legacy: menu_items array
            foreach ($menuItems as $menuItem) {
                $normalized = $this->normalizeMenuItem($menuItem, $menuOrder++);
                $this->installMenuItem($sectionId, $normalized);
            }

            // V2 Spec: hub_cards → menu items
            if (!empty($packageData['hub_cards'])) {
                foreach ($packageData['hub_cards'] as $card) {
                    $normalized = $this->normalizeMenuItem([
                        'label' => $card['title'] ?? 'Untitled',
                        'route' => $card['route'] ?? '#',
                        'icon' => $card['icon'] ?? 'fa-circle',
                        'required_permission' => $card['access'][0] ?? null
                    ], $menuOrder++);
                    $this->installMenuItem($sectionId, $normalized);
                }
            }

            // V2 Spec: management_sections → menu items
            if (!empty($packageData['management_sections'])) {
                foreach ($packageData['management_sections'] as $section) {
                    $normalized = $this->normalizeMenuItem([
                        'label' => $section['title'] ?? 'Untitled',
                        'route' => $section['route'] ?? '#',
                        'icon' => $section['icon'] ?? 'fa-gear',
                        'required_permission' => $section['access'][0] ?? null
                    ], $menuOrder++);
                    $this->installMenuItem($sectionId, $normalized);
                }
            }

            // ========================================
            // V3 Schema: presentation.pages → menu items, policy → role access
            // ========================================
            if ($isV3) {
                // Create menu items from presentation pages
                if (!empty($packageData['presentation']['pages'])) {
                    foreach ($packageData['presentation']['pages'] as $pageKey => $page) {
                        // Skip sub-pages like view/{id}, edit/{id}, add — only top-level nav
                        $route = $page['route'] ?? "/{$pageKey}";
                        if (strpos($route, '{') !== false) {
                            continue; // Skip parameterized routes
                        }
                        // Only create menu items for primary navigation pages
                        if (in_array($pageKey, ['index', 'import', 'export'])) {
                            $label = $page['title'] ?? ucfirst($pageKey);
                            $icon = $this->extractPageIcon($page) ?? $pkg['icon'] ?? 'fa-circle';
                            $normalized = $this->normalizeMenuItem([
                                'label' => $label,
                                'route' => $route,
                                'icon' => $icon,
                            ], $menuOrder++);
                            $this->installMenuItem($sectionId, $normalized);
                        }
                    }
                }

                // Install package-defined roles into package_roles table
                if (!empty($packageData['policy']['roles'])) {
                    $this->installPackageRoles(
                        $sectionId,
                        $packageData['policy']['roles'],
                        $packageData['policy']['globalRoleMapping'] ?? [],
                        $installedBy
                    );
                }

                // Install role access from policy.globalRoleMapping
                if (!empty($packageData['policy']['globalRoleMapping'])) {
                    $this->installPolicyRoleAccess(
                        $sectionId,
                        $packageData['policy']['globalRoleMapping'],
                        $installedBy
                    );
                } else {
                    // No policy defined — grant super_admin access by default
                    $this->installPolicyRoleAccess($sectionId, ['super_admin' => 'admin'], $installedBy);
                }

                // Store package config as capabilities JSON for the runtime engine
                $capabilitiesJson = json_encode([
                    'schemaVersion' => $schemaVersion,
                    'database' => $packageData['database'] ?? null,
                    'presentation' => $packageData['presentation'] ?? null,
                    'data' => $packageData['data'] ?? null,
                    'policy' => $packageData['policy'] ?? null,
                    'access' => $packageData['access'] ?? null,
                ]);
                $this->db->execute(
                    "UPDATE sections SET capabilities_json = ?, supports_capabilities = 1 WHERE id = ?",
                    [$capabilitiesJson, $sectionId]
                );
            }

            // Warn if no menu items created
            if ($menuOrder === 1) {
                error_log("PackageManager: Warning - No menu items created for package {$pkgId}");
            }

            // Store installation in package_installs table for history
            $installHistoryId = $this->db->insert('section_package_installs', [
                'package_id' => $pkgId,
                'package_version' => $pkg['version'],
                'status' => 'success',
                'installation_type' => 'new',
                'attempted_by' => $installedBy,
                'attempted_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
                'section_id' => $sectionId
            ]);

            // Move package to permanent storage
            $permanentPath = self::PACKAGE_DIR . 'local/' . $pkgId . '_' . $pkg['version'] . '.hubpkg';
            if (!is_dir(dirname($permanentPath))) {
                mkdir(dirname($permanentPath), 0775, true);
            }
            if (!empty($pkgRecord['file_path']) && file_exists($pkgRecord['file_path'])) {
                copy($pkgRecord['file_path'], $permanentPath);
            }

            $this->db->commit();

            // ========================================
            // Post-commit: Run database migrations
            // DDL (CREATE TABLE) can't be rolled back in MySQL, so this runs outside the transaction.
            // If migrations fail, the package is still installed but flagged with a warning.
            // ========================================
            $migrationResult = $this->runPackageDatabaseMigrations($pkgId, $packageData, $sectionId, $installedBy);

            // Clear installed packages cache
            Cache::delete('packages:installed');

            // Log installation
            $this->auditLogger->log('package_install', 'section_installations', $installId, null, [
                'package_id' => $pkgId,
                'version' => $pkg['version'],
                'section_id' => $sectionId,
                'section_name' => $pkgDisplayName,
                'schema_version' => $schemaVersion
            ], $installedBy);

            return [
                'success' => true,
                'section_id' => $sectionId,
                'installation_id' => $installId,
                'message' => "Package '{$pkgDisplayName}' installed successfully",
                'database_migration' => $migrationResult ?? null
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

            // Update capabilities_json so sidebar/pages reflect new version
            if (!empty($newPackageData['presentation'])) {
                $capabilitiesJson = json_encode([
                    'presentation' => $newPackageData['presentation'],
                    'data' => $newPackageData['data'] ?? null,
                    'policy' => $newPackageData['policy'] ?? null,
                    'access' => $newPackageData['access'] ?? null,
                ]);
                $this->db->execute(
                    "UPDATE sections SET capabilities_json = ? WHERE id = ?",
                    [$capabilitiesJson, $installation['section_id']]
                );
            }

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

            // Clear installed packages cache
            Cache::delete('packages:installed');

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

            // Hard delete the section (to allow reinstallation)
            $this->db->execute(
                "DELETE FROM sections WHERE id = ?",
                [$sectionId]
            );

            // Delete installation record
            $this->db->execute(
                "DELETE FROM section_installations WHERE id = ?",
                [$installation['id']]
            );

            // Log uninstall (section_id is NULL because section was deleted)
            $this->db->insert('section_package_installs', [
                'package_id' => $packageId,
                'package_version' => $installation['installed_version'],
                'status' => 'success',
                'installation_type' => 'reinstall',
                'attempted_by' => $uninstalledBy,
                'attempted_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s'),
                'section_id' => null,
                'installation_log' => $keepData ? 'Data preserved' : 'Data deleted'
            ]);

            $this->db->commit();

            // Clear installed packages cache
            Cache::delete('packages:installed');

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
     *
     * @param bool $skipCache  When true, bypass cache and read fresh from DB.
     *                         Mutations (install/upgrade/uninstall) clear cache,
     *                         but callers like checkForUpdates() should always
     *                         pass true so stale data never hides a version change.
     */
    public function getInstalledPackages(bool $skipCache = false): array
    {
        // Try cache first (5 minute TTL) unless caller wants fresh data
        if (!$skipCache) {
            $cached = Cache::get('packages:installed');
            if ($cached !== null) {
                return $cached;
            }
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
        // Always read fresh from DB — update detection must never serve stale data
        $installed = $this->getInstalledPackages(true);

        foreach ($installed as $pkg) {
            if (
                $pkg['latest_available_version'] &&
                version_compare($pkg['latest_available_version'], $pkg['installed_version'], '>')
            ) {
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
        // Valid role ENUM values from database schema
        $validRoles = ['user', 'driver', 'manager', 'admin', 'super_admin'];

        // Clear existing
        $this->db->execute("DELETE FROM section_role_access WHERE section_id = ?", [$sectionId]);

        // Install new (skip invalid roles)
        foreach ($permissions as $role => $access) {
            if ($access) {
                // Validate role against ENUM values
                if (!in_array($role, $validRoles, true)) {
                    error_log("PackageManager: Skipping invalid role '{$role}' for section {$sectionId}");
                    continue; // Skip invalid role
                }

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
     * Install hub card access (V2 spec)
     * Processes hub_cards[].access arrays and creates section_role_access entries
     * Maps custom role names to database ENUM values
     */
    private function installHubCardAccess(int $sectionId, array $hubCards, int $installedBy): void
    {
        // Valid database roles from ENUM
        $validDbRoles = [
            'staff',
            'student',
            'maintenance_staff',
            'custodial',
            'cafeteria',
            'custodial_manager',
            'maintenance_director',
            'business_manager',
            'substitute_manager',
            'counselor',
            'principal',
            'admin',
            'super_admin'
        ];

        // Map custom role names to database ENUM roles
        $roleMap = [
            // Hub package standard roles
            'hub_user' => 'staff',
            'hub_student' => 'student',

            // Management roles
            'management_crew' => 'maintenance_staff',
            'management_custodial' => 'custodial',
            'management_cafeteria' => 'cafeteria',
            'management_fleet_manager' => 'maintenance_director',
            'management_director' => 'maintenance_director',
            'management_business_manager' => 'business_manager',
            'management_substitute_manager' => 'substitute_manager',
            'management_counselor' => 'counselor',
            'management_principal' => 'principal',
            'management_admin' => 'admin',

            // Direct database role names (pass through)
            'staff' => 'staff',
            'student' => 'student',
            'maintenance_staff' => 'maintenance_staff',
            'custodial' => 'custodial',
            'cafeteria' => 'cafeteria',
            'custodial_manager' => 'custodial_manager',
            'maintenance_director' => 'maintenance_director',
            'business_manager' => 'business_manager',
            'substitute_manager' => 'substitute_manager',
            'counselor' => 'counselor',
            'principal' => 'principal',
            'admin' => 'admin',
            'super_admin' => 'super_admin'
        ];

        // Collect all unique roles from all hub cards
        $rolesToGrant = [];
        foreach ($hubCards as $card) {
            if (!empty($card['access']) && is_array($card['access'])) {
                foreach ($card['access'] as $customRole) {
                    // Map custom role to database role
                    if (isset($roleMap[$customRole])) {
                        $dbRole = $roleMap[$customRole];
                        $rolesToGrant[$dbRole] = true;
                    } else {
                        error_log("PackageManager: Unknown hub_card role '{$customRole}' - skipping");
                    }
                }
            }
        }

        // Always grant super_admin access
        $rolesToGrant['super_admin'] = true;

        // Insert section_role_access entries
        foreach (array_keys($rolesToGrant) as $role) {
            if (in_array($role, $validDbRoles, true)) {
                $this->db->insert('section_role_access', [
                    'section_id' => $sectionId,
                    'role' => $role,
                    'granted_by' => $installedBy,
                    'granted_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        error_log("PackageManager: Created " . count($rolesToGrant) . " role access entries for section {$sectionId}");
    }

    /**
     * Generate a URL-safe slug from a package name/id
     */
    private function generateSlug(string $name): string
    {
        // Replace dots and underscores with hyphens
        $slug = str_replace(['.', '_', ' '], '-', $name);
        // Remove non-alphanumeric chars except hyphens
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));
        // Collapse multiple hyphens
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        // Ensure max 50 chars (slug column limit)
        return substr($slug, 0, 50);
    }

    /**
     * Extract a representative icon from a v3 presentation page
     */
    private function extractPageIcon(array $page): ?string
    {
        if (!empty($page['components'])) {
            foreach ($page['components'] as $component) {
                if (!empty($component['config']['icon'])) {
                    return $component['config']['icon'];
                }
            }
        }
        return null;
    }

    /**
     * Install role access from v3 policy.globalRoleMapping
     * Maps global roles (super_admin, admin, staff, etc.) to section_role_access entries
     */
    private function installPolicyRoleAccess(int $sectionId, array $globalRoleMapping, int $installedBy): void
    {
        // Valid database role ENUMs for section_role_access table
        $validDbRoles = [
            'staff',
            'student',
            'maintenance_staff',
            'custodial',
            'cafeteria',
            'custodial_manager',
            'maintenance_director',
            'business_manager',
            'substitute_manager',
            'counselor',
            'principal',
            'admin',
            'super_admin'
        ];

        // Clear existing for this section
        $this->db->execute("DELETE FROM section_role_access WHERE section_id = ?", [$sectionId]);

        $rolesToGrant = [];
        $unmappedRoles = [];
        foreach ($globalRoleMapping as $globalRole => $packageRole) {
            // Only add to section_role_access if the global role matches a valid ENUM
            if (in_array($globalRole, $validDbRoles, true)) {
                $rolesToGrant[$globalRole] = true;
            } else {
                // Non-ENUM roles (custom school roles) — try to map via org_roles
                $unmappedRoles[$globalRole] = $packageRole;
            }
        }

        // Always grant super_admin access
        $rolesToGrant['super_admin'] = true;

        foreach (array_keys($rolesToGrant) as $role) {
            $this->db->insert('section_role_access', [
                'section_id' => $sectionId,
                'role' => $role,
                'granted_by' => $installedBy,
                'granted_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Log unmapped roles for admin attention
        if (!empty($unmappedRoles)) {
            error_log("PackageManager: " . count($unmappedRoles) . " role(s) from package not in ENUM: " . implode(', ', array_keys($unmappedRoles)) . " — use Package Role Mapping to assign to org_roles");
        }

        error_log("PackageManager: Created " . count($rolesToGrant) . " section_role_access entries for section {$sectionId} (v3 policy)");
    }

    /**
     * Install package-defined roles into package_roles table and create
     * default mappings to org_roles where globalRoleMapping provides hints.
     *
     * This is the many-to-many role system: package roles (viewer, editor, importer)
     * get mapped to org_roles (Principal, Teacher, Administrator) by admins.
     * The globalRoleMapping in the package JSON provides initial suggestions.
     */
    private function installPackageRoles(int $sectionId, array $policyRoles, array $globalRoleMapping, int $installedBy): void
    {
        // Clear existing package roles for this section
        $this->db->execute("DELETE FROM package_roles WHERE package_id = ?", [$sectionId]);

        $tierLevel = 1;
        $roleIdMap = []; // role_key => package_roles.id

        foreach ($policyRoles as $roleKey => $roleDef) {
            // Determine tier from inheritance chain or explicit level
            $currentTier = $roleDef['tier'] ?? $tierLevel;

            // Build permissions JSON from queries + mutations
            $permissions = json_encode([
                'queries' => $roleDef['queries'] ?? [],
                'mutations' => $roleDef['mutations'] ?? [],
                'inherits' => $roleDef['inherits'] ?? null,
            ]);

            $roleId = $this->db->insert('package_roles', [
                'package_id' => $sectionId,
                'role_key' => $roleKey,
                'role_name' => $roleDef['label'] ?? ucfirst($roleKey),
                'tier_level' => $currentTier,
                'description' => $roleDef['description'] ?? null,
                'permissions' => $permissions,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $roleIdMap[$roleKey] = $roleId;
            $tierLevel++;
        }

        // Create default package_role_mappings from globalRoleMapping hints
        // e.g., "principal" => "viewer" means map org_role "Principal" to package_role "viewer"
        $mappingsCreated = 0;
        foreach ($globalRoleMapping as $globalRoleName => $packageRoleKey) {
            if (!isset($roleIdMap[$packageRoleKey])) {
                continue; // Package role doesn't exist
            }

            // Try to find matching org_role by name (case-insensitive)
            $orgRole = $this->db->fetchOne(
                "SELECT id FROM org_roles WHERE LOWER(name) = LOWER(?) AND is_active = 1",
                [$globalRoleName]
            );

            if ($orgRole) {
                try {
                    $this->db->insert('package_role_mappings', [
                        'package_id' => $sectionId,
                        'package_role_id' => $roleIdMap[$packageRoleKey],
                        'org_role_id' => $orgRole['id'],
                        'mapped_by' => $installedBy,
                        'mapped_at' => date('Y-m-d H:i:s'),
                    ]);
                    $mappingsCreated++;
                } catch (Exception $e) {
                    // Duplicate mapping — skip silently
                    error_log("PackageManager: Skipped duplicate role mapping {$globalRoleName} => {$packageRoleKey}");
                }
            }
        }

        error_log("PackageManager: Created " . count($roleIdMap) . " package roles and {$mappingsCreated} org_role mappings for section {$sectionId}");
    }

    /**
     * Normalize menu item data from various formats
     */
    private function normalizeMenuItem(array $raw, int $defaultOrder): array
    {
        return [
            'label' => $raw['label'] ?? $raw['title'] ?? 'Untitled',
            'route' => $raw['route'] ?? $raw['url'] ?? '#',
            'icon' => $raw['icon'] ?? 'fa-circle',
            'sort_order' => $raw['sort_order'] ?? $raw['order'] ?? $defaultOrder,
            'parent_id' => $raw['parent_id'] ?? null,
            'required_permission' => $raw['minimum_role'] ?? $raw['required_permission'] ?? null,
            'is_active' => $raw['is_active'] ?? 1
        ];
    }

    /**
     * Install menu item
     */
    private function installMenuItem(int $sectionId, array $menuItem): void
    {
        // Icon column now VARCHAR(50) - no need to truncate
        $icon = $menuItem['icon'] ?? 'fa-circle';

        $this->db->insert('section_menu_items', [
            'section_id' => $sectionId,
            'label' => $menuItem['label'],
            'route' => $menuItem['route'],
            'icon' => $icon,
            'parent_id' => $menuItem['parent_id'] ?? null,
            'sort_order' => $menuItem['sort_order'] ?? 0,
            'required_permission' => $menuItem['required_permission'] ?? null,
            'is_active' => $menuItem['is_active'] ?? 1
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
            if (
                version_compare($migrationVersion, $fromVersion, '>') &&
                version_compare($migrationVersion, $toVersion, '<=')
            ) {

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
     * Run database migrations for a package during installation.
     *
     * Checks two sources in priority order:
     * 1. SQL files in the package's local directory: packages/local/{pkgId}/migrations/*.sql
     * 2. database/schema.sql in the package's local directory
     *
     * Tables are created in the hub database (woodson_hub) — table prefixes (vm_, sd_, etc.)
     * provide namespace isolation. This avoids needing CREATE DATABASE privileges and enables
     * native foreign keys to the users table.
     *
     * @return array Migration result with status and details
     */
    private function runPackageDatabaseMigrations(string $pkgId, array $packageData, int $sectionId, int $installedBy): array
    {
        $result = ['status' => 'skipped', 'tables_created' => 0, 'details' => []];

        // Normalize package ID for directory lookup (dots → dashes)
        $dirName = str_replace('.', '-', $pkgId);
        $basePaths = [
            self::PACKAGE_DIR . 'local/' . $dirName,
            self::PACKAGE_DIR . 'local/' . $pkgId,
        ];

        $sqlFiles = [];

        foreach ($basePaths as $basePath) {
            if (!is_dir($basePath)) {
                continue;
            }

            // Priority 1: migrations/*.sql files (numbered, run in order)
            $migrationsDir = $basePath . '/migrations';
            if (is_dir($migrationsDir)) {
                $files = scandir($migrationsDir);
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                        $sqlFiles[] = [
                            'path' => $migrationsDir . '/' . $file,
                            'name' => $file,
                            'source' => 'migrations'
                        ];
                    }
                }
                usort($sqlFiles, fn($a, $b) => strcmp($a['name'], $b['name']));
                break;
            }

            // Priority 2: database/schema.sql (single file)
            $schemaPath = $basePath . '/database/schema.sql';
            if (file_exists($schemaPath)) {
                $sqlFiles[] = [
                    'path' => $schemaPath,
                    'name' => 'schema.sql',
                    'source' => 'database'
                ];
                break;
            }
        }

        if (empty($sqlFiles)) {
            $result['details'][] = 'No migration files found';
            return $result;
        }

        // Check if migrations already ran for this package
        $this->ensurePackageMigrationsTable();
        try {
            $existing = $this->db->fetchOne(
                "SELECT id FROM package_migrations WHERE package_id = ? AND migration = 'initial_install'",
                [$pkgId]
            );
            if ($existing) {
                $result['status'] = 'already_run';
                $result['details'][] = 'Database migrations already executed';
                return $result;
            }
        } catch (\Exception $e) {
            // Swallow — table may have just been created
        }

        // Execute each SQL file
        $result['status'] = 'running';
        $tablesCreated = 0;

        foreach ($sqlFiles as $sqlFile) {
            try {
                $sql = file_get_contents($sqlFile['path']);
                if (empty(trim($sql))) {
                    continue;
                }

                $statements = $this->splitSqlStatements($sql);

                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (empty($stmt)) {
                        continue;
                    }

                    $this->db->getConnection()->exec($stmt);

                    if (preg_match('/CREATE\s+TABLE/i', $stmt)) {
                        $tablesCreated++;
                    }
                }

                $result['details'][] = "Executed {$sqlFile['name']} ({$sqlFile['source']})";
            } catch (\Exception $e) {
                $result['status'] = 'partial';
                $result['details'][] = "Error in {$sqlFile['name']}: " . $e->getMessage();
                error_log("PackageManager: Migration error for {$pkgId}: " . $e->getMessage());
            }
        }

        $result['tables_created'] = $tablesCreated;

        // Record migration
        try {
            $this->db->insert('package_migrations', [
                'package_id' => $pkgId,
                'migration' => 'initial_install',
                'description' => "Auto-migration: {$tablesCreated} tables created",
                'executed_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            error_log("PackageManager: Failed to record migration for {$pkgId}: " . $e->getMessage());
        }

        if ($result['status'] === 'running') {
            $result['status'] = 'success';
        }

        // Audit log
        $this->auditLogger->log('package_db_migration', 'sections', $sectionId, null, [
            'package_id' => $pkgId,
            'tables_created' => $tablesCreated,
            'files_executed' => count($sqlFiles),
            'status' => $result['status']
        ], $installedBy);

        return $result;
    }

    /**
     * Split a SQL file into individual statements.
     * Handles semicolons inside string literals and comments.
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if (!$inString && ($char === "'" || $char === '"')) {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            } elseif ($inString && $char === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
                $current .= $char;
                continue;
            }

            // Line comments
            if (!$inString && $char === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                $eol = strpos($sql, "\n", $i);
                if ($eol === false) break;
                $i = $eol;
                continue;
            }

            // Block comments
            if (!$inString && $char === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) break;
                $i = $end + 1;
                continue;
            }

            if (!$inString && $char === ';') {
                $stmt = trim($current);
                if (!empty($stmt)) {
                    $statements[] = $stmt;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $stmt = trim($current);
        if (!empty($stmt)) {
            $statements[] = $stmt;
        }

        return $statements;
    }

    /**
     * Ensure the package_migrations tracking table exists.
     */
    private function ensurePackageMigrationsTable(): void
    {
        try {
            $this->db->getConnection()->exec("
                CREATE TABLE IF NOT EXISTS package_migrations (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    package_id VARCHAR(255) NOT NULL,
                    migration VARCHAR(255) NOT NULL,
                    description TEXT DEFAULT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_package (package_id),
                    UNIQUE KEY uk_package_migration (package_id, migration)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Exception $e) {
            // Already exists — fine
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

    /**
     * Rebuild menu items for a package (idempotent recovery tool)
     *
     * @param string $packageId Package identifier
     * @param int $rebuiltBy User ID performing the action
     * @return array Result with success status
     */
    public function rebuildMenuItems(string $packageId, int $rebuiltBy): array
    {
        try {
            // Get installation and package data
            $installation = $this->db->fetchOne(
                "SELECT si.*, s.id as section_id, sp.package_data
                 FROM section_installations si
                 JOIN sections s ON si.section_id = s.id
                 JOIN section_packages sp ON si.package_record_id = sp.id
                 WHERE si.package_id = ?",
                [$packageId]
            );

            if (!$installation) {
                throw new Exception('Package not installed');
            }

            $sectionId = $installation['section_id'];
            $packageData = json_decode($installation['package_data'], true);

            if (!$packageData) {
                throw new Exception('Invalid package data');
            }

            // Delete existing menu items for this section
            $this->db->execute(
                "DELETE FROM section_menu_items WHERE section_id = ?",
                [$sectionId]
            );

            // Rebuild menu items using same logic as install
            $menuOrder = 1;
            $itemsCreated = 0;

            // Legacy: menu_items array
            if (!empty($packageData['menu_items'])) {
                foreach ($packageData['menu_items'] as $menuItem) {
                    $normalized = $this->normalizeMenuItem($menuItem, $menuOrder++);
                    $this->installMenuItem($sectionId, $normalized);
                    $itemsCreated++;
                }
            }

            // V2 Spec: hub_cards
            if (!empty($packageData['hub_cards'])) {
                foreach ($packageData['hub_cards'] as $card) {
                    $normalized = $this->normalizeMenuItem([
                        'label' => $card['title'] ?? 'Untitled',
                        'route' => $card['route'] ?? '#',
                        'icon' => $card['icon'] ?? 'fa-circle',
                        'required_permission' => $card['access'][0] ?? null
                    ], $menuOrder++);
                    $this->installMenuItem($sectionId, $normalized);
                    $itemsCreated++;
                }
            }

            // V2 Spec: management_sections
            if (!empty($packageData['management_sections'])) {
                foreach ($packageData['management_sections'] as $section) {
                    $normalized = $this->normalizeMenuItem([
                        'label' => $section['title'] ?? 'Untitled',
                        'route' => $section['route'] ?? '#',
                        'icon' => $section['icon'] ?? 'fa-gear',
                        'required_permission' => $section['access'][0] ?? null
                    ], $menuOrder++);
                    $this->installMenuItem($sectionId, $normalized);
                    $itemsCreated++;
                }
            }

            // Log rebuild
            $this->auditLogger->log(
                'package_menu_rebuild',
                'section_menu_items',
                $sectionId,
                null,
                [
                    'package_id' => $packageId,
                    'items_created' => $itemsCreated,
                    'rebuilt_by' => $rebuiltBy
                ],
                $rebuiltBy
            );

            return [
                'success' => true,
                'message' => "Rebuilt {$itemsCreated} menu items for package {$packageId}",
                'items_created' => $itemsCreated
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
