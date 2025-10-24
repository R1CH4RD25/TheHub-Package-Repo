#!/usr/bin/env php
<?php
/**
 * Add Success and System badge color settings to all themes
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

$db = Database::getInstance();

try {
    // Get all themes
    $stmt = $db->query("SELECT id, name, settings FROM themes WHERE is_active = 1");
    $themes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = 0;
    $skipped = 0;
    
    foreach ($themes as $theme) {
        $settings = json_decode($theme['settings'], true) ?? [];
        
        // Skip if already has these settings
        if (isset($settings['badge_success_bg']) && isset($settings['badge_system_bg'])) {
            $skipped++;
            continue;
        }
        
        // Add Success Badge colors (green)
        $settings['badge_success_bg'] = '#059669';
        $settings['badge_success_text'] = '#FFFFFF';
        
        // Add System Badge colors (indigo)
        $settings['badge_system_bg'] = '#6366F1';
        $settings['badge_system_text'] = '#FFFFFF';
        
        // Update theme
        $pdo = $db->getConnection();
        $updateStmt = $pdo->prepare("UPDATE themes SET settings = :settings WHERE id = :id");
        $updateStmt->execute([
            ':settings' => json_encode($settings, JSON_PRETTY_PRINT),
            ':id' => $theme['id']
        ]);
        
        $updated++;
        echo "Updated: {$theme['name']}\n";
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Updated: {$updated} themes\n";
    echo "Skipped: {$skipped} themes\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
