<?php

/**
 * Package Page Front Controller (Layer 3)
 *
 * Catch-all entry point for /p/{packageId}/{pageId} URLs.
 * Routes requests to the Package PageRouter which:
 * 1. Loads the package JSON definition
 * 2. Finds the matching page
 * 3. Checks access (RBAC enforcement)
 * 4. Executes queries through enforcement pipelines
 * 5. Renders components from JSON definitions
 *
 * URL Format: /p/com.woodson.student-directory/student-directory
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md
 * @see src/Package/PageRouter.php
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Layout;
use Hub\SiteSettings;
use Hub\Package\PageRouter;
use Hub\Package\PackageLoader;
use Hub\Package\ComponentRegistry;
use Hub\Package\PolicyEngine;

// Require login
Auth::requireLogin();

$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$userArray = [
    'id' => $user['id'] ?? null,
    'role' => $userRole,
    'email' => $user['email'] ?? '',
    'name' => $user['display_name'] ?? $user['name'] ?? '',
];

// Parse URL: /p/{packageId}/{pageId...}
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = array_values(array_filter(explode('/', $path)));

// Expected: ['p', 'com.woodson.student-directory', 'student-directory', ...]
if (count($pathParts) < 2 || $pathParts[0] !== 'p') {
    http_response_code(404);
    die('Invalid package URL. Expected: /p/{packageId}/{pageId}');
}

$packageId = $pathParts[1];
$pageId = implode('/', array_slice($pathParts, 2)); // Everything after packageId

// Merge GET and POST params
$params = array_merge($_GET, $_POST);

// Route the request
$router = new PageRouter();

// Register direct query handlers (Sprint 1 — pre-enforcement pipeline)
$userDirHandler = new \Hub\Package\Handlers\UserDirectoryHandler();
$router->registerHandler('com.woodson.user-directory', function (string $queryName, array $params, array $context) use ($userDirHandler) {
    return $userDirHandler->execute($queryName, $params, $context);
});

$result = $router->handle($packageId, $pageId, $userArray, $params);

// Handle non-200 responses
if ($result['status'] !== 200) {
    http_response_code($result['status']);
}

// Get package info for page rendering
$pageTitle = $result['title'] ?? 'Package';
$packageName = $result['package']['name'] ?? $packageId;
$packageIcon = $result['package']['icon'] ?? '';
$siteName = SiteSettings::get('site_name', 'The Hub');
$assets = $result['assets'] ?? ['css' => [], 'js' => []];
$layout = $result['layout'] ?? 'standard';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php Layout::renderHead($pageTitle . ' - ' . $siteName, 'package'); ?>

    <!-- Package component styles -->
    <link rel="stylesheet" href="/assets/css/package-components.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/package-components.css') ?: time(); ?>">

    <!-- Component-specific assets -->
    <?php foreach ($assets['css'] as $css): ?>
        <?php if ($css !== '/assets/css/package-components.css'): ?>
            <link rel="stylesheet" href="<?php echo e($css); ?>">
        <?php endif; ?>
    <?php endforeach; ?>
</head>

<body>
    <div class="page-wrapper">
        <?php include __DIR__ . '/../partials/header.php'; ?>

        <div class="main-content">
            <div class="container">

                <?php if ($layout !== 'error'): ?>
                    <!-- Page Header -->
                    <div class="pkg-page-header">
                        <div class="pkg-page-header-left">
                            <?php if ($packageIcon): ?>
                                <span class="pkg-page-icon"><i class="<?php echo e($packageIcon); ?>"></i></span>
                            <?php endif; ?>
                            <div>
                                <h1 class="pkg-page-title"><?php echo e($pageTitle); ?></h1>
                                <?php if (!empty($result['subtitle'])): ?>
                                    <p class="pkg-page-subtitle"><?php echo e($result['subtitle']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pkg-page-header-right">
                            <span class="pkg-badge"><?php echo e($packageName); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Rendered Components -->
                <div class="pkg-content" data-package="<?php echo e($packageId); ?>" data-page="<?php echo e($pageId); ?>">
                    <?php echo $result['html']; ?>
                </div>

            </div>
        </div>

        <?php include __DIR__ . '/../partials/footer.php'; ?>
    </div>

    <!-- Package component scripts -->
    <?php foreach ($assets['js'] as $js): ?>
        <script src="<?php echo e($js); ?>"></script>
    <?php endforeach; ?>

    <!-- Mutation modal (shared across all package pages) -->
    <div class="modal pkg-mutation-modal" id="pkgMutationModal" tabindex="-1" style="display:none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pkgMutationModalTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" id="pkgMutationModalBody">
                    <!-- Dynamic content inserted by JS -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pkgMutationModalConfirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
