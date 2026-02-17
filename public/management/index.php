<?php

/**
 * Management Console - Home Dashboard
 *
 * Google Admin Console-style card grid.
 * Shows one card per package/section the user has access to,
 * with quick action links to their accessible pages.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\ManagementCenter;
use Hub\Database;
use Hub\SiteSettings;
use Hub\PackageAccessResolver;

// Require login
Auth::requireLogin();

$userId = $_SESSION['user_id'];
$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$isSuperAdmin = ($userRole === 'super_admin');

$mc = new ManagementCenter();
$db = Database::getInstance();

// Check if user has access to any management packages
if (!$mc->userHasManagementAccess($userId, $userRole)) {
    http_response_code(403);
    header('Location: /hub.php');
    exit;
}

// Get management display name
$mgmtDisplayName = SiteSettings::get('mgmt_display_name', 'Management');
$mgmtIcon = SiteSettings::get('mgmt_icon', 'bi-kanban');
$siteName = SiteSettings::get('site_name', 'The Hub');
$orgName = SiteSettings::get('organization_name', 'Your Organization');

// Get installed packages user has access to
$packages = $mc->getInstalledPackagesForUser($userId, $userRole);

// Resolve package roles for each package
$resolver = new PackageAccessResolver();
foreach ($packages as &$pkg) {
    $slug = $pkg['slug'] ?? '';
    $pkg['userPkgRole'] = $resolver->getUserPackageRole($userId, $slug);
    $pkg['accessiblePages'] = $resolver->getAccessiblePages($userId, $slug);
    // Filter pages to only accessible ones
    $pkg['pages'] = array_filter($pkg['pages'] ?? [], function ($page) use ($resolver, $userId, $slug) {
        $pageId = $page['id'] ?? '';
        return $resolver->canAccessPage($userId, $slug, $pageId);
    });
}
unset($pkg);

// Legacy sections
if ($isSuperAdmin) {
    $sections = $mc->getSectionsWithCounts();
} else {
    $sections = $mc->getSectionsWithCounts($userId);
}
$packageSlugs = array_column($packages, 'slug');
$legacySections = array_filter($sections, fn($s) => !in_array($s['slug'], $packageSlugs));

$pageTitle = $mgmtDisplayName . ' Console';

// Icon color rotation for cards
$iconColors = ['blue', 'purple', 'orange', 'green', 'teal', 'gray'];
$colorIndex = 0;

// Build navigation items for sidebar
$navItems = \Hub\Components\EnterpriseSidebar::buildManagementNavItems(
    $legacySections,
    null,
    $packages,
    null,
    null
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteName) ?></title>

    <!-- MGMT BUNDLE -->
    <link rel="stylesheet" href="/assets/css/mgmt-bundle.css?v=<?= filemtime(__DIR__ . '/../assets/css/mgmt-bundle.css') ?>">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
</head>

<body class="admin-root">
    <div class="admin-shell">
        <?php
        \Hub\Components\EnterpriseSidebar::render($user, $userRole, [
            'context' => 'management',
            'title' => $mgmtDisplayName,
            'icon' => $mgmtIcon,
            'logo_url' => '/management/',
            'nav_items' => $navItems,
            'active_item' => 'home'
        ]);

        \Hub\Components\EnterpriseHeader::render($user, $userRole, [
            'context' => 'management',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => '/hub.php'],
                ['label' => $mgmtDisplayName]
            ],
            'show_notifications' => true
        ]);
        ?>

        <main class="admin-main">
            <!-- Google Admin Console-style card grid -->
            <div class="mgmt-modules-grid admin-responsive">

                <?php foreach ($packages as $pkg): ?>
                <?php
                    $pkgName = htmlspecialchars($pkg['name'] ?? 'Package');
                    $pkgDesc = htmlspecialchars($pkg['description'] ?? 'Manage ' . ($pkg['name'] ?? 'package'));
                    $pkgIcon = $pkg['icon'] ?? 'fas fa-box';
                    $pkgUrl = '/management/package.php?id=' . urlencode($pkg['package_id']);
                    $color = $iconColors[$colorIndex % count($iconColors)];
                    $colorIndex++;

                    // Get visible pages (no dynamic route params)
                    $visiblePages = array_values(array_filter($pkg['pages'] ?? [], function ($p) {
                        return !preg_match('/\{[^}]+\}/', $p['route'] ?? '');
                    }));
                ?>
                <div class="mgmt-module-card mgmt-rich-card">
                    <div class="module-header">
                        <div class="module-icon <?= $color ?>">
                            <i class="<?= htmlspecialchars($pkgIcon) ?>"></i>
                        </div>
                        <div class="module-header-text">
                            <h3><?= $pkgName ?></h3>
                            <p class="module-subtitle"><?= $pkgDesc ?></p>
                        </div>
                        <a href="<?= htmlspecialchars($pkgUrl) ?>" class="module-manage-link">Manage</a>
                    </div>
                    <?php if (!empty($visiblePages)): ?>
                    <div class="module-quick-actions">
                        <?php foreach (array_slice($visiblePages, 0, 3) as $page): ?>
                        <?php
                            $pTitle = htmlspecialchars($page['title'] ?? $page['id'] ?? 'Page');
                            $pRoute = '/management/package.php?id=' . urlencode($pkg['package_id']) . '&page=' . urlencode($page['id'] ?? '');
                        ?>
                        <a href="<?= htmlspecialchars($pRoute) ?>" class="module-action-link"><?= $pTitle ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <?php foreach ($legacySections as $section): ?>
                <?php
                    $secName = htmlspecialchars($section['name'] ?? 'Section');
                    $secIcon = $section['icon'] ?? 'bi-folder';
                    $secUrl = '/management/section.php?slug=' . urlencode($section['slug']);
                    $secCount = (int)($section['submission_count'] ?? 0);
                    $color = $iconColors[$colorIndex % count($iconColors)];
                    $colorIndex++;
                ?>
                <div class="mgmt-module-card mgmt-rich-card">
                    <div class="module-header">
                        <div class="module-icon <?= $color ?>">
                            <i class="<?= htmlspecialchars($secIcon) ?>"></i>
                        </div>
                        <div class="module-header-text">
                            <h3><?= $secName ?></h3>
                            <p class="module-subtitle"><?= $secCount ?> submission<?= $secCount !== 1 ? 's' : '' ?></p>
                        </div>
                        <a href="<?= htmlspecialchars($secUrl) ?>" class="module-manage-link">Manage</a>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </main>

        <?php
        \Hub\Components\EnterpriseFooter::render($user, [
            'context' => 'management',
            'show_version' => true,
            'show_user' => false,
            'show_custom_text' => true
        ]);
        ?>
    </div>
</body>

</html>
