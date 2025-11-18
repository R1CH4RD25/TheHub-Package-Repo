<?php
/**
 * Dynamic Section Router
 * Handles rendering of package-based sections in SPA style
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\Layout;
use Hub\Module;
use Hub\SectionRoleAccess;

// Require login
Auth::requireLogin();
$user = Auth::getCurrentUser();

// Extract section slug from URL path
// URL format: /modules/sections/{slug}/
$path = $_SERVER['REQUEST_URI'];
preg_match('#/modules/sections/([a-z0-9-]+)/?#', $path, $matches);
$slug = $matches[1] ?? null;

if (!$slug) {
    http_response_code(404);
    die('Section not found');
}

// Get section from database
$db = Database::getInstance();
$section = $db->query(
    'SELECT * FROM sections WHERE slug = ? AND is_active = 1',
    [$slug]
)->fetch();

if (!$section) {
    http_response_code(404);
    die('Section not found');
}

// Check if user has access
if (!SectionRoleAccess::hasAccess($user['id'], $slug)) {
    http_response_code(403);
    die('Access denied');
}

// Check if this is a dynamic (package-based) section
if (!$section['is_dynamic']) {
    // For non-dynamic sections, redirect to the base_url
    header('Location: ' . $section['base_url']);
    exit;
}

// Find the package entry point
$packagePath = __DIR__ . '/../../../packages/' . $slug;
$indexFile = $packagePath . '/index.php';

if (!file_exists($indexFile)) {
    http_response_code(500);
    die('Package not found: ' . $slug);
}

// Determine if this is an AJAX/SPA request
$isSpaRequest = isset($_SERVER['HTTP_X_SPA_REQUEST']) || 
                isset($_SERVER['HTTP_X_REQUESTED_WITH']);

// Start output buffering to capture package content
ob_start();

// Include the package (it will render its content)
require $indexFile;

// Get the captured output
$packageContent = ob_get_clean();

// If SPA request, wrap in minimal layout for content extraction
if ($isSpaRequest) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo e($section['display_name']); ?> - The Hub</title>
</head>
<body>
    <div class="main-content">
        <?php echo $packageContent; ?>
    </div>
</body>
</html>
    <?php
} else {
    // Full page render with header/footer
    $siteName = $_ENV['SITE_NAME'] ?? 'The Hub';
    Layout::renderHead($section['display_name'] . ' - ' . $siteName, 'section');
    Layout::renderHeader($user, 'section', $section);
    ?>
    <div class="main-content">
        <?php echo $packageContent; ?>
    </div>
    <?php
    include __DIR__ . '/../../partials/footer.php';
}
