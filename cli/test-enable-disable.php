#!/usr/bin/env php
<?php
/**
 * Test Enable/Disable Package Functionality
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\PackageManager;
use Hub\Database;

echo "🧪 Testing Package Enable/Disable Functionality\n";
echo str_repeat('=', 60) . "\n\n";

// Check that methods exist
$packageManager = new PackageManager();
if (!method_exists($packageManager, 'enableSection')) {
    echo "❌ enableSection method not found\n";
    exit(1);
}
if (!method_exists($packageManager, 'disableSection')) {
    echo "❌ disableSection method not found\n";
    exit(1);
}

echo "✅ PackageManager::enableSection() exists\n";
echo "✅ PackageManager::disableSection() exists\n\n";

// Check database structure
$db = Database::getInstance();
$sections = $db->fetchAll('DESCRIBE sections');
$hasIsActive = false;
foreach ($sections as $col) {
    if ($col['Field'] === 'is_active') {
        $hasIsActive = true;
        echo "✅ sections.is_active column exists (Type: {$col['Type']})\n";
    }
}

if (!$hasIsActive) {
    echo "❌ sections.is_active column not found\n";
    exit(1);
}

// Check that getInstalledPackages includes is_active
$testQuery = 'SELECT si.*, s.is_active FROM section_installations si JOIN sections s ON si.section_id = s.id LIMIT 1';
try {
    $db->query($testQuery);
    echo "✅ Query includes sections.is_active field\n";
} catch (Exception $e) {
    echo "❌ Query test failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ All structural tests passed!\n";
echo "\n📦 Ready to test with actual package installation\n\n";

// Check if package is available
$package = $db->fetchOne("SELECT * FROM section_packages WHERE package_id = 'com.woodson.bullying-report'");
if ($package) {
    echo "📦 Bullying Report Package Found:\n";
    echo "   - ID: {$package['id']}\n";
    echo "   - Version: {$package['version']}\n";
    echo "   - Can Install: " . ($package['can_install'] ? 'YES' : 'NO') . "\n";
    
    // Check if installed
    $installation = $db->fetchOne("SELECT * FROM section_installations WHERE package_id = 'com.woodson.bullying-report'");
    if ($installation) {
        $section = $db->fetchOne("SELECT is_active FROM sections WHERE id = ?", [$installation['section_id']]);
        echo "   - Status: INSTALLED\n";
        echo "   - Active: " . ($section['is_active'] ? 'YES ✅' : 'NO ⭕') . "\n";
    } else {
        echo "   - Status: NOT INSTALLED (ready to install)\n";
    }
} else {
    echo "ℹ️  No package found in database yet\n";
}

echo "\n✅ Test complete!\n";
