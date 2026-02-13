<?php

/**
 * Management - Package Page Renderer
 *
 * Renders package pages (from PageRouter) within the management console shell.
 * This is the integration layer between:
 *   - Management Console (sidebar, header, footer, enterprise layout)
 *   - Package PageRouter (JSON-driven page rendering, data queries, components)
 *
 * URL: /management/package.php?id={packageId}&page={pageId}
 *
 * Access: Controlled by section_role_access — user's role must have access
 *         to the package's section. No hardcoded admin check.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\ManagementCenter;
use Hub\Database;
use Hub\SiteSettings;
use Hub\SectionRoleAccess;
use Hub\PackageAccessResolver;
use Hub\Package\PageRouter;
use Hub\Package\IconMapper;

// Require login
Auth::requireLogin();

$userId = $_SESSION['user_id'];
$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();

$packageId = $_GET['id'] ?? null;
$pageId = $_GET['page'] ?? '';

if (!$packageId) {
    header('Location: /management/');
    exit;
}

$mc = new ManagementCenter();
$db = Database::getInstance();

// Check if user has access to this package via section_role_access
$sra = new SectionRoleAccess();

// Find the section for this package
$sectionRow = $db->fetchOne(
    "SELECT s.id, s.slug
     FROM section_installations si
     JOIN sections s ON si.section_id = s.id
     WHERE si.package_id = ? AND si.status = 'installed' AND s.is_active = 1",
    [$packageId]
);

if (!$sectionRow) {
    http_response_code(404);
    header('Location: /management/');
    exit;
}

// Check access: section_role_access + super_admin bypass
if (!$sra->hasAccess($userId, $sectionRow['slug'])) {
    http_response_code(403);
    header('Location: /management/');
    exit;
}

// Resolve user's package role and permissions
$resolver = new PackageAccessResolver();
$userPkgRole = $resolver->getUserPackageRole($userId, $sectionRow['slug']);
$userPerms = $resolver->getUserPermissions($userId, $sectionRow['slug']);
$accessiblePages = $resolver->getAccessiblePages($userId, $sectionRow['slug']);

// Check page-level access (can user access this specific page?)
$effectivePageId = $pageId ?: 'index';
if (!$resolver->canAccessPage($userId, $sectionRow['slug'], $effectivePageId)) {
    http_response_code(403);
    // Redirect to the package index if they have some access, else management home
    if ($resolver->canAccessPage($userId, $sectionRow['slug'], 'index')) {
        header('Location: /management/package.php?id=' . urlencode($packageId) . '&denied=1');
    } else {
        header('Location: /management/');
    }
    exit;
}

// Get management branding
$mgmtDisplayName = SiteSettings::get('mgmt_display_name', 'Management');
$mgmtIcon = SiteSettings::get('mgmt_icon', 'bi-kanban');
$siteName = SiteSettings::get('site_name', 'The Hub');

// Get all installed packages for sidebar
$packages = $mc->getInstalledPackagesForUser($userId, $userRole);

// Build sidebar nav (filtered by accessible pages)
$navItems = \Hub\Components\EnterpriseSidebar::buildManagementNavItems(
    [],           // no legacy sections in sidebar when viewing a package
    null,
    $packages,
    $packageId,
    $pageId ?: 'index',
    $accessiblePages
);

// Build user array for PageRouter (include package role + permissions)
$userArray = [
    'id' => $user['id'] ?? null,
    'role' => $userRole,
    'packageRole' => $userPkgRole,
    'permissions' => $userPerms,
    'email' => $user['email'] ?? '',
    'name' => $user['display_name'] ?? $user['name'] ?? '',
];

// Merge GET and POST params
$params = array_merge($_GET, $_POST);

// Route through PageRouter
$router = new PageRouter();

// Register handlers (same as /p/index.php)
$studentDirHandler = new \Hub\Package\StudentDirectory\StudentDirectoryHandler();
$router->registerHandler('district.student-directory', function (string $queryName, array $params, array $context) use ($studentDirHandler) {
    return $studentDirHandler->executeQuery($queryName, $params, $context);
});

// Check if user directory handler exists
if (class_exists(\Hub\Package\Handlers\UserDirectoryHandler::class)) {
    $userDirHandler = new \Hub\Package\Handlers\UserDirectoryHandler();
    $router->registerHandler('com.woodson.user-directory', function (string $queryName, array $params, array $context) use ($userDirHandler) {
        return $userDirHandler->execute($queryName, $params, $context);
    });
}

$result = $router->handle($packageId, $pageId, $userArray, $params);

// Handle non-200 responses
if ($result['status'] !== 200) {
    http_response_code($result['status']);
}

// Get page info
$pageTitle = $result['title'] ?? 'Package';
$packageName = $result['package']['name'] ?? $packageId;
$packageIcon = $result['package']['icon'] ?? '';
$packageIcon = IconMapper::map($packageIcon);
$assets = $result['assets'] ?? ['css' => [], 'js' => []];
$layout = $result['layout'] ?? 'standard';

// Find current package info
$currentPkg = null;
foreach ($packages as $pkg) {
    if ($pkg['package_id'] === $packageId) {
        $currentPkg = $pkg;
        break;
    }
}

// Build breadcrumbs
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/hub.php'],
    ['label' => $mgmtDisplayName, 'url' => '/management/'],
    ['label' => $currentPkg['name'] ?? $packageName],
];
if ($pageId && $pageId !== 'index') {
    $breadcrumbs[count($breadcrumbs) - 1]['url'] = '/management/package.php?id=' . urlencode($packageId);
    $breadcrumbs[] = ['label' => $pageTitle];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($mgmtDisplayName) ?> - <?= htmlspecialchars($siteName) ?></title>

    <!-- MGMT BUNDLE (Enterprise Design) -->
    <link rel="stylesheet" href="/assets/css/mgmt-bundle.css">

    <!-- Package component styles -->
    <link rel="stylesheet" href="/assets/css/package-components.css?v=<?= filemtime(__DIR__ . '/../assets/css/package-components.css') ?: time() ?>">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">

    <!-- Component-specific assets -->
    <?php foreach ($assets['css'] as $css): ?>
        <?php if ($css !== '/assets/css/package-components.css'): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
        <?php endif; ?>
    <?php endforeach; ?>

    <style>
        /* Package content within management shell - match admin dashboard padding */
        .mgmt-package-content {
            padding-top: var(--space-4, 1rem);
        }

        .mgmt-package-content .pkg-page-header {
            margin-bottom: var(--space-5, 1.25rem);
            padding-bottom: var(--space-4, 1rem);
            border-bottom: 1px solid var(--gray-300, #e0e0e0);
        }

        .mgmt-package-content .pkg-content {
            min-height: 400px;
        }

        /* Ensure DataTables and tables work within the management shell */
        .mgmt-package-content .dataTables_wrapper {
            width: 100%;
        }

        .mgmt-package-content table {
            width: 100% !important;
        }
    </style>
</head>

<body class="admin-root">
    <div class="admin-shell">
        <?php
        // Render Enterprise Sidebar
        \Hub\Components\EnterpriseSidebar::render($user, $userRole, [
            'context' => 'management',
            'title' => $mgmtDisplayName,
            'icon' => $mgmtIcon,
            'logo_url' => '/management/',
            'nav_items' => $navItems,
            'active_item' => 'pkg:' . $packageId
        ]);

        // Render Enterprise Header
        \Hub\Components\EnterpriseHeader::render($user, $userRole, [
            'context' => 'management',
            'breadcrumbs' => $breadcrumbs,
            'show_notifications' => true
        ]);
        ?>

        <!-- Main Content Area -->
        <main class="admin-main">
            <div class="admin-main-content mgmt-package-content">
                <?php if ($layout !== 'error'): ?>
                    <!-- Page Header -->
                    <div class="pkg-page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4, 1rem);">
                        <div style="display: flex; align-items: center; gap: var(--space-3, 0.75rem);">
                            <?php if ($packageIcon): ?>
                                <span class="pkg-page-icon" style="font-size: 1.5rem; color: var(--primary, #4361ee);"><i class="<?= htmlspecialchars($packageIcon) ?>"></i></span>
                            <?php endif; ?>
                            <div>
                                <h1 style="font-size: var(--text-xl, 1.25rem); font-weight: var(--font-semibold, 600); color: var(--gray-900, #111); margin: 0;"><?= htmlspecialchars($pageTitle) ?></h1>
                                <?php if (!empty($result['subtitle'])): ?>
                                    <p style="font-size: var(--text-sm, 0.875rem); color: var(--gray-600, #666); margin: 0;"><?= htmlspecialchars($result['subtitle']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: var(--space-2, 0.5rem);">
                            <span style="background: var(--primary-light, #eef); color: var(--primary, #4361ee); padding: 4px 12px; border-radius: 9999px; font-size: var(--text-xs, 0.75rem); font-weight: 500;"><?= htmlspecialchars($packageName) ?></span>
                            <?php if ($userPkgRole): ?>
                                <span style="background: var(--success-light, #e8f5e9); color: var(--success, #2e7d32); padding: 4px 12px; border-radius: 9999px; font-size: var(--text-xs, 0.75rem); font-weight: 500; text-transform: capitalize;">
                                    <i class="bi bi-shield-check"></i> <?= htmlspecialchars($userPkgRole) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Rendered Package Components -->
                <div class="pkg-content" data-package="<?= htmlspecialchars($packageId) ?>" data-page="<?= htmlspecialchars($pageId) ?>" data-management="true">
                    <?= $result['html'] ?>
                </div>
            </div>
        </main>

        <?php
        // Render Enterprise Footer
        \Hub\Components\EnterpriseFooter::render($user, [
            'context' => 'management',
            'show_version' => true,
            'show_user' => false,
            'show_custom_text' => true
        ]);
        ?>
    </div><!-- end admin-shell -->

    <!-- Package component scripts -->
    <?php foreach ($assets['js'] as $js): ?>
        <script src="<?= htmlspecialchars($js) ?>"></script>
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

    <script>
        // Override package link navigation to stay within management shell
        document.addEventListener('DOMContentLoaded', function() {
            const pkgContent = document.querySelector('.pkg-content[data-management="true"]');
            if (!pkgContent) return;

            const packageId = pkgContent.dataset.package;
            const baseUrl = '/p/' + packageId;

            // Intercept clicks on package internal links
            pkgContent.addEventListener('click', function(e) {
                const link = e.target.closest('a[href]');
                if (!link) return;

                const href = link.getAttribute('href');
                if (!href) return;

                // Check if this is a package-internal link
                if (href.startsWith(baseUrl + '/') || href.startsWith(baseUrl)) {
                    e.preventDefault();
                    // Extract pageId from the URL
                    const pagePath = href.replace(baseUrl, '').replace(/^\//, '');
                    const mgmtUrl = '/management/package.php?id=' + encodeURIComponent(packageId) + '&page=' + encodeURIComponent(pagePath);
                    window.location.href = mgmtUrl;
                }
            });
        });
    </script>
</body>

</html>
