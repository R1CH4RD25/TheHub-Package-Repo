#!/usr/bin/env php
<?php
/**
 * Package Backup Script
 * 
 * Creates safety backups of all package data before cleanup.
 * See PACKAGE_CLEANUP_PLAN.md for usage context.
 * 
 * Usage: php cli/backup-packages.php
 */

require __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

$db = Database::getInstance();

echo "=== PACKAGE BACKUP UTILITY ===\n\n";

$backupDir = '/tmp/hub-package-backups-' . date('Y-m-d-His');
mkdir($backupDir, 0755, true);

echo "Backup directory: $backupDir\n\n";

// Step 1: Backup package JSON
echo "[1/4] Backing up package definitions (JSON)...\n";
$packages = $db->query('SELECT package_id, version, package_data FROM section_packages')->fetchAll(PDO::FETCH_ASSOC);

if (empty($packages)) {
    echo "  • No packages to backup\n\n";
} else {
    foreach ($packages as $pkg) {
        $filename = "{$pkg['package_id']}_v{$pkg['version']}.json";
        $filepath = "$backupDir/$filename";
        file_put_contents($filepath, $pkg['package_data']);
        echo "  ✓ Saved $filename\n";
    }
    echo "  • Total: " . count($packages) . " packages backed up\n\n";
}

// Step 2: Backup database tables as SQL
echo "[2/4] Backing up package database tables (SQL)...\n";

$tablesToBackup = [
    'section_packages',
    'section_installations',
    'section_field_definitions',
    'sections',
    'section_role_access'
];

// Check for package-specific tables
$tables = $db->query("SHOW TABLES LIKE 'vm_%'")->fetchAll(PDO::FETCH_COLUMN);
$tablesToBackup = array_merge($tablesToBackup, $tables);

$dbName = getenv('DB_DATABASE') ?: 'woodson_hub';
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dbHost = getenv('DB_HOST') ?: 'localhost';

foreach ($tablesToBackup as $table) {
    $filename = "$backupDir/{$table}.sql";
    $command = sprintf(
        'mysqldump -h %s -u %s %s %s %s > %s 2>&1',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        !empty($dbPass) ? '-p' . escapeshellarg($dbPass) : '',
        escapeshellarg($dbName),
        escapeshellarg($table),
        escapeshellarg($filename)
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($filename) && filesize($filename) > 0) {
        $size = filesize($filename);
        echo "  ✓ Backed up $table (" . number_format($size) . " bytes)\n";
    } else {
        echo "  - $table not found or empty (OK)\n";
    }
}
echo "\n";

// Step 3: Export metadata summary
echo "[3/4] Creating backup metadata...\n";

$metadata = [
    'backup_date' => date('Y-m-d H:i:s'),
    'hub_version' => '3.0.0-dev',
    'spec_version' => '1.0.0-draft',
    'packages' => [],
    'database_tables' => $tablesToBackup,
    'reason' => 'Pre-cleanup backup for Layer 3 migration'
];

foreach ($packages as $pkg) {
    $metadata['packages'][] = [
        'package_id' => $pkg['package_id'],
        'version' => $pkg['version'],
        'file' => "{$pkg['package_id']}_v{$pkg['version']}.json"
    ];
}

file_put_contents("$backupDir/backup-metadata.json", json_encode($metadata, JSON_PRETTY_PRINT));
echo "  ✓ Created backup-metadata.json\n\n";

// Step 4: Create restoration instructions
echo "[4/4] Creating restoration instructions...\n";

$restoreInstructions = <<<'RESTORE'
# Package Backup Restoration Instructions

This backup was created before Layer 1/2 package cleanup.

## Files Included
- *.json: Package definition files (original JSON)
- *.sql: Database table dumps
- backup-metadata.json: Backup summary

## To Restore (If Needed)

### Restore Database Tables
```bash
# Navigate to backup directory
cd {BACKUP_DIR}

# Restore each table
for file in *.sql; do
    mysql -u root -p woodson_hub < "$file"
done
```

### Restore Package Definitions
```bash
# Use Hub's package import utility (if exists)
php cli/import-package.php {BACKUP_DIR}/com.woodson.vehicle-maintenance_v2.1.0.json

# Or manually insert into database
mysql -u root -p woodson_hub <<EOF
INSERT INTO section_packages (package_id, version, package_data, created_at, updated_at)
VALUES (
    'com.woodson.vehicle-maintenance',
    '2.1.0',
    LOAD_FILE('{BACKUP_DIR}/com.woodson.vehicle-maintenance_v2.1.0.json'),
    NOW(),
    NOW()
);
EOF
```

## Notes
- Layer 1/2 packages are NOT compatible with Layer 3 runtime
- Restoration only useful for data recovery, not production use
- Rebuild packages using Layer 3 spec after restoration

RESTORE;

$restoreInstructions = str_replace('{BACKUP_DIR}', $backupDir, $restoreInstructions);
file_put_contents("$backupDir/RESTORE.md", $restoreInstructions);
echo "  ✓ Created RESTORE.md\n\n";

// Summary
echo "✅ BACKUP COMPLETE\n\n";
echo "Backup location: $backupDir\n";
echo "Packages backed up: " . count($packages) . "\n";
echo "Database tables: " . count($tablesToBackup) . "\n\n";

echo "Files created:\n";
$files = scandir($backupDir);
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $size = filesize("$backupDir/$file");
    echo "  • $file (" . number_format($size) . " bytes)\n";
}

echo "\n";
echo "Next step: Run cleanup script\n";
echo "  php cli/cleanup-layer1-layer2-packages.php\n";
