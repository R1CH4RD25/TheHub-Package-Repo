#!/usr/bin/env php
<?php

/**
 * Package Permission System Migration
 * 
 * Applies the new package permission schema that supports:
 * - Organization roles
 * - Package roles with section-aware permissions
 * - Role mappings
 * - Hub items and Management tabs
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

$db = Database::getInstance()->getConnection();

echo "\n🔧 Package Permission System Migration\n";
echo "=====================================\n\n";

// Read schema file
$schemaFile = __DIR__ . '/../database/package-permissions-schema.sql';
if (!file_exists($schemaFile)) {
    echo "❌ Schema file not found: {$schemaFile}\n";
    exit(1);
}

$schema = file_get_contents($schemaFile);

// Split into individual statements
$statements = array_filter(
    preg_split('/;\s*$/m', $schema),
    fn($stmt) => !empty(trim($stmt)) && !preg_match('/^--/', trim($stmt))
);

echo "📋 Found " . count($statements) . " SQL statements\n\n";

$success = 0;
$failed = 0;

foreach ($statements as $i => $statement) {
    $stmt = trim($statement);
    
    // Skip comments and empty statements
    if (empty($stmt) || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) {
        continue;
    }

    // Extract table name for display
    $tableName = 'unknown';
    if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $stmt, $matches)) {
        $tableName = $matches[1];
        echo "📦 Creating table: {$tableName}... ";
    } elseif (preg_match('/INSERT INTO `?(\w+)`?/i', $stmt, $matches)) {
        $tableName = $matches[1];
        echo "📝 Inserting data into: {$tableName}... ";
    } else {
        echo "⚙️  Executing statement " . ($i + 1) . "... ";
    }

    try {
        $db->exec($stmt);
        echo "✅\n";
        $success++;
    } catch (PDOException $e) {
        // If table already exists, that's okay
        if (str_contains($e->getMessage(), 'already exists') || str_contains($e->getMessage(), 'Duplicate')) {
            echo "⚠️  (already exists)\n";
            $success++;
        } else {
            echo "❌\n";
            echo "   Error: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

echo "\n=====================================\n";
echo "✅ Success: {$success}\n";
if ($failed > 0) {
    echo "❌ Failed: {$failed}\n";
}
echo "\n";

// Verify tables were created
echo "🔍 Verifying tables...\n\n";

$requiredTables = [
    'org_roles',
    'user_org_roles',
    'package_roles',
    'package_role_mappings',
    'package_sections',
    'package_hub_items',
    'package_management_tabs'
];

$allTablesExist = true;
foreach ($requiredTables as $table) {
    $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->rowCount() > 0) {
        echo "✅ {$table}\n";
    } else {
        echo "❌ {$table} - NOT FOUND\n";
        $allTablesExist = false;
    }
}

echo "\n";

if ($allTablesExist) {
    echo "🎉 Migration completed successfully!\n\n";
    echo "Next steps:\n";
    echo "1. Update package manifests to include role definitions\n";
    echo "2. Create organization roles via Admin Dashboard\n";
    echo "3. Map org roles to package roles during package configuration\n";
    echo "4. Update navigation to show Hub/Management based on permissions\n\n";
    exit(0);
} else {
    echo "⚠️  Some tables were not created. Please check errors above.\n\n";
    exit(1);
}
