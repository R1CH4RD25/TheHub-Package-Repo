<?php
/**
 * Cleanup orphaned package installation data
 */

require_once __DIR__ . '/src/bootstrap.php';

use Hub\Database;
use Hub\Cache;

$db = Database::getInstance();

echo "🧹 Package Data Cleanup\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Clear all package caches
echo "1️⃣  Clearing package caches...\n";
Cache::delete('packages:installed');
Cache::delete('packages:available');
Cache::delete('packages:updates');
echo "   ✅ Cache cleared\n\n";

// Find orphaned section_installations (section doesn't exist)
echo "2️⃣  Finding orphaned installations (section deleted)...\n";
$orphanedInstalls = $db->fetchAll("
    SELECT si.* 
    FROM section_installations si
    LEFT JOIN sections s ON si.section_id = s.id
    WHERE s.id IS NULL
");

if (count($orphanedInstalls) > 0) {
    echo "   Found " . count($orphanedInstalls) . " orphaned installations:\n";
    foreach ($orphanedInstalls as $install) {
        echo "   - Installation ID {$install['id']}: package_id={$install['package_id']}, section_id={$install['section_id']} (section deleted)\n";
    }
    
    echo "\n   Deleting orphaned installations...\n";
    $db->execute("
        DELETE FROM section_installations 
        WHERE id IN (
            SELECT si.id 
            FROM section_installations si
            LEFT JOIN sections s ON si.section_id = s.id
            WHERE s.id IS NULL
        )
    ");
    echo "   ✅ Deleted " . count($orphanedInstalls) . " orphaned installations\n\n";
} else {
    echo "   ✅ No orphaned installations found\n\n";
}

// Find orphaned sections (no installation record)
echo "3️⃣  Finding orphaned sections (installed but no installation record)...\n";
$orphanedSections = $db->fetchAll("
    SELECT s.* 
    FROM sections s
    LEFT JOIN section_installations si ON s.id = si.section_id
    WHERE si.id IS NULL AND s.is_dynamic = 1
");

if (count($orphanedSections) > 0) {
    echo "   Found " . count($orphanedSections) . " orphaned sections:\n";
    foreach ($orphanedSections as $section) {
        echo "   - Section ID {$section['id']}: {$section['slug']} ({$section['display_name']})\n";
    }
    
    echo "\n   Deleting orphaned sections...\n";
    foreach ($orphanedSections as $section) {
        // Delete related data first
        $db->execute("DELETE FROM section_field_definitions WHERE section_id = ?", [$section['id']]);
        $db->execute("DELETE FROM section_role_access WHERE section_id = ?", [$section['id']]);
        $db->execute("DELETE FROM section_access WHERE section_id = ?", [$section['id']]);
        $db->execute("DELETE FROM section_administrators WHERE section_id = ?", [$section['id']]);
        $db->execute("DELETE FROM section_menu_items WHERE section_id = ?", [$section['id']]);
        $db->execute("DELETE FROM sections WHERE id = ?", [$section['id']]);
        echo "   - Deleted section ID {$section['id']}\n";
    }
    echo "   ✅ Cleaned up " . count($orphanedSections) . " orphaned sections\n\n";
} else {
    echo "   ✅ No orphaned sections found\n\n";
}

// Find duplicate installations (same package_id installed multiple times)
echo "4️⃣  Finding duplicate installations...\n";
$duplicates = $db->fetchAll("
    SELECT package_id, COUNT(*) as count
    FROM section_installations
    GROUP BY package_id
    HAVING count > 1
");

if (count($duplicates) > 0) {
    echo "   Found " . count($duplicates) . " packages with duplicate installations:\n";
    foreach ($duplicates as $dup) {
        echo "   - Package {$dup['package_id']}: {$dup['count']} installations\n";
        
        // Keep the most recent one, delete others
        $installations = $db->fetchAll("
            SELECT * FROM section_installations 
            WHERE package_id = ? 
            ORDER BY installed_at DESC
        ", [$dup['package_id']]);
        
        $keep = array_shift($installations); // Keep first (most recent)
        echo "     Keeping installation ID {$keep['id']} (most recent)\n";
        
        foreach ($installations as $old) {
            echo "     Deleting installation ID {$old['id']}\n";
            $db->execute("DELETE FROM section_installations WHERE id = ?", [$old['id']]);
        }
    }
    echo "   ✅ Cleaned up duplicates\n\n";
} else {
    echo "   ✅ No duplicate installations found\n\n";
}

// Show current state
echo "5️⃣  Current package state:\n";
$installed = $db->fetchAll("
    SELECT si.*, s.display_name, s.slug, s.is_active
    FROM section_installations si
    LEFT JOIN sections s ON si.section_id = s.id
    ORDER BY si.installed_at DESC
");

if (count($installed) > 0) {
    echo "   Currently installed packages:\n";
    foreach ($installed as $pkg) {
        $status = $pkg['display_name'] ? "✓ {$pkg['display_name']} (slug: {$pkg['slug']})" : "⚠️  SECTION MISSING";
        $active = $pkg['is_active'] ? "active" : "inactive";
        echo "   - Installation ID {$pkg['id']}: {$status} [{$active}]\n";
    }
} else {
    echo "   ✅ No packages currently installed\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Cleanup complete!\n";
