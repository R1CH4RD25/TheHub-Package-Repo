<?php

/**
 * Package Manager API
 * 
 * Handles package uploads, installations, upgrades, and uninstalls
 * 
 * Endpoints:
 * GET    /api/packages.php - List packages
 * POST   /api/packages.php - Upload package
 * DELETE /api/packages.php?id=123 - Delete uploaded package
 * 
 * POST   /api/packages.php?action=install&id=123 - Install package
 * POST   /api/packages.php?action=upgrade&id=123 - Upgrade package
 * POST   /api/packages.php?action=uninstall&package_id=xxx - Uninstall package
 * 
 * GET    /api/packages.php?action=installed - List installed packages
 * GET    /api/packages.php?action=updates - Check for updates
 * GET    /api/packages.php?action=validation&id=123 - Get validation results
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\AuditLogger;
use Hub\Database;
use Hub\PackageManager;
use Hub\PackageValidator;

// Require login
Auth::requireLogin();

// Only super admin and admin can manage packages
$currentUser = Auth::getCurrentUser();
if (!in_array($currentUser['role'], ['super_admin', 'admin'])) {
    jsonResponse(['error' => 'Insufficient permissions'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

$packageManager = new PackageManager();
$db = Database::getInstance();

// ============================================================================
// GET - List packages, installed packages, updates, validation results
// ============================================================================

if ($method === 'GET') {
    
    // Get installed packages
    if ($action === 'installed') {
        $installed = $packageManager->getInstalledPackages();
        jsonResponse(['success' => true, 'packages' => $installed]);
    }
    
    // Check for updates
    elseif ($action === 'updates') {
        $updates = $packageManager->checkForUpdates();
        jsonResponse(['success' => true, 'updates' => $updates]);
    }
    
    // Get validation results for a package
    elseif ($action === 'validation' && !empty($_GET['id'])) {
        $packageId = (int) $_GET['id'];
        
        // Get package
        $package = $db->fetchOne("SELECT * FROM section_packages WHERE id = ?", [$packageId]);
        
        if (!$package) {
            jsonResponse(['error' => 'Package not found'], 404);
        }
        
        // Get compatibility checks
        $checks = $db->fetchAll(
            "SELECT * FROM section_compatibility_checks WHERE package_record_id = ? ORDER BY check_type, check_name",
            [$packageId]
        );
        
        // Group by check type
        $grouped = [];
        foreach ($checks as $check) {
            $grouped[$check['check_type']][] = $check;
        }
        
        // Summary
        $summary = [
            'total_checks' => count($checks),
            'passed' => count(array_filter($checks, fn($c) => $c['status'] === 'pass')),
            'failed' => count(array_filter($checks, fn($c) => $c['status'] === 'fail')),
            'warnings' => count(array_filter($checks, fn($c) => $c['status'] === 'warning')),
            'critical' => count(array_filter($checks, fn($c) => $c['severity'] === 'critical'))
        ];
        
        // Mark package as validated (viewing the report completes validation)
        $canInstall = $summary['failed'] === 0 && $summary['critical'] === 0;
        $validationStatus = $canInstall ? 'pass' : 'fail';
        
        $db->execute(
            "UPDATE section_packages SET validation_status = ?, can_install = ? WHERE id = ?",
            [$validationStatus, $canInstall ? 1 : 0, $packageId]
        );
        
        jsonResponse([
            'success' => true,
            'package' => [
                'id' => $package['id'],
                'package_id' => $package['package_id'],
                'version' => $package['version'],
                'display_name' => $package['display_name'],
                'can_install' => $canInstall,
                'validation_status' => $validationStatus
            ],
            'summary' => $summary,
            'checks' => $grouped,
            'all_checks' => $checks
        ]);
    }
    
    // List all uploaded packages
    else {
        $filters = [];
        
        if (!empty($_GET['package_id'])) {
            $filters['package_id'] = $_GET['package_id'];
        }
        
        if (!empty($_GET['can_install'])) {
            $filters['can_install'] = true;
        }
        
        $packages = $packageManager->getPackages($filters);
        
        // Add installation status
        foreach ($packages as &$pkg) {
            $installed = $db->fetchOne(
                "SELECT id, installed_version, installed_at FROM section_installations WHERE package_id = ?",
                [$pkg['package_id']]
            );
            
            $pkg['is_installed'] = !empty($installed);
            $pkg['installed_version'] = $installed['installed_version'] ?? null;
            $pkg['installed_at'] = $installed['installed_at'] ?? null;
            
            if (!empty($installed)) {
                $pkg['has_update'] = version_compare($pkg['version'], $installed['installed_version'], '>');
            } else {
                $pkg['has_update'] = false;
            }
        }
        
        jsonResponse(['success' => true, 'packages' => $packages]);
    }
}

// ============================================================================
// POST - Upload, Install, Upgrade
// ============================================================================

elseif ($method === 'POST') {
    
    // Verify CSRF token for state-changing operations
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }
    
    // Run validation on a package
    if ($action === 'validate' && !empty($_GET['id'])) {
        $packageId = (int) $_GET['id'];
        
        // Get package
        $package = $db->fetchOne("SELECT * FROM section_packages WHERE id = ?", [$packageId]);
        
        if (!$package) {
            jsonResponse(['error' => 'Package not found'], 404);
        }
        
        // Decode package data
        $packageData = json_decode($package['package_data'], true);
        
        if (!$packageData) {
            jsonResponse(['error' => 'Invalid package data'], 400);
        }
        
        // Run validation
        $validator = new PackageValidator();
        $validationResult = $validator->validate($packageData);
        
        // Delete old checks
        $db->execute("DELETE FROM section_compatibility_checks WHERE package_record_id = ?", [$packageId]);
        
        // Store new compatibility checks
        foreach ($validationResult['checks'] as $check) {
            $db->execute(
                "INSERT INTO section_compatibility_checks 
                (package_record_id, check_type, check_name, required_value, actual_value, status, message, severity, resolution) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $packageId,
                    $check['check_type'] ?? 'validation',
                    $check['check_name'] ?? 'Unknown Check',
                    $check['required_value'] ?? null,
                    $check['actual_value'] ?? null,
                    $check['status'],
                    $check['message'],
                    $check['severity'] ?? 'info',
                    $check['resolution'] ?? null
                ]
            );
        }
        
        // Update package validation status
        $canInstall = $validationResult['can_install'];
        $validationStatus = $canInstall ? 'pass' : 'fail';
        
        $db->execute(
            "UPDATE section_packages SET validation_status = ?, can_install = ? WHERE id = ?",
            [$validationStatus, $canInstall ? 1 : 0, $packageId]
        );
        
        // Log validation
        $logger = new AuditLogger();
        $logger->log(
            'package_validated',
            'section_packages',
            $packageId,
            null,
            [
                'package_id' => $package['package_id'],
                'version' => $package['version'],
                'result' => $validationStatus,
                'checks_performed' => count($validationResult['checks']),
                'failures' => count(array_filter($validationResult['checks'], fn($c) => $c['status'] === 'fail'))
            ]
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Package validation complete',
            'can_install' => $canInstall,
            'validation_status' => $validationStatus,
            'checks_performed' => count($validationResult['checks'])
        ]);
    }
    
    // Install package
    elseif ($action === 'install' && !empty($_GET['id'])) {
        $packageId = (int) $_GET['id'];
        
        $result = $packageManager->installPackage($packageId, $currentUser['id']);
        
        if ($result['success']) {
            jsonResponse($result, 200);
        } else {
            jsonResponse($result, 400);
        }
    }
    
    // Upgrade package
    elseif ($action === 'upgrade' && !empty($_GET['id'])) {
        $packageId = (int) $_GET['id'];
        
        $result = $packageManager->upgradePackage($packageId, $currentUser['id']);
        
        if ($result['success']) {
            jsonResponse($result, 200);
        } else {
            jsonResponse($result, 400);
        }
    }
    
    // Uninstall package
    elseif ($action === 'uninstall' && !empty($_GET['package_id'])) {
        $packageId = $_GET['package_id'];
        $keepData = !empty($_GET['keep_data']) && $_GET['keep_data'] === '1';
        
        $result = $packageManager->uninstallPackage($packageId, $currentUser['id'], $keepData);
        
        if ($result['success']) {
            jsonResponse($result, 200);
        } else {
            jsonResponse($result, 400);
        }
    }
    
    // Enable package
    elseif ($action === 'enable' && !empty($_GET['package_id'])) {
        $packageId = $_GET['package_id'];
        
        $result = $packageManager->enableSection($packageId, $currentUser['id']);
        
        if ($result['success']) {
            jsonResponse($result, 200);
        } else {
            jsonResponse($result, 400);
        }
    }
    
    // Disable package
    elseif ($action === 'disable' && !empty($_GET['package_id'])) {
        $packageId = $_GET['package_id'];
        
        $result = $packageManager->disableSection($packageId, $currentUser['id']);
        
        if ($result['success']) {
            jsonResponse($result, 200);
        } else {
            jsonResponse($result, 400);
        }
    }
    
    // Upload package
    else {
        if (empty($_FILES['package'])) {
            jsonResponse(['error' => 'No package file uploaded'], 400);
        }
        
        $result = $packageManager->uploadPackage($_FILES['package'], $currentUser['id']);
        
        if ($result['success']) {
            jsonResponse($result, 201);
        } else {
            jsonResponse($result, 400);
        }
    }
}

// ============================================================================
// DELETE - Remove uploaded package (not uninstall)
// ============================================================================

elseif ($method === 'DELETE') {
    
    // Parse DELETE data
    parse_str(file_get_contents('php://input'), $_DELETE);
    
    if (!verifyCsrfToken($_DELETE['csrf_token'] ?? $_GET['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }
    
    if (empty($_GET['id'])) {
        jsonResponse(['error' => 'Package ID required'], 400);
    }
    
    $packageId = (int) $_GET['id'];
    
    try {
        // Get package
        $package = $db->fetchOne("SELECT * FROM section_packages WHERE id = ?", [$packageId]);
        
        if (!$package) {
            jsonResponse(['error' => 'Package not found'], 404);
        }
        
        // Check if installed
        $installed = $db->fetchOne(
            "SELECT id FROM section_installations WHERE package_record_id = ?",
            [$packageId]
        );
        
        if ($installed) {
            jsonResponse(['error' => 'Cannot delete: Package is currently installed'], 400);
        }
        
        // Delete file
        if (file_exists($package['file_path'])) {
            unlink($package['file_path']);
        }
        
        // Delete compatibility checks
        $db->execute("DELETE FROM section_compatibility_checks WHERE package_record_id = ?", [$packageId]);
        
        // Delete package record
        $db->execute("DELETE FROM section_packages WHERE id = ?", [$packageId]);
        
        jsonResponse(['success' => true, 'message' => 'Package deleted']);
        
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

// ============================================================================
// Invalid Method
// ============================================================================

else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
