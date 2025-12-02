<?php

/**
 * Management Console - Google Admin Console Style
 *
 * Module grid interface showing all sections/packages the user has access to.
 * Each card displays key metrics and provides quick access to management functions.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\ManagementCenter;
use Hub\Database;
use Hub\SiteSettings;

// Require login and manager+ role
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$userId = $_SESSION['user_id'];
$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$isSuperAdmin = ($userRole === 'super_admin');

$mc = new ManagementCenter();
$db = Database::getInstance();

// Get management display name
$mgmtDisplayName = SiteSettings::get('mgmt_display_name', 'Management');
$mgmtDescription = SiteSettings::get('mgmt_description', 'Centralized management system for tracking and processing submissions');
$mgmtIcon = SiteSettings::get('mgmt_icon', 'bi-kanban');

// Get sections user has access to with stats
if ($isSuperAdmin) {
    $sections = $mc->getSectionsWithCounts();
} else {
    $sections = $mc->getSectionsWithCounts($userId);
}

// Calculate aggregate stats across all accessible sections
$totalSubmissions = 0;
$totalPending = 0;
$totalUrgent = 0;
$totalRecent = 0;

foreach ($sections as &$section) {
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

// Build navigation items for sidebar
$navItems = \Hub\Components\EnterpriseSidebar::buildManagementNavItems($sections, null);
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
                <!-- Main Content Area -->
        <main class="admin-main">"><?= count($sections) ?></div>
                        <div class="metric-label">Active Modules</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon gold">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?= number_format($totalSubmissions) ?></div>
                        <div class="metric-label">Total Submissions</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: var(--warning-light); color: var(--warning);">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?= $totalPending ?></div>
                        <div class="metric-label">Pending Review</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon error">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?= $totalUrgent ?></div>
                        <div class="metric-label">Urgent Items</div>
                    </div>
                </div>
            </div>

            <!-- Module Cards Grid -->
            <div>
                <h2 style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--gray-900); margin: 0 0 var(--space-1) 0;">
                    Your Modules
                </h2>
                <p style="font-size: var(--text-sm); color: var(--gray-600); margin: 0 0 var(--space-4) 0;">
                    Select a module to view and manage submissions
                </p>

                <?php if (empty($sections)): ?>
                    <!-- Empty State -->
                    <div class="mgmt-empty-modules">
                        <i class="bi bi-inbox"></i>
                        <h3>No Modules Assigned</h3>
                        <p>You don't have access to any management modules yet.</p>
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
                    <!-- Module Grid -->
                    <div class="mgmt-module-grid">
                        <?php foreach ($sections as $section): ?>
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

                                <!-- Description -->
                                <?php if (!empty($section['description'])): ?>
                                    <p class="mgmt-module-description">
                                        <?= htmlspecialchars($section['description']) ?>
                                    </p>
                                <?php endif; ?>

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
