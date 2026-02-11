#!/usr/bin/env php
<?php
/**
 * Layer 1/2 Package Cleanup Script
 * 
 * Removes all existing Layer 1 and Layer 2 packages to prepare for Layer 3 implementation.
 * See PACKAGE_CLEANUP_PLAN.md for full details.
 * 
 * Usage: php cli/cleanup-layer1-layer2-packages.php
 */

require __DIR__ . '/../src/bootstrap.php';

use Hub\Database;
use Hub\AuditLogger;

$db = Database::getInstance();

echo "=== LAYER 1/2 PACKAGE CLEANUP ===\n\n";
echo "Authority: PACKAGE_ARCHITECTURE_SPEC.md v1.0.0-draft\n";
echo "Purpose: Remove incompatible packages before Layer 3 implementation\n\n";

// Get packages to remove
$packages = $db->query('SELECT package_id, version FROM section_packages')->fetchAll(PDO::FETCH_ASSOC);

if (empty($packages)) {
    echo "No packages found. System already clean.\n";
    exit(0);
}

echo "Packages found: " . count($packages) . "\n\n";
foreach ($packages as $pkg) {
    echo "  • {$pkg['package_id']} v{$pkg['version']}\n";
}

echo "\n⚠️  WARNING ⚠️\n";
echo "This will PERMANENTLY REMOVE all packages and their data.\n";
echo "Backups should be created first (see PACKAGE_CLEANUP_PLAN.md).\n\n";

// Safety confirmation
echo "Type 'YES' to confirm cleanup: ";
$confirm = trim(fgets(STDIN));

if ($confirm !== 'YES') {
    echo "\n❌ Cleanup aborted by user.\n";
    exit(1);
}

echo "\n🚀 Starting cleanup...\n\n";

try {
    // Begin transaction for safety
    $db->query('START TRANSACTION');
    
    // Step 1: Remove package installations
    echo "[1/7] Removing package installations...\n";
    $installCount = $db->query('SELECT COUNT(*) as c FROM section_installations')->fetch()['c'];
    $db->query('DELETE FROM section_installations');
    echo "  ✓ Removed $installCount installation records\n\n";
    
    // Step 2: Remove package definitions
    echo "[2/7] Removing package definitions...\n";
    $packageCount = $db->query('SELECT COUNT(*) as c FROM section_packages')->fetch()['c'];
    $db->query('DELETE FROM section_packages');
    echo "  ✓ Removed $packageCount package definitions\n\n";
    
    // Step 3: Get package sections
    echo "[3/7] Finding package-created sections...\n";
    $packageSections = $db->query('
        SELECT id, name, slug 
        FROM sections 
        WHERE slug LIKE "com.%" OR slug LIKE "%.%"
    ')->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  • Found " . count($packageSections) . " package sections\n";
    foreach ($packageSections as $section) {
        echo "    - {$section['slug']}: {$section['name']}\n";
    }
    echo "\n";
    
    // Step 4: Remove field definitions for package sections
    echo "[4/7] Removing field definitions...\n";
    if (!empty($packageSections)) {
        $sectionIds = array_column($packageSections, 'id');
        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
        $fieldCount = $db->query(
            "SELECT COUNT(*) as c FROM section_field_definitions WHERE section_id IN ($placeholders)",
            $sectionIds
        )->fetch()['c'];
        
        $db->query(
            "DELETE FROM section_field_definitions WHERE section_id IN ($placeholders)",
            $sectionIds
        );
        echo "  ✓ Removed $fieldCount field definitions\n\n";
    } else {
        echo "  • No fields to remove\n\n";
    }
    
    // Step 5: Remove section role access
    echo "[5/7] Removing section role access...\n";
    if (!empty($packageSections)) {
        $sectionIds = array_column($packageSections, 'id');
        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
        $accessCount = $db->query(
            "SELECT COUNT(*) as c FROM section_role_access WHERE section_id IN ($placeholders)",
            $sectionIds
        )->fetch()['c'];
        
        $db->query(
            "DELETE FROM section_role_access WHERE section_id IN ($placeholders)",
            $sectionIds
        );
        echo "  ✓ Removed $accessCount role access records\n\n";
    } else {
        echo "  • No role access to remove\n\n";
    }
    
    // Step 6: Remove sections
    echo "[6/7] Removing package sections...\n";
    $db->query('DELETE FROM sections WHERE slug LIKE "com.%" OR slug LIKE "%.%"');
    echo "  ✓ Removed " . count($packageSections) . " sections\n\n";
    
    // Step 7: Drop package-specific tables
    echo "[7/7] Dropping package-specific database tables...\n";
    
    $tablesToDrop = [
        'vm_fuel_logs',
        'vm_maintenance_events',
        'student_password_reveals' // If any test data exists
    ];
    
    foreach ($tablesToDrop as $table) {
        try {
            $db->query("DROP TABLE IF EXISTS $table");
            echo "  ✓ Dropped $table\n";
        } catch (Exception $e) {
            echo "  - $table not found (OK)\n";
        }
    }
    
    echo "\n";
    
    // Commit transaction
    $db->query('COMMIT');
    
    // Audit the cleanup
    AuditLogger::log('system.packages_cleanup', [
        'packages_removed' => array_column($packages, 'package_id'),
        'package_count' => count($packages),
        'sections_removed' => count($packageSections),
        'reason' => 'Layer 3 migration - removing Layer 1/2 packages',
        'spec_version' => '1.0.0-draft',
        'actor_id' => 1, // System
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    echo "✅ CLEANUP COMPLETE\n\n";
    
    // Verification
    echo "=== VERIFICATION ===\n\n";
    $checks = [
        'section_packages' => $db->query('SELECT COUNT(*) as c FROM section_packages')->fetch()['c'],
        'section_installations' => $db->query('SELECT COUNT(*) as c FROM section_installations')->fetch()['c'],
        'package_sections' => $db->query('SELECT COUNT(*) as c FROM sections WHERE slug LIKE "com.%"')->fetch()['c']
    ];
    
    $allClean = true;
    foreach ($checks as $table => $count) {
        if ($count > 0) {
            echo "❌ $table: $count remaining (should be 0)\n";
            $allClean = false;
        } else {
            echo "✅ $table: 0 records\n";
        }
    }
    
    echo "\n";
    
    if ($allClean) {
        echo "🎉 CLEAN SLATE ACHIEVED\n\n";
        echo "Next steps:\n";
        echo "1. Review PACKAGE_ARCHITECTURE_SPEC.md\n";
        echo "2. Start Sprint 0: Platform Contract implementation\n";
        echo "3. Build Student Directory as Layer 3 pilot\n";
    } else {
        echo "⚠️  CLEANUP INCOMPLETE - Manual intervention required\n";
        exit(1);
    }
    
} catch (Exception $e) {
    // Rollback on error
    $db->query('ROLLBACK');
    
    echo "\n❌ ERROR DURING CLEANUP\n\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Location: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Transaction rolled back. No changes made.\n";
    exit(1);
}
