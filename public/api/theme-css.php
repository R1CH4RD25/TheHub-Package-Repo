<?php
/**
 * Dynamic Theme CSS Generator
 * Generates CSS from database settings
 * Must be served as text/css
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\SiteSettings;

// Set proper content type
header('Content-Type: text/css; charset=utf-8');

// Cache for 5 minutes
header('Cache-Control: public, max-age=300');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 300) . ' GMT');

// Generate CSS from site settings
echo SiteSettings::getCSSVariables();
?>
