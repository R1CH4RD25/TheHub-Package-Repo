#!/usr/bin/env php
<?php

/**
 * Cleanup Orphaned Sections
 * 
 * Removes inactive sections that have no associated installation records
 * Useful for cleaning up failed package installations
 * 
 * Usage: php cli/cleanup-orphaned-sections.php [--dry-run] [--slug=section-slug]
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;
use Hub\AuditLogger;

$db = Database::getInstance();
$dryRun = in_array('--dry-run', $argv);
$specificSlug = null;

// Check for --slug parameter
foreach ($argv as $arg) {
    if (strpos($arg, '--slug=') === 0) {
        $specificSlug = substr($arg, 7);
    }
}

echo "🔍 Scanning for orphaned sections...\n\n";

// Find sections that are:
// 1. Inactive (is_active = 0)
// 2. Have no installation record in section_installations
$query = "
    SELECT s.id, s.slug, s.display_name, s.is_active, s.is_dynamic, s.created_at
    FROM sections s
    LEFT JOIN section_installations si ON s.id = si.section_id
    WHERE s.is_active = 0 
    AND si.id IS NULL
";

if ($specificSlug) {
    $query .= " AND s.slug = ?";
    $orphaned = $db->fetchAll($query, [$specificSlug]);
} else {
    $orphaned = $db->fetchAll($query);
}

if (empty($orphaned)) {
    echo "✅ No orphaned sections found!\n";
    exit(0);
}

echo "Found " . count($orphaned) . " orphaned section(s):\n\n";

foreach ($orphaned as $section) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  ID:           {$section['id']}\n";
    echo "  Slug:         {$section['slug']}\n";
    echo "  Display Name: {$section['display_name']}\n";
    echo "  Active:       " . ($section['is_active'] ? 'Yes' : 'No') . "\n";
    echo "  Dynamic:      " . ($section['is_dynamic'] ? 'Yes' : 'No') . "\n";
    echo "  Created:      {$section['created_at']}\n";
    
    // Check for related data (use table_exists checks for safety)
    $fieldCount = 0;
    $dataCount = 0;
    $accessCount = 0;
    
    try {
        $fieldCount = $db->fetchOne("SELECT COUNT(*) as count FROM section_fields WHERE section_id = ?", [$section['id']])['count'];
    } catch (Exception $e) {
        // Table might not exist in this database
    }
    
    try {
        $dataCount = $db->fetchOne("SELECT COUNT(*) as count FROM section_data WHERE section_id = ?", [$section['id']])['count'];
    } catch (Exception $e) {
        // Table might not exist
    }
    
    try {
        $accessCount = $db->fetchOne("SELECT COUNT(*) as count FROM section_role_access WHERE section_id = ?", [$section['id']])['count'];
    } catch (Exception $e) {
        // Table might not exist
    }
    
    echo "  Fields:       {$fieldCount}\n";
    echo "  Data Records: {$dataCount}\n";
    echo "  Access Rules: {$accessCount}\n";
    echo "\n";
    
    if (!$dryRun) {
        echo "  🗑️  Deleting section and related data...\n";
        
        try {
            $db->beginTransaction();
            
            // Delete related data (with safety checks)
            try { $db->execute("DELETE FROM section_fields WHERE section_id = ?", [$section['id']]); } catch (Exception $e) {}
            try { $db->execute("DELETE FROM section_data WHERE section_id = ?", [$section['id']]); } catch (Exception $e) {}
            try { $db->execute("DELETE FROM section_role_access WHERE section_id = ?", [$section['id']]); } catch (Exception $e) {}
            try { $db->execute("DELETE FROM section_menu_items WHERE section_id = ?", [$section['id']]); } catch (Exception $e) {}
            
            // Delete the section
            $db->execute("DELETE FROM sections WHERE id = ?", [$section['id']]);
            
            $db->commit();
            
            // Log the cleanup
            AuditLogger::log(
                'section_orphan_cleanup',
                'sections',
                $section['id'],
                [
                    'slug' => $section['slug'],
                    'display_name' => $section['display_name'],
                    'fields_deleted' => $fieldCount,
                    'data_deleted' => $dataCount,
                    'access_rules_deleted' => $accessCount
                ],
                null,
                1 // System user
            );
            
            echo "  ✅ Deleted successfully!\n";
            
        } catch (Exception $e) {
            $db->rollback();
            echo "  ❌ Error deleting: {$e->getMessage()}\n";
        }
    } else {
        echo "  ⚠️  Would delete (dry-run mode)\n";
    }
    
    echo "\n";
}

if ($dryRun) {
    echo "\n📋 This was a dry run. No changes were made.\n";
    echo "Run without --dry-run to actually delete these sections.\n";
} else {
    echo "\n✅ Cleanup complete!\n";
}
