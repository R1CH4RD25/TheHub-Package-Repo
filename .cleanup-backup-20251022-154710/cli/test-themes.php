#!/usr/bin/env php
<?php
/**
 * Test Themes System
 * Usage: php cli/test-themes.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Theme;
use Hub\SiteSettings;

echo "=== Theme Management System Test ===\n\n";

$theme = new Theme();
$settings = new SiteSettings();

// Test 1: List all themes
echo "1. Listing all themes:\n";
$themes = $theme->getAll();
echo "   Found " . count($themes) . " themes:\n";
foreach ($themes as $t) {
    $active = $t['is_active'] ? '✓ ACTIVE' : '';
    $system = $t['is_system'] ? '[SYSTEM]' : '';
    echo "   - {$t['name']} (ID: {$t['id']}) {$active} {$system}\n";
}
echo "\n";

// Test 2: Get active theme
echo "2. Getting active theme:\n";
$activeTheme = $theme->getActive();
if ($activeTheme) {
    echo "   Active: {$activeTheme['name']}\n";
    $themeSettings = $activeTheme['settings'];
    echo "   Primary Color: {$themeSettings['primary_color']}\n";
    echo "   Navbar Color: {$themeSettings['navbar_color']}\n";
} else {
    echo "   No active theme found.\n";
}
echo "\n";

// Test 3: Current site settings
echo "3. Current site settings (theme-related):\n";
$currentSettings = $settings->getAll();
$themeKeys = ['primary_color', 'navbar_color', 'background_color', 'accent_color', 'header_bg_color'];
foreach ($currentSettings as $key => $value) {
    if (in_array($key, $themeKeys)) {
        echo "   {$key}: {$value}\n";
    }
}
echo "\n";

// Test 4: Get specific theme
echo "4. Getting 'Dark Professional' theme:\n";
$darkTheme = $theme->getBySlug('dark-mode');
if ($darkTheme) {
    echo "   Found: {$darkTheme['name']}\n";
    echo "   Description: {$darkTheme['description']}\n";
    echo "   Primary Color: {$darkTheme['settings']['primary_color']}\n";
} else {
    echo "   Dark theme not found.\n";
}
echo "\n";

// Test 5: Preview generation
echo "5. Testing preview generation:\n";
if ($activeTheme) {
    $preview = $theme->generatePreview($activeTheme['settings']);
    echo "   Preview HTML generated (length: " . strlen($preview) . " chars)\n";
}
echo "\n";

echo "=== Test Complete ===\n";
echo "\nTo activate a theme manually:\n";
echo "  1. Note the theme ID from the list above\n";
echo "  2. Use the admin interface or API to activate it\n";
echo "  3. Or modify this script to call: \$theme->activate(\$themeId);\n";
