#!/usr/bin/env php
<?php
/**
 * Re-run validation for existing packages and store checks
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\PackageManager;
use Hub\PackageValidator;
use Hub\Database;

$db = Database::getInstance();
$validator = new PackageValidator();

echo "Re-running validation for existing packages...\n\n";

// Get all packages
$packages = $db->fetchAll("SELECT * FROM section_packages");

foreach ($packages as $pkg) {
    echo "Package: {$pkg['display_name']} v{$pkg['version']}\n";
    
    // Delete old checks
    $db->execute("DELETE FROM section_compatibility_checks WHERE package_record_id = ?", [$pkg['id']]);
    
    // Parse package data
    $packageData = json_decode($pkg['package_data'], true);
    
    // Run validation
    $validation = $validator->validate($packageData, 'new');
    
    // Store checks
    foreach ($validation['checks'] as $check) {
        $db->execute("
            INSERT INTO section_compatibility_checks 
            (package_record_id, check_type, check_name, required_value, actual_value, status, severity, message, resolution, checked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $pkg['id'],
            $check['check_type'],
            $check['check_name'],
            $check['required_value'] ?? null,
            $check['actual_value'] ?? null,
            $check['status'],
            $check['severity'],
            $check['message'] ?? null,
            $check['resolution'] ?? null,
            $check['checked_at'] ?? date('Y-m-d H:i:s')
        ]);
    }
    
    echo "  ✅ Stored {$validation['summary']['total_checks']} compatibility checks\n";
    echo "  Status: {$validation['overall_status']} | Passed: {$validation['summary']['passed']} | Failed: {$validation['summary']['failed']}\n\n";
}

echo "✅ Validation complete!\n";
