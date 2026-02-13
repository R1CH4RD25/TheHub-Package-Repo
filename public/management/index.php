<?php

/**
 * Management Console - Package-Driven Experience
 *
 * Shows installed packages the user has access to, based on their role.
 * Each package card links to the package's pages within the management shell.
 * Roles are determined by section_role_access, not hardcoded admin check.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\ManagementCenter;
use Hub\Database;
use Hub\SiteSettings;

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
$mgmtDescription = SiteSettings::get('mgmt_description', 'Centralized management system for tracking and processing submissions');
$mgmtIcon = SiteSettings::get('mgmt_icon', 'bi-kanban');

// Get installed packages user has access to (package-driven)
$packages = $mc->getInstalledPackagesForUser($userId, $userRole);

// Also get legacy sections with submissions (backward compatible)
if ($isSuperAdmin) {
    $sections = $mc->getSectionsWithCounts();
} else {
    $sections = $mc->getSectionsWithCounts($userId);
}

// Filter out legacy sections that are already covered by dynamic packages
$packageSlugs = array_column($packages, 'slug');
$legacySections = array_filter($sections, function ($s) use ($packageSlugs) {
    return !in_array($s['slug'], $packageSlugs);
});

// Calculate aggregate stats from legacy sections
$totalSubmissions = 0;
$totalPending = 0;
$totalUrgent = 0;
$totalRecent = 0;

foreach ($legacySections as &$section) {
    $totalSubmissions += $section['submission_count'];
    $totalPending += $section['pending_count'];

    // Get urgent count for this section
    $urgentResult = $db->fetchOne(
        "SELECT COUNT(*) as count FROM section_submissions
         WHERE section_id = ? AND priority = 'urgent' AND is_draft = 0 AND is_active = 1",
        [$section['id']]
    );
    $section['urgent_count'] = $urgentResult['count'] ?? 0;
    $totalUrgent += $section['urgent_count'];

    // Get recent (last 7 days) count
    $recentResult = $db->fetchOne(
        "SELECT COUNT(*) as count FROM section_submissions
         WHERE section_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND is_draft = 0",
        [$section['id']]
    );
    $section['recent_count'] = $recentResult['count'] ?? 0;
    $totalRecent += $section['recent_count'];
}
unset($section);

$pageTitle = $mgmtDisplayName . ' Console';
$orgName = SiteSettings::get('organization_name', 'Your Organization');
$siteName = SiteSettings::get('site_name', 'The Hub');

// Build navigation items for sidebar (packages + legacy sections)
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

    <!-- MGMT BUNDLE (Theme-aware workflow) -->
    <link rel="stylesheet" href="/assets/css/mgmt-bundle.css">

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
        // Render Enterprise Sidebar
        \Hub\Components\EnterpriseSidebar::render($user, $userRole, [
            'context' => 'management',
            'title' => $mgmtDisplayName,
            'icon' => $mgmtIcon,
            'logo_url' => '/management/',
            'nav_items' => $navItems,
            'active_item' => null
        ]);

        // Render Enterprise Header
        \Hub\Components\EnterpriseHeader::render($user, $userRole, [
            'context' => 'management',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => '/hub.php'],
                ['label' => $mgmtDisplayName]
            ],
            'show_notifications' => true
        ]);
        ?>

        <!-- Main Content Area -->
        <main class="admin-main">
            <!-- Overview Metrics -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?= count($packages) + count($legacySections) ?></div>
                        <div class="metric-label">Available Packages</div>
                    </div>
                </div>

                <?php if ($totalSubmissions > 0): ?>
                    <div class="metric-card">
                        <div class="metric-icon gold">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value"><?= number_format($totalSubmissions) ?></div>
                            <div class="metric-label">Total Submissions</div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($totalPending > 0): ?>
                    <div class="metric-card">
                        <div class="metric-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value"><?= $totalPending ?></div>
                            <div class="metric-label">Pending Review</div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($totalUrgent > 0): ?>
                    <div class="metric-card">
                        <div class="metric-icon error">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="metric-content">
                            <div class="metric-value"><?= $totalUrgent ?></div>
                            <div class="metric-label">Urgent Items</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Package Cards Grid -->
            <div>
                <h2 style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--gray-900); margin: 0 0 var(--space-1) 0;">
                    Your Packages
                </h2>
                <p style="font-size: var(--text-sm); color: var(--gray-600); margin: 0 0 var(--space-4) 0;">
                    Select a package to manage its data and settings
                </p>

                <?php if (empty($packages) && empty($legacySections)): ?>
                    <!-- Empty State -->
                    <div class="mgmt-empty-modules">
                        <i class="bi bi-inbox"></i>
                        <h3>No Packages Available</h3>
                        <p>You don't have access to any management packages yet.</p>
                        <?php if ($isSuperAdmin): ?>
                            <a href="/admin/" class="btn btn-primary">
                                <i class="bi bi-gear"></i> Configure Access
                            </a>
                        <?php else: ?>
                            <p style="font-size: var(--text-sm); color: var(--gray-500);">
                                Contact your administrator for access.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Package Grid -->
                    <div class="mgmt-module-grid">
                        <?php foreach ($packages as $pkg): ?>
                            <div class="mgmt-module-card" onclick="window.location.href='/management/package.php?id=<?= urlencode($pkg['package_id']) ?>'">
                                <!-- Card Header with Icon & Title -->
                                <div class="mgmt-module-header">
                                    <div class="mgmt-module-icon">
                                        <i class="<?= htmlspecialchars($pkg['icon'] ?? 'bi-box') ?>"></i>
                                    </div>
                                    <div class="mgmt-module-info">
                                        <h3 class="mgmt-module-title"><?= htmlspecialchars($pkg['name']) ?></h3>
                                        <p class="mgmt-module-subtitle">v<?= htmlspecialchars($pkg['version'] ?? '1.0') ?></p>
                                    </div>
                                </div>

                                <!-- Package Pages List -->
                                <?php if (!empty($pkg['pages'])): ?>
                                    <div class="mgmt-module-stats">
                                        <?php
                                        $visiblePages = array_filter($pkg['pages'], function ($p) {
                                            return !preg_match('/\{[^}]+\}/', $p['route'] ?? '');
                                        });
                                        ?>
                                        <div class="mgmt-module-stat">
                                            <span class="mgmt-module-stat-value"><?= count($visiblePages) ?></span>
                                            <span class="mgmt-module-stat-label">Pages</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Footer with Actions -->
                                <div class="mgmt-module-footer">
                                    <a href="/management/package.php?id=<?= urlencode($pkg['package_id']) ?>"
                                        class="mgmt-module-action"
                                        onclick="event.stopPropagation();">
                                        <i class="bi bi-box-arrow-in-right"></i>
                                        <span>Open Package</span>
                                    </a>
                                    <?php if ($pkg['is_dynamic']): ?>
                                        <div class="mgmt-module-badge" style="background: var(--info-light, #e8f4f8); color: var(--info, #0077b6);">
                                            <i class="bi bi-lightning-charge"></i>
                                            <span>Dynamic</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($legacySections as $section): ?>
                            <div class="mgmt-module-card" onclick="window.location.href='/management/section.php?slug=<?= urlencode($section['slug']) ?>'">
                                <!-- Card Header with Icon & Title -->
                                <div class="mgmt-module-header">
                                    <div class="mgmt-module-icon">
                                        <i class="<?= htmlspecialchars($section['icon'] ?? 'bi-folder') ?>"></i>
                                    </div>
                                    <div class="mgmt-module-info">
                                        <h3 class="mgmt-module-title"><?= htmlspecialchars($section['name']) ?></h3>
                                        <p class="mgmt-module-subtitle"><?= htmlspecialchars($section['mgmt_prefix'] ?? 'Section') ?></p>
                                    </div>
                                </div>

                                <!-- Stats Grid -->
                                <div class="mgmt-module-stats">
                                    <div class="mgmt-module-stat">
                                        <span class="mgmt-module-stat-value"><?= $section['submission_count'] ?></span>
                                        <span class="mgmt-module-stat-label">Total</span>
                                    </div>
                                    <div class="mgmt-module-stat">
                                        <span class="mgmt-module-stat-value"><?= $section['pending_count'] ?></span>
                                        <span class="mgmt-module-stat-label">Pending</span>
                                    </div>
                                    <div class="mgmt-module-stat">
                                        <span class="mgmt-module-stat-value"><?= $section['recent_count'] ?></span>
                                        <span class="mgmt-module-stat-label">Last 7 Days</span>
                                    </div>
                                    <?php if ($section['urgent_count'] > 0): ?>
                                        <div class="mgmt-module-stat">
                                            <span class="mgmt-module-stat-value" style="color: var(--error);"><?= $section['urgent_count'] ?></span>
                                            <span class="mgmt-module-stat-label">Urgent</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Footer with Actions -->
                                <div class="mgmt-module-footer">
                                    <a href="/management/section.php?slug=<?= urlencode($section['slug']) ?>"
                                        class="mgmt-module-action"
                                        onclick="event.stopPropagation();">
                                        <i class="bi bi-box-arrow-in-right"></i>
                                        <span>Open Module</span>
                                    </a>

                                    <?php if ($section['urgent_count'] > 0): ?>
                                        <div class="mgmt-module-badge">
                                            <i class="bi bi-exclamation-circle"></i>
                                            <span>Needs Attention</span>
                                        </div>
                                    <?php elseif ($section['recent_count'] > 0): ?>
                                        <div class="mgmt-module-badge" style="background: var(--info-light); color: var(--info);">
                                            <i class="bi bi-clock-history"></i>
                                            <span>Recent Activity</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
</body>

</html>
