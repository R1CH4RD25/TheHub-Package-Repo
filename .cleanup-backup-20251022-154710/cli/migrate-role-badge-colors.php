#!/usr/bin/env php
<?php
/**
 * Migration Script: Add Role Badge Colors to All Themes
 * Run: php cli/migrate-role-badge-colors.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

echo "=== Role Badge Colors Migration ===\n\n";

// Default role badge colors (matching current admin.css)
$defaultColors = [
    'role_staff_bg' => '#e0e7ff',
    'role_staff_text' => '#3730a3',
    'role_maintenance_bg' => '#fef3c7',
    'role_maintenance_text' => '#92400e',
    'role_maintenance_director_bg' => '#fed7aa',
    'role_maintenance_director_text' => '#9a3412',
    'role_manager_bg' => '#ddd6fe',
    'role_manager_text' => '#5b21b6',
    'role_admin_bg' => '#fce7f3',
    'role_admin_text' => '#9f1239',
    'role_super_admin_bg' => '#fee2e2',
    'role_super_admin_text' => '#991b1b',
];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Get all themes
    $stmt = $pdo->query("SELECT id, name, slug, settings FROM themes ORDER BY id");
    $themes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($themes) . " themes to update\n\n";
    
    $updated = 0;
    
    foreach ($themes as $theme) {
        $settings = json_decode($theme['settings'], true);
        
        // Check if role badge colors already exist
        if (isset($settings['role_staff_bg'])) {
            echo "✓ {$theme['name']}: Already has role badge colors, skipping\n";
            continue;
        }
        
        // Theme-specific adaptations based on primary color
        $customColors = $defaultColors;
        
        // For themes with specific color schemes, adapt role badges
        $primaryColor = $settings['primary_color'] ?? '#C99700';
        
        switch ($theme['slug']) {
            case 'newcastle-green':
            case 'forest-green':
                // Green themes: Use green-tinted badges
                $customColors['role_staff_bg'] = '#E8F5E9';
                $customColors['role_staff_text'] = '#1B5E20';
                $customColors['role_maintenance_bg'] = '#C8E6C9';
                $customColors['role_maintenance_text'] = '#2E7D32';
                break;
                
            case 'cardinal-red':
            case 'maroon-gold':
                // Red themes: Use red-tinted badges
                $customColors['role_admin_bg'] = '#FFEBEE';
                $customColors['role_admin_text'] = '#B71C1C';
                $customColors['role_super_admin_bg'] = '#FFCDD2';
                $customColors['role_super_admin_text'] = '#C62828';
                break;
                
            case 'royal-purple':
                // Purple theme: Use purple-tinted badges
                $customColors['role_manager_bg'] = '#F3E5F5';
                $customColors['role_manager_text'] = '#6A1B9A';
                $customColors['role_admin_bg'] = '#E1BEE7';
                $customColors['role_admin_text'] = '#7B1FA2';
                break;
                
            case 'dark-professional':
            case 'high-contrast':
                // Dark/high contrast themes: More vivid badges for visibility
                $customColors['role_staff_bg'] = '#dbeafe';
                $customColors['role_staff_text'] = '#1e40af';
                break;
        }
        
        // Merge role badge colors into existing settings
        $settings = array_merge($settings, $customColors);
        
        // Update theme
        $stmt = $pdo->prepare("UPDATE themes SET settings = ? WHERE id = ?");
        $stmt->execute([json_encode($settings), $theme['id']]);
        
        echo "✓ {$theme['name']}: Added role badge colors\n";
        $updated++;
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Updated: $updated themes\n";
    echo "Skipped: " . (count($themes) - $updated) . " themes\n\n";
    
    echo "Next steps:\n";
    echo "1. Update src/SiteSettings.php to output role badge CSS variables\n";
    echo "2. Update admin.css to use CSS variables instead of hardcoded colors\n";
    echo "3. Add role badge color pickers to admin UI\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
