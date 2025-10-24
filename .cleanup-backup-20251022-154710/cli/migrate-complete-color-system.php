#!/usr/bin/env php
<?php
/**
 * Migration Script: Add Complete Color System to All Themes
 * Adds ~45 new color settings for full customization + dark mode
 * Run: php cli/migrate-complete-color-system.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

echo "=== Complete Color System Migration ===\n\n";

// Default color palette
$defaultColors = [
    // Text colors
    'text_primary' => '#111827',
    'text_secondary' => '#374151',
    'text_muted' => '#6b7280',
    'text_disabled' => '#d1d5db',
    'text_inverse' => '#FFFFFF',
    'link_color' => '#3B82F6',
    
    // Border colors
    'border_primary' => '#e5e7eb',
    'border_secondary' => '#d1d5db',
    'border_light' => '#f3f4f6',
    'border_focus' => '#C99700', // Will use primary color by default
    
    // Background colors
    'bg_secondary' => '#f9fafb',
    'bg_hover' => '#f3f4f6',
    'bg_active' => '#e5e7eb',
    'bg_modal_overlay' => 'rgba(0,0,0,0.5)',
    'bg_code' => '#1f2937',
    
    // Status - Success
    'success_bg' => '#d4edda',
    'success_text' => '#155724',
    'success_border' => '#c3e6cb',
    'success_button' => '#10b981',
    'success_button_hover' => '#059669',
    
    // Status - Danger
    'danger_bg' => '#f8d7da',
    'danger_text' => '#721c24',
    'danger_border' => '#f5c6cb',
    'danger_button' => '#ef4444',
    'danger_button_hover' => '#dc2626',
    
    // Status - Warning
    'warning_bg' => '#fff3cd',
    'warning_text' => '#856404',
    'warning_border' => '#ffeeba',
    'warning_button' => '#f59e0b',
    'warning_button_hover' => '#d97706',
    
    // Status - Info
    'info_bg' => '#d1ecf1',
    'info_text' => '#0c5460',
    'info_border' => '#bee5eb',
    'info_button' => '#3B82F6',
    'info_button_hover' => '#2563EB',
    
    // Scrollbar
    'scrollbar_track' => '#f1f1f1',
    'scrollbar_thumb' => '#888888',
    'scrollbar_thumb_hover' => '#555555',
    
    // Shadow opacity values (0-1)
    'shadow_light' => '0.1',
    'shadow_medium' => '0.2',
    'shadow_heavy' => '0.3',
    
    // Dark mode
    'dark_mode_enabled' => '0',
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
        
        // Check if already migrated
        if (isset($settings['text_primary'])) {
            echo "✓ {$theme['name']}: Already has complete color system, skipping\n";
            continue;
        }
        
        // Theme-specific color adaptations
        $customColors = $defaultColors;
        $primaryColor = $settings['primary_color'] ?? '#C99700';
        
        // Use primary color for focus borders
        $customColors['border_focus'] = $primaryColor;
        
        // Theme-specific adaptations
        switch ($theme['slug']) {
            case 'dark-professional':
                // Dark theme
                $customColors['text_primary'] = '#F9FAFB';
                $customColors['text_secondary'] = '#D1D5DB';
                $customColors['text_muted'] = '#9CA3AF';
                $customColors['text_disabled'] = '#6B7280';
                $customColors['text_inverse'] = '#111827';
                $customColors['bg_secondary'] = '#1F2937';
                $customColors['bg_hover'] = '#374151';
                $customColors['bg_active'] = '#4B5563';
                $customColors['border_primary'] = '#374151';
                $customColors['border_secondary'] = '#4B5563';
                $customColors['scrollbar_track'] = '#1F2937';
                $customColors['scrollbar_thumb'] = '#4B5563';
                $customColors['scrollbar_thumb_hover'] = '#6B7280';
                break;
                
            case 'high-contrast':
                // High contrast theme
                $customColors['text_primary'] = '#000000';
                $customColors['border_primary'] = '#000000';
                $customColors['border_secondary'] = '#333333';
                $customColors['shadow_light'] = '0.3';
                $customColors['shadow_medium'] = '0.5';
                $customColors['shadow_heavy'] = '0.7';
                break;
                
            case 'newcastle-green':
            case 'forest-green':
                // Green themes
                $customColors['success_button'] = $primaryColor;
                $customColors['success_button_hover'] = '#00883E';
                $customColors['link_color'] = $primaryColor;
                break;
                
            case 'ocean-blue':
            case 'navy-silver':
                // Blue themes
                $customColors['link_color'] = $primaryColor;
                $customColors['info_button'] = $primaryColor;
                break;
                
            case 'cardinal-red':
            case 'maroon-gold':
                // Red themes
                $customColors['danger_button'] = $primaryColor;
                break;
                
            case 'royal-purple':
                // Purple theme
                $customColors['link_color'] = $primaryColor;
                break;
                
            case 'burnt-orange':
                // Orange theme
                $customColors['warning_button'] = $primaryColor;
                break;
        }
        
        // Merge new colors into existing settings
        $settings = array_merge($settings, $customColors);
        
        // Update theme
        $stmt = $pdo->prepare("UPDATE themes SET settings = ? WHERE id = ?");
        $stmt->execute([json_encode($settings), $theme['id']]);
        
        echo "✓ {$theme['name']}: Added complete color system\n";
        $updated++;
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Updated: $updated themes\n";
    echo "Skipped: " . (count($themes) - $updated) . " themes\n\n";
    
    echo "Color settings added:\n";
    echo "- 6 text colors\n";
    echo "- 4 border colors\n";
    echo "- 5 background colors\n";
    echo "- 15 status colors (success, danger, warning, info)\n";
    echo "- 3 scrollbar colors\n";
    echo "- 3 shadow opacity values\n";
    echo "- 1 dark mode toggle\n";
    echo "= 37 new settings (+ 12 role badges + existing = ~60 total)\n\n";
    
    echo "Next steps:\n";
    echo "1. Update SiteSettings::getCSSVariables() to output all new variables\n";
    echo "2. Replace hardcoded colors in CSS files with var() references\n";
    echo "3. Add comprehensive color picker UI to admin\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
