<?php
/**
 * Command Center - Dashboard
 *
 * Main dashboard showing overview stats, section list with counts,
 * and recent activity across all submissions.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\CommandCenter;
use Hub\SectionRoleAccess;

// Require login and admin/super_admin role
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$userId = $_SESSION['user_id'];
$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();

$cc = new CommandCenter();

// Get dashboard data
$stats = $cc->getDashboardStats();
$sections = $cc->getSectionsWithCounts($userId);
$recentActivity = $cc->getActivityFeed(15);

$pageTitle = 'Command Center';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php Hub\Layout::renderHead($pageTitle . ' - Dashboard', 'command'); ?>
</head>
<body>

<?php Hub\Layout::renderHeader($user, $userRole, 'command'); ?>

<style>
/* Command Center Professional Styles */
.cc-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.cc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e9ecef;
}

.cc-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
    color: #2c3e50;
}

.cc-header .breadcrumb {
    background: none;
    padding: 0;
    margin: 0;
    font-size: 14px;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border-left: 4px solid #3498db;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.12);
}

.stat-card.status {
    border-left-color: #6c757d;
}

.stat-card.warning {
    border-left-color: #f39c12;
}

.stat-card.success {
    border-left-color: #27ae60;
}

.stat-card.info {
    border-left-color: #3498db;
}

.stat-card .stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #2c3e50;
    margin: 10px 0 5px;
}

.stat-card .stat-label {
    font-size: 14px;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card .stat-icon {
    font-size: 24px;
    opacity: 0.3;
    float: right;
}

/* Status Breakdown */
.status-breakdown {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.status-item {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    text-align: center;
    border-left: 3px solid;
}

.status-item .status-count {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 5px;
}

.status-item .status-name {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
}

/* Sections Grid */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.section-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    border-top: 4px solid #3498db;
}

.section-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.section-card.has-pending {
    border-top-color: #e74c3c;
}

.section-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.section-icon {
    font-size: 32px;
    margin-right: 15px;
    opacity: 0.8;
}

.section-title {
    flex: 1;
}

.section-title h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

.section-title .section-slug {
    font-size: 12px;
    color: #95a5a6;
    margin-top: 2px;
}

.section-counts {
    display: flex;
    gap: 20px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ecf0f1;
}

.count-item {
    flex: 1;
    text-align: center;
}

.count-item .count-value {
    font-size: 24px;
    font-weight: 700;
    color: #3498db;
}

.count-item.pending .count-value {
    color: #e74c3c;
}

.count-item .count-label {
    font-size: 11px;
    color: #7f8c8d;
    text-transform: uppercase;
    margin-top: 2px;
}

/* Activity Feed */
.activity-feed {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.activity-feed h2 {
    margin: 0 0 20px;
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    padding-bottom: 10px;
    border-bottom: 2px solid #ecf0f1;
}

.activity-item {
    padding: 15px;
    border-left: 3px solid #3498db;
    background: #f8f9fa;
    margin-bottom: 10px;
    border-radius: 4px;
    transition: background 0.2s;
}

.activity-item:hover {
    background: #ecf0f1;
}

.activity-item.critical {
    border-left-color: #e74c3c;
}

.activity-item.warning {
    border-left-color: #f39c12;
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.activity-user {
    font-weight: 600;
    color: #2c3e50;
}

.activity-time {
    font-size: 12px;
    color: #95a5a6;
}

.activity-details {
    font-size: 14px;
    color: #7f8c8d;
}

.activity-link {
    margin-top: 8px;
}

.activity-link a {
    font-size: 13px;
    color: #3498db;
    text-decoration: none;
}

.activity-link a:hover {
    text-decoration: underline;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #95a5a6;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h3 {
    font-size: 20px;
    margin-bottom: 10px;
    color: #7f8c8d;
}

.empty-state p {
    font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .sections-grid {
        grid-template-columns: 1fr;
    }

    .cc-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
}
</style>

<div class="cc-container">
    <!-- Header -->
    <div class="cc-header">
        <div>
            <h1><i class="bi bi-command"></i> Command Center</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/admin/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Command Center</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="/command/search.php" class="btn btn-outline-primary">
                <i class="bi bi-search"></i> Search All
            </a>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="stats-grid">
        <div class="stat-card info">
            <i class="bi bi-inbox-fill stat-icon"></i>
            <div class="stat-label">Total Submissions</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>

        <div class="stat-card warning">
            <i class="bi bi-hourglass-split stat-icon"></i>
            <div class="stat-label">Unassigned</div>
            <div class="stat-value"><?= number_format($stats['unassigned']) ?></div>
        </div>

        <div class="stat-card success">
            <i class="bi bi-calendar-week stat-icon"></i>
            <div class="stat-label">Last 7 Days</div>
            <div class="stat-value"><?= number_format($stats['recent_7_days']) ?></div>
        </div>
    </div>

    <!-- Status Breakdown -->
    <?php if (!empty($stats['by_status'])): ?>
    <div class="stat-card">
        <h3 style="margin: 0 0 15px; font-size: 16px; color: #7f8c8d;">By Status</h3>
        <div class="status-breakdown">
            <?php foreach ($stats['by_status'] as $status): ?>
            <div class="status-item" style="border-left-color: <?= htmlspecialchars($status['status_color']) ?>">
                <div class="status-count" style="color: <?= htmlspecialchars($status['status_color']) ?>">
                    <?= number_format($status['count']) ?>
                </div>
                <div class="status-name"><?= htmlspecialchars($status['status_name']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sections Grid -->
    <h2 style="margin: 40px 0 20px; font-size: 22px; font-weight: 600; color: #2c3e50;">
        <i class="bi bi-folder-fill"></i> Active Sections
    </h2>

    <?php if (empty($sections)): ?>
    <div class="empty-state">
        <i class="bi bi-inbox"></i>
        <h3>No Submissions Yet</h3>
        <p>Sections will appear here once submissions are received.</p>
    </div>
    <?php else: ?>
    <div class="sections-grid">
        <?php foreach ($sections as $section): ?>
        <div class="section-card <?= $section['pending_count'] > 0 ? 'has-pending' : '' ?>"
             onclick="window.location.href='/command/section.php?slug=<?= urlencode($section['slug']) ?>'">
            <div class="section-header">
                <?php if ($section['icon']): ?>
                <div class="section-icon"><?= htmlspecialchars($section['icon']) ?></div>
                <?php endif; ?>
                <div class="section-title">
                    <h3><?= htmlspecialchars($section['name']) ?></h3>
                    <div class="section-slug"><?= htmlspecialchars($section['slug']) ?></div>
                </div>
            </div>
            <div class="section-counts">
                <div class="count-item">
                    <div class="count-value"><?= number_format($section['submission_count']) ?></div>
                    <div class="count-label">Total</div>
                </div>
                <div class="count-item pending">
                    <div class="count-value"><?= number_format($section['pending_count']) ?></div>
                    <div class="count-label">Pending</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Recent Activity Feed -->
    <h2 style="margin: 40px 0 20px; font-size: 22px; font-weight: 600; color: #2c3e50;">
        <i class="bi bi-activity"></i> Recent Activity
    </h2>

    <div class="activity-feed">
        <?php if (empty($recentActivity)): ?>
        <div class="empty-state">
            <i class="bi bi-clock-history"></i>
            <h3>No Recent Activity</h3>
            <p>Activity will appear here as submissions are processed.</p>
        </div>
        <?php else: ?>
        <?php foreach ($recentActivity as $activity): ?>
        <div class="activity-item <?= htmlspecialchars($activity['severity']) ?>">
            <div class="activity-header">
                <span class="activity-user">
                    <i class="bi bi-person-fill"></i> <?= htmlspecialchars($activity['user_name']) ?>
                </span>
                <span class="activity-time">
                    <i class="bi bi-clock"></i> <?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?>
                </span>
            </div>
            <div class="activity-details">
                <strong><?= ucfirst(str_replace('_', ' ', $activity['action'])) ?></strong>
                on <strong><?= htmlspecialchars($activity['display_id'] ?? 'submission') ?></strong>
                in <?= htmlspecialchars($activity['section_name']) ?>
                <?php if ($activity['notes']): ?>
                <br><em><?= htmlspecialchars($activity['notes']) ?></em>
                <?php endif; ?>
            </div>
            <div class="activity-link">
                <a href="/command/submission.php?id=<?= $activity['submission_id'] ?>">
                    View Submission <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

    });
</script>

        }
    });
</script>

<?php Hub\Layout::renderFooter($user, 'command'); ?>
</body>
</html>
