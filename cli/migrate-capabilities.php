<?php
/**
 * Capability System Migration Script
 * 
 * Creates new tables and generates default capabilities for existing packages.
 * Safe to run multiple times (uses IF NOT EXISTS and ON DUPLICATE KEY).
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

$db = Database::getInstance()->getConnection();

echo "🔧 Migrating to capability system...\n\n";

// Step 1: Add new columns and tables
echo "1. Creating new schema...\n";
try {
    $schema = file_get_contents(__DIR__ . '/../database/packages-schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $sql) {
        if (empty($sql)) continue;
        
        try {
            $db->exec($sql);
            
            // Extract table name for feedback
            if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $sql, $matches)) {
                echo "   ✅ Table: {$matches[1]}\n";
            } elseif (preg_match('/ALTER TABLE (\w+)/i', $sql, $matches)) {
                echo "   ✅ Altered: {$matches[1]}\n";
            }
        } catch (PDOException $e) {
            // Ignore "already exists" errors
            if (strpos($e->getMessage(), 'Duplicate column name') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                echo "   ⚠️  " . $e->getMessage() . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "   ❌ Schema error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "   ✅ Schema updated\n\n";

// Step 2: Generate default capabilities for existing packages
echo "2. Generating default capabilities for existing packages...\n";

$defaultCapabilities = [
    [
        'key' => 'view',
        'label' => 'View package content',
        'description' => 'Can access and view this package',
        'type' => 'read',
        'default_roles' => ['Teacher', 'Staff', 'Admin']
    ],
    [
        'key' => 'submit',
        'label' => 'Submit entries',
        'description' => 'Can create and submit new entries',
        'type' => 'action',
        'default_roles' => ['Teacher', 'Staff']
    ],
    [
        'key' => 'manage',
        'label' => 'Manage package',
        'description' => 'Can configure package settings and manage data',
        'type' => 'admin',
        'default_roles' => ['Admin']
    ]
];

try {
    $packages = $db->query("SELECT slug, name, is_active FROM sections WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($packages)) {
        echo "   ⚠️  No active packages found\n\n";
    } else {
        $stmt = $db->prepare("
            INSERT INTO package_capabilities
            (package_slug, capability_key, capability_label, capability_description, capability_type, default_roles, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                capability_label = VALUES(capability_label),
                capability_description = VALUES(capability_description)
        ");
        
        foreach ($packages as $pkg) {
            echo "   Processing: {$pkg['name']}...\n";
            
            // Insert default capabilities
            foreach ($defaultCapabilities as $i => $cap) {
                $stmt->execute([
                    $pkg['slug'],
                    $cap['key'],
                    $cap['label'],
                    $cap['description'],
                    $cap['type'],
                    json_encode($cap['default_roles']),
                    $i
                ]);
            }
            
            // Mark package as supporting capabilities
            $updateStmt = $db->prepare("UPDATE sections SET supports_capabilities = TRUE WHERE slug = ?");
            $updateStmt->execute([$pkg['slug']]);
            
            echo "      ✅ Added 3 default capabilities\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Capability generation error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Migration complete!\n";
echo "📋 Next steps:\n";
echo "   1. Review generated capabilities in Package Management\n";
echo "   2. Customize capabilities per package as needed\n";
echo "   3. Apply smart defaults to roles (optional)\n\n";
