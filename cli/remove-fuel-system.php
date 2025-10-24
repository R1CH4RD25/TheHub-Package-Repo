#!/usr/bin/env php
<?php
/**
 * Remove Fuel and Maintenance Tracking System
 * 
 * This script removes the legacy fuel tracking tables and views from the database.
 * Run this to complete the removal of the fuel/maintenance tracking system.
 * 
 * Usage: php cli/remove-fuel-system.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

echo "===========================================\n";
echo "  Remove Fuel & Maintenance System\n";
echo "===========================================\n\n";

try {
    $db = Database::getInstance();
    
    echo "This will remove the following:\n";
    echo "  - fuel_records table\n";
    echo "  - vehicles table\n";
    echo "  - trip_purposes table\n";
    echo "  - fuel_records_view\n\n";
    
    // Check for data
    $vehiclesCount = $db->fetchOne("SELECT COUNT(*) as count FROM vehicles")['count'];
    $fuelCount = $db->fetchOne("SELECT COUNT(*) as count FROM fuel_records")['count'];
    $purposesCount = $db->fetchOne("SELECT COUNT(*) as count FROM trip_purposes")['count'];
    
    echo "Current data:\n";
    echo "  Vehicles: $vehiclesCount records\n";
    echo "  Fuel Records: $fuelCount records\n";
    echo "  Trip Purposes: $purposesCount records\n\n";
    
    if ($vehiclesCount > 0 || $fuelCount > 0) {
        echo "⚠️  WARNING: Tables contain data!\n";
        echo "Are you sure you want to proceed? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim(strtolower($line)) !== 'yes') {
            echo "Aborted.\n";
            exit(0);
        }
        fclose($handle);
    } else {
        echo "✓ Tables are empty - safe to remove\n\n";
    }
    
    echo "Starting removal...\n\n";
    
    // Drop the view first (depends on tables)
    echo "1. Dropping fuel_records_view...\n";
    $db->execute("DROP VIEW IF EXISTS fuel_records_view");
    echo "   ✓ View dropped\n\n";
    
    // Drop fuel_records table (has foreign keys to vehicles and trip_purposes)
    echo "2. Dropping fuel_records table...\n";
    $db->execute("DROP TABLE IF EXISTS fuel_records");
    echo "   ✓ Table dropped\n\n";
    
    // Drop vehicles table
    echo "3. Dropping vehicles table...\n";
    $db->execute("DROP TABLE IF EXISTS vehicles");
    echo "   ✓ Table dropped\n\n";
    
    // Drop trip_purposes table
    echo "4. Dropping trip_purposes table...\n";
    $db->execute("DROP TABLE IF EXISTS trip_purposes");
    echo "   ✓ Table dropped\n\n";
    
    echo "===========================================\n";
    echo "✅ SUCCESS!\n";
    echo "===========================================\n\n";
    echo "Fuel and maintenance tracking system has been removed.\n";
    echo "Next steps:\n";
    echo "  1. Remove PHP files (fuel-entry.php, api/fuel-records.php, etc.)\n";
    echo "  2. Update admin dashboard UI\n";
    echo "  3. Update Auth.php and Roles.php\n";
    echo "  4. Test admin dashboard\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
