<?php

/**
 * Management Console - Home Dashboard
 *
 * Alerts & attention-focused landing page.
 * Package navigation lives in the sidebar (expandable groups).
 * The main dashboard shows what needs your attention today.
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

// ─── Gather dashboard data ──────────────────────────────────────────
$attentionItems = [];
$stats = [];

// Pending submissions (unreviewed)
try {
    $pendingSubs = $db->fetchAll(
        "SELECT s.name, s.slug, COUNT(ss.id) as cnt
         FROM section_submissions ss
         JOIN sections s ON ss.section_id = s.id
         WHERE ss.is_draft = 0 AND ss.is_active = 1 AND ss.reviewed_at IS NULL
         GROUP BY s.name, s.slug ORDER BY cnt DESC"
    );
    foreach ($pendingSubs as $ps) {
        $attentionItems[] = [
            'type' => 'warning',
            'icon' => 'bi-inbox',
            'title' => $ps['cnt'] . ' unreviewed submission' . ($ps['cnt'] > 1 ? 's' : ''),
            'subtitle' => $ps['name'],
            'url' => '/management/section.php?slug=' . urlencode($ps['slug']),
            'action' => 'Review'
        ];
    }
} catch (\Exception $e) { /* table may not exist yet */
}

// Unresolved package alerts
try {
    $alertCount = $db->fetchOne(
        "SELECT COUNT(*) as cnt FROM package_triggered_alerts WHERE resolved_at IS NULL"
    );
    if (($alertCount['cnt'] ?? 0) > 0) {
        $attentionItems[] = [
            'type' => 'danger',
            'icon' => 'bi-exclamation-triangle',
            'title' => $alertCount['cnt'] . ' unresolved alert' . ($alertCount['cnt'] > 1 ? 's' : ''),
            'subtitle' => 'Package monitoring',
            'url' => '#',
            'action' => 'View'
        ];
    }
} catch (\Exception $e) {
}

// Urgent submissions
try {
    $urgentCount = $db->fetchOne(
        "SELECT COUNT(*) as cnt FROM section_submissions
         WHERE priority = 'urgent' AND is_draft = 0 AND is_active = 1 AND reviewed_at IS NULL"
    );
    if (($urgentCount['cnt'] ?? 0) > 0) {
        $attentionItems[] = [
            'type' => 'danger',
            'icon' => 'bi-exclamation-octagon',
            'title' => $urgentCount['cnt'] . ' urgent item' . ($urgentCount['cnt'] > 1 ? 's' : '') . ' need attention',
            'subtitle' => 'Priority submissions',
            'url' => '#',
            'action' => 'Review'
        ];
    }
} catch (\Exception $e) {
}

// Recent activity from audit log (last 7 days)
try {
    $recentActivity = $db->fetchAll(
        "SELECT al.action, al.table_name, al.created_at, u.name as user_name, u.email
         FROM audit_log al
         LEFT JOIN users u ON al.user_id = u.id
         WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           AND al.action NOT IN ('login_success', 'logout', 'package_discovery_search', 'package_discovery_download')
         ORDER BY al.created_at DESC
         LIMIT 15"
    );
} catch (\Exception $e) {
    $recentActivity = [];
}

// Package-specific stats
try {
    $totalStudents = $db->fetchOne("SELECT COUNT(*) as cnt FROM woodson_students.students");
    $stats['total_students'] = $totalStudents['cnt'] ?? 0;

    $recentStudents = $db->fetchOne(
        "SELECT COUNT(*) as cnt FROM woodson_students.students WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $stats['recent_student_updates'] = $recentStudents['cnt'] ?? 0;

    $newStudents = $db->fetchOne(
        "SELECT COUNT(*) as cnt FROM woodson_students.students WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $stats['new_students'] = $newStudents['cnt'] ?? 0;
} catch (\Exception $e) {
}

$pageTitle = $mgmtDisplayName . ' Console';

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

    <style>
        .mgmt-welcome {
            margin-bottom: var(--space-5, 1.25rem);
        }

        .mgmt-welcome h1 {
            font-size: var(--text-2xl, 1.5rem);
            font-weight: var(--font-bold, 700);
            color: var(--gray-900, #111);
            margin: 0 0 var(--space-1, 0.25rem) 0;
        }

        .mgmt-welcome p {
            font-size: var(--text-sm, 0.875rem);
            color: var(--gray-500, #888);
            margin: 0;
        }

        .attention-section {
            margin-bottom: var(--space-6, 1.5rem);
        }

        .attention-section h2 {
            font-size: var(--text-base, 1rem);
            font-weight: var(--font-semibold, 600);
            color: var(--gray-800, #222);
            margin: 0 0 var(--space-3, 0.75rem) 0;
            display: flex;
            align-items: center;
            gap: var(--space-2, 0.5rem);
        }

        .attention-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-2, 0.5rem);
        }

        .attention-item {
            display: flex;
            align-items: center;
            gap: var(--space-3, 0.75rem);
            padding: var(--space-3, 0.75rem) var(--space-4, 1rem);
            background: var(--white, #fff);
            border: 1px solid var(--gray-200, #e5e5e5);
            border-radius: var(--radius-lg, 12px);
            transition: all 0.15s ease;
        }

        .attention-item:hover {
            border-color: var(--primary, #4361ee);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .attention-item.type-warning {
            border-left: 3px solid var(--warning, #f59e0b);
        }

        .attention-item.type-danger {
            border-left: 3px solid var(--error, #ef4444);
        }

        .attention-item.type-info {
            border-left: 3px solid var(--info, #0077b6);
        }

        .attention-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md, 8px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .attention-icon.warning {
            background: var(--warning-light, #fef3cd);
            color: var(--warning, #f59e0b);
        }

        .attention-icon.danger {
            background: var(--error-light, #fde8e8);
            color: var(--error, #ef4444);
        }

        .attention-icon.info {
            background: var(--info-light, #e8f4f8);
            color: var(--info, #0077b6);
        }

        .attention-body {
            flex: 1;
            min-width: 0;
        }

        .attention-title {
            font-size: var(--text-sm, 0.875rem);
            font-weight: var(--font-medium, 500);
            color: var(--gray-900, #111);
            margin: 0;
        }

        .attention-subtitle {
            font-size: var(--text-xs, 0.75rem);
            color: var(--gray-500, #888);
            margin: 0;
        }

        .attention-action {
            font-size: var(--text-xs, 0.75rem);
            font-weight: var(--font-semibold, 600);
            color: var(--primary, #4361ee);
            text-decoration: none;
            padding: 4px 12px;
            border-radius: 9999px;
            background: var(--primary-light, #eef);
            white-space: nowrap;
            transition: all 0.15s ease;
        }

        .attention-action:hover {
            background: var(--primary, #4361ee);
            color: #fff;
        }

        .all-clear {
            text-align: center;
            padding: var(--space-6, 1.5rem) var(--space-4, 1rem);
            background: var(--success-light, #e8f5e9);
            border-radius: var(--radius-lg, 12px);
            border: 1px solid #c8e6c9;
        }

        .all-clear i {
            font-size: 2rem;
            color: var(--success, #2e7d32);
            margin-bottom: var(--space-2, 0.5rem);
        }

        .all-clear h3 {
            font-size: var(--text-base, 1rem);
            color: var(--success, #2e7d32);
            margin: 0 0 var(--space-1, 0.25rem) 0;
        }

        .all-clear p {
            font-size: var(--text-sm, 0.875rem);
            color: var(--gray-600, #666);
            margin: 0;
        }

        .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .activity-entry {
            display: flex;
            align-items: flex-start;
            gap: var(--space-3, 0.75rem);
            padding: var(--space-3, 0.75rem) 0;
            border-bottom: 1px solid var(--gray-100, #f0f0f0);
        }

        .activity-entry:last-child {
            border-bottom: none;
        }

        .activity-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gray-300, #d0d0d0);
            margin-top: 6px;
            flex-shrink: 0;
        }

        .activity-dot.action-update {
            background: var(--info, #0077b6);
        }

        .activity-dot.action-delete {
            background: var(--error, #ef4444);
        }

        .activity-dot.action-create,
        .activity-dot.action-insert {
            background: var(--success, #2e7d32);
        }

        .activity-dot.action-package_install {
            background: var(--primary, #4361ee);
        }

        .activity-text {
            font-size: var(--text-sm, 0.875rem);
            color: var(--gray-700, #444);
            line-height: 1.4;
        }

        .activity-text strong {
            font-weight: var(--font-medium, 500);
            color: var(--gray-900, #111);
        }

        .activity-time {
            font-size: var(--text-xs, 0.75rem);
            color: var(--gray-400, #aaa);
            margin-top: 2px;
        }

        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--space-3, 0.75rem);
        }

        .quick-access-card {
            display: flex;
            align-items: center;
            gap: var(--space-3, 0.75rem);
            padding: var(--space-3, 0.75rem) var(--space-4, 1rem);
            background: var(--white, #fff);
            border: 1px solid var(--gray-200, #e5e5e5);
            border-radius: var(--radius-lg, 12px);
            text-decoration: none;
            color: var(--gray-800, #222);
            transition: all 0.15s ease;
        }

        .quick-access-card:hover {
            border-color: var(--primary, #4361ee);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transform: translateY(-1px);
        }

        .quick-access-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md, 8px);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-light, #eef);
            color: var(--primary, #4361ee);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .quick-access-label {
            font-size: var(--text-sm, 0.875rem);
            font-weight: var(--font-medium, 500);
        }

        .quick-access-meta {
            font-size: var(--text-xs, 0.75rem);
            color: var(--gray-500, #888);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-5, 1.25rem);
        }

        @media (max-width: 900px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .dashboard-section {
            background: var(--white, #fff);
            border: 1px solid var(--gray-200, #e5e5e5);
            border-radius: var(--radius-lg, 12px);
            padding: var(--space-4, 1rem);
        }

        .dashboard-section h3 {
            font-size: var(--text-sm, 0.875rem);
            font-weight: var(--font-semibold, 600);
            color: var(--gray-700, #444);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 var(--space-3, 0.75rem) 0;
            display: flex;
            align-items: center;
            gap: var(--space-2, 0.5rem);
        }
    </style>
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
            <div class="admin-main-content">

                <!-- Welcome -->
                <div class="mgmt-welcome">
                    <h1>Welcome back, <?= htmlspecialchars(explode(' ', $user['display_name'] ?? $user['name'] ?? 'User')[0]) ?></h1>
                    <p><?= date('l, F j, Y') ?> &mdash; <?= htmlspecialchars($orgName) ?></p>
                </div>

                <!-- Metrics Row -->
                <div class="metrics-grid" style="margin-bottom: var(--space-5, 1.25rem);">
                    <div class="metric-card">
                        <div class="metric-icon"><i class="bi bi-box-seam"></i></div>
                        <div class="metric-content">
                            <div class="metric-value"><?= count($packages) + count($legacySections) ?></div>
                            <div class="metric-label">Package<?= (count($packages) + count($legacySections)) !== 1 ? 's' : '' ?></div>
                        </div>
                    </div>
                    <?php if (($stats['total_students'] ?? 0) > 0): ?>
                        <div class="metric-card">
                            <div class="metric-icon" style="background: var(--info-light); color: var(--info);"><i class="bi bi-people"></i></div>
                            <div class="metric-content">
                                <div class="metric-value"><?= number_format($stats['total_students']) ?></div>
                                <div class="metric-label">Total Students</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (($stats['recent_student_updates'] ?? 0) > 0): ?>
                        <div class="metric-card">
                            <div class="metric-icon" style="background: var(--success-light); color: var(--success);"><i class="bi bi-pencil-square"></i></div>
                            <div class="metric-content">
                                <div class="metric-value"><?= $stats['recent_student_updates'] ?></div>
                                <div class="metric-label">Updated This Week</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (($stats['new_students'] ?? 0) > 0): ?>
                        <div class="metric-card">
                            <div class="metric-icon" style="background: var(--warning-light); color: var(--warning);"><i class="bi bi-person-plus"></i></div>
                            <div class="metric-content">
                                <div class="metric-value"><?= $stats['new_students'] ?></div>
                                <div class="metric-label">Added This Week</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Attention Items -->
                <div class="attention-section">
                    <h2><i class="bi bi-bell" style="color: var(--warning, #f59e0b);"></i> Needs Your Attention</h2>
                    <?php if (empty($attentionItems)): ?>
                        <div class="all-clear">
                            <i class="bi bi-check-circle"></i>
                            <h3>All Clear</h3>
                            <p>Nothing needs your attention right now. Use the sidebar to navigate your packages.</p>
                        </div>
                    <?php else: ?>
                        <div class="attention-list">
                            <?php foreach ($attentionItems as $item): ?>
                                <div class="attention-item type-<?= htmlspecialchars($item['type']) ?>">
                                    <div class="attention-icon <?= htmlspecialchars($item['type']) ?>">
                                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                                    </div>
                                    <div class="attention-body">
                                        <p class="attention-title"><?= htmlspecialchars($item['title']) ?></p>
                                        <p class="attention-subtitle"><?= htmlspecialchars($item['subtitle']) ?></p>
                                    </div>
                                    <a href="<?= htmlspecialchars($item['url']) ?>" class="attention-action">
                                        <?= htmlspecialchars($item['action']) ?> <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Two-column: Recent Activity + Quick Access -->
                <div class="dashboard-grid">
                    <!-- Recent Activity -->
                    <div class="dashboard-section">
                        <h3><i class="bi bi-clock-history"></i> Recent Activity</h3>
                        <?php if (empty($recentActivity)): ?>
                            <p style="color: var(--gray-500); font-size: var(--text-sm); text-align: center; padding: var(--space-4) 0;">
                                No recent activity
                            </p>
                        <?php else: ?>
                            <div class="activity-feed">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <?php
                                    $action = $activity['action'] ?? 'unknown';
                                    $table = $activity['table_name'] ?? '';
                                    $who = $activity['user_name'] ?? $activity['email'] ?? 'System';
                                    $when = $activity['created_at'] ?? '';
                                    $timeAgo = '';
                                    if ($when) {
                                        $diff = time() - strtotime($when);
                                        if ($diff < 60) $timeAgo = 'just now';
                                        elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm ago';
                                        elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h ago';
                                        else $timeAgo = floor($diff / 86400) . 'd ago';
                                    }
                                    $actionLabel = match ($action) {
                                        'insert', 'create' => 'created',
                                        'update' => 'updated',
                                        'delete' => 'deleted',
                                        'package_install' => 'installed package',
                                        'package_uninstall' => 'uninstalled package',
                                        'package_upgrade' => 'upgraded package',
                                        default => str_replace('_', ' ', $action)
                                    };
                                    $tableLabel = str_replace('_', ' ', $table);
                                    ?>
                                    <div class="activity-entry">
                                        <div class="activity-dot action-<?= htmlspecialchars($action) ?>"></div>
                                        <div>
                                            <div class="activity-text">
                                                <strong><?= htmlspecialchars($who) ?></strong> <?= htmlspecialchars($actionLabel) ?>
                                                <?php if ($tableLabel): ?>
                                                    <span style="color: var(--gray-500);"><?= htmlspecialchars($tableLabel) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-time"><?= htmlspecialchars($timeAgo) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Access — one card per package (sidebar handles page-level nav) -->
                    <div class="dashboard-section">
                        <h3><i class="bi bi-lightning-charge"></i> Quick Access</h3>
                        <?php
                        $allItems = [];
                        foreach ($packages as $pkg) {
                            $visiblePages = array_filter($pkg['pages'] ?? [], function ($p) {
                                return !preg_match('/\{[^}]+\}/', $p['route'] ?? '');
                            });
                            $allItems[] = [
                                'url' => '/management/package.php?id=' . urlencode($pkg['package_id']),
                                'icon' => $pkg['icon'] ?? 'bi-box',
                                'label' => $pkg['name'],
                                'meta' => count($visiblePages) . ' page' . (count($visiblePages) !== 1 ? 's' : ''),
                                'role' => $pkg['userPkgRole'] ?? null,
                            ];
                        }
                        foreach ($legacySections as $section) {
                            $allItems[] = [
                                'url' => '/management/section.php?slug=' . urlencode($section['slug']),
                                'icon' => $section['icon'] ?? 'bi-folder',
                                'label' => $section['name'],
                                'meta' => 'Section',
                                'role' => null,
                            ];
                        }
                        $initialShow = 8;
                        $hasMore = count($allItems) > $initialShow;
                        ?>
                        <div class="quick-access-grid" id="quickAccessGrid">
                            <?php foreach ($allItems as $idx => $qa): ?>
                                <a href="<?= htmlspecialchars($qa['url']) ?>"
                                    class="quick-access-card<?= $idx >= $initialShow ? ' qa-hidden' : '' ?>"
                                    <?= $idx >= $initialShow ? 'style="display:none;"' : '' ?>>
                                    <div class="quick-access-icon"><i class="<?= htmlspecialchars($qa['icon']) ?>"></i></div>
                                    <div style="flex:1; min-width:0;">
                                        <div class="quick-access-label"><?= htmlspecialchars($qa['label']) ?></div>
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span class="quick-access-meta"><?= htmlspecialchars($qa['meta']) ?></span>
                                            <?php if (!empty($qa['role'])): ?>
                                                <span style="background:var(--success-light,#e8f5e9);color:var(--success,#2e7d32);padding:1px 6px;border-radius:9999px;font-size:0.6rem;font-weight:600;text-transform:uppercase;letter-spacing:0.3px;"><?= htmlspecialchars($qa['role']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($hasMore): ?>
                            <button id="qaShowAll" onclick="document.querySelectorAll('.qa-hidden').forEach(el=>{el.style.display='';});this.style.display='none';"
                                style="display:block;margin:var(--space-3,0.75rem) auto 0;padding:6px 16px;font-size:var(--text-xs,0.75rem);font-weight:600;color:var(--primary,#4361ee);background:var(--primary-light,#eef);border:none;border-radius:9999px;cursor:pointer;transition:all 0.15s ease;">
                                Show all <?= count($allItems) ?> packages
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

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
