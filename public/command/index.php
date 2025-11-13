<?php
/**
 * Command Center - Section Selector
 *
 * Professional interface for selecting which section/department to manage.
 * Shows only sections the user has access to with real-time submission counts.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\CommandCenter;
use Hub\Database;

// Require login and admin/super_admin role
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$userId = $_SESSION['user_id'];
$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$isSuperAdmin = ($userRole === 'super_admin');

$cc = new CommandCenter();
$db = Database::getInstance();

// Get sections user has access to with submission counts
if ($isSuperAdmin) {
    // Super admins see all sections
    $sections = $cc->getSectionsWithCounts();
} else {
    // Other admins see only their assigned sections
    $sections = $cc->getSectionsWithCounts($userId);
}

// Get total stats across all accessible sections
$totalSubmissions = 0;
$totalPending = 0;
$totalUrgent = 0;

foreach ($sections as $section) {
    $totalSubmissions += $section['submission_count'];
    $totalPending += $section['pending_count'];

    // Count urgent submissions for this section
    $urgentResult = $db->fetchOne(
        "SELECT COUNT(*) as count FROM section_submissions
         WHERE section_id = ? AND priority = 'urgent' AND is_draft = 0",
        [$section['id']]
    );
    $totalUrgent += ($urgentResult['count'] ?? 0);
}

// DISABLED: Auto-redirect if user has access to only ONE section
// (Keeping selector visible for easier navigation testing)
/*
if (count($sections) === 1) {
    header('Location: /command/section/' . $sections[0]['slug']);
    exit;
}
*/

$pageTitle = 'Command Center - Select Section';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php Hub\Layout::renderHead($pageTitle, 'command'); ?>
</head>
<body>

<?php Hub\Layout::renderHeader($user, $userRole, 'command'); ?>

<style data-cache-bust="<?= time() ?>">
/* Command Center Layout Fix */
body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    margin: 0;
}

.navbar {
    flex-shrink: 0;
}

.selector-container {
    flex: 1 0 auto;
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
    width: 100%;
}

footer {
    flex-shrink: 0;
    margin-top: auto;
}

/* Professional Section Selector Styles */
.selector-header {
    text-align: center;
    margin-bottom: 40px;
}

.selector-header h1 {
    font-size: 32px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 10px;
}

.selector-header p {
    font-size: 16px;
    color: #7f8c8d;
}

/* Summary Stats Bar */
.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.summary-stat {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.summary-stat.pending {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.summary-stat.urgent {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.summary-stat .number {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 5px;
}

.summary-stat .label {
    font-size: 14px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Professional Table Selector */
.section-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.section-table table {
    width: 100%;
    border-collapse: collapse;
}

.section-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.section-table thead th {
    padding: 18px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.section-table tbody tr {
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s;
}

.section-table tbody tr:last-child {
    border-bottom: none;
}

.section-table tbody tr:hover {
    background-color: #f8f9fa;
}

.section-table tbody td {
    padding: 20px;
    vertical-align: middle;
}

.section-icon {
    font-size: 24px;
    width: 40px;
    text-align: center;
}

.section-name {
    font-weight: 600;
    font-size: 16px;
    color: #2c3e50;
}

.section-slug {
    font-size: 13px;
    color: #7f8c8d;
    margin-top: 4px;
}

.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-align: center;
    min-width: 45px;
}

.badge.total {
    background: #e3f2fd;
    color: #1976d2;
}

.badge.pending {
    background: #fff3e0;
    color: #f57c00;
}

.badge.urgent {
    background: #ffebee;
    color: #c62828;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.btn-enter {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
    display: inline-block;
}

.btn-enter:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
    color: white;
    text-decoration: none;
}

.btn-enter i {
    margin-left: 8px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #7f8c8d;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h3 {
    font-size: 24px;
    margin-bottom: 10px;
}

.empty-state p {
    font-size: 16px;
}
</style>

<div class="selector-container">
    <!-- Header -->
    <div class="selector-header">
        <h1><i class="bi bi-command"></i> Command Center</h1>
        <p>Select a section to manage submissions, review reports, and track workflows</p>
    </div>

    <!-- Summary Stats -->
    <?php if (!empty($sections)): ?>
    <div class="summary-stats">
        <div class="summary-stat">
            <div class="number"><?php echo $totalSubmissions; ?></div>
            <div class="label">Total Submissions</div>
        </div>
        <div class="summary-stat pending">
            <div class="number"><?php echo $totalPending; ?></div>
            <div class="label">Pending Review</div>
        </div>
        <div class="summary-stat urgent">
            <div class="number"><?php echo $totalUrgent; ?></div>
            <div class="label">Urgent Priority</div>
        </div>
    </div>

    <!-- Section Table -->
    <div class="section-table">
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;"></th>
                    <th>Section Name</th>
                    <th style="text-align: center; width: 120px;">Total</th>
                    <th style="text-align: center; width: 120px;">Pending</th>
                    <th style="text-align: center; width: 120px;">Urgent</th>
                    <th style="text-align: right; width: 150px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $section):
                    // Get urgent count for this section
                    $urgentCount = $db->fetchValue(
                        "SELECT COUNT(*) FROM section_submissions
                         WHERE section_id = ? AND priority = 'urgent' AND is_draft = 0 AND status_id = 1",
                        [$section['id']]
                    );
                ?>
                <tr>
                    <td class="section-icon">
                        <?php echo $section['icon'] ?: '📁'; ?>
                    </td>
                    <td>
                        <div class="section-name"><?php echo htmlspecialchars($section['name']); ?></div>
                        <div class="section-slug"><?php echo htmlspecialchars($section['slug']); ?></div>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge total"><?php echo $section['submission_count']; ?></span>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge pending"><?php echo $section['pending_count']; ?></span>
                    </td>
                    <td style="text-align: center;">
                        <?php if ($urgentCount > 0): ?>
                            <span class="badge urgent"><?php echo $urgentCount; ?></span>
                        <?php else: ?>
                            <span class="badge total">0</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <a href="/command/section/<?php echo htmlspecialchars($section['slug']); ?>" class="btn-enter">
                            Enter <i class="bi bi-arrow-right"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <!-- Empty State -->
    <div class="section-table">
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h3>No Sections Assigned</h3>
            <p>You don't have access to any sections yet. Contact your administrator to request access.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php Hub\Layout::renderFooter($user, 'command'); ?>
</body>
</html>
