<?php
/**
 * Management - Submission Detail View
 *
 * Full submission details with comments, attachments, and history.
 * Allows status changes, assignment, and adding comments.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Submission;
use Hub\ManagementCenter;
use Hub\SiteSettings;

// Require login and admin/super_admin role
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$userId = $_SESSION['user_id'];
$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$submissionId = $_GET['id'] ?? null;

if (!$submissionId) {
    header('Location: /management/index.php');
    exit;
}

$submission = new Submission();
$mc = new ManagementCenter();

$data = $submission->getById($submissionId, true); // Include drafts for admin view

if (!$data) {
    header('Location: /management/index.php');
    exit;
}

$comments = $submission->getComments($submissionId, true); // Include internal
$attachments = $submission->getAttachments($submissionId);
$history = $submission->getHistory($submissionId);
$statuses = $mc->getStatuses($data['section_id']);

// Get Management branding
$mgmtDisplayName = SiteSettings::get('mgmt_display_name', 'Management');
$mgmtIcon = SiteSettings::get('mgmt_icon', 'bi-kanban');

// Get sections for sidebar
if ($userRole === 'super_admin') {
    $sections = $mc->getSectionsWithCounts();
} else {
    $sections = $mc->getSectionsWithCounts($userId);
}

// Build navigation items for sidebar
$navItems = \Hub\Components\EnterpriseSidebar::buildManagementNavItems($sections, $data['section_slug']);

$pageTitle = $mgmtDisplayName . ' - ' . ($data['display_id'] ?? $data['id']);
$siteName = SiteSettings::get('site_name', 'The Hub');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteName) ?></title>

    <!-- MGMT BUNDLE (Enterprise Design) -->
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
        // Render Enterprise Sidebar
        \Hub\Components\EnterpriseSidebar::render($user, $userRole, [
            'context' => 'management',
            'title' => $mgmtDisplayName,
            'icon' => $mgmtIcon,
            'logo_url' => '/management/',
            'nav_items' => $navItems,
            'active_item' => $data['section_slug']
        ]);

        // Render Enterprise Header
        \Hub\Components\EnterpriseHeader::render($user, $userRole, [
            'context' => 'management',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => '/hub.php'],
                ['label' => $mgmtDisplayName, 'url' => '/management/'],
                ['label' => $data['section_name'], 'url' => '/management/section.php?slug=' . urlencode($data['section_slug'])],
                ['label' => $data['display_id'] ?? 'Submission #' . $data['id']]
            ],
            'show_notifications' => true
        ]);
        ?>

        <!-- Main Content Area -->
        <main class="admin-main">
            <div class="admin-page-header">
                <div class="admin-page-header-content">
                    <div class="admin-page-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div>
                        <div class="admin-page-subtitle"><?= htmlspecialchars($data['section_name']) ?></div>
                        <h1 class="admin-page-title"><?= htmlspecialchars($data['display_id'] ?? 'Submission #' . $data['id']) ?></h1>
                    </div>
                </div>
                <div class="admin-page-actions">
                    <button class="btn btn-sm btn-outline-secondary" onclick="changeStatus()">
                        <i class="bi bi-arrow-repeat"></i> Change Status
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="assignTo()">
                        <i class="bi bi-person-plus"></i> Assign
                    </button>
                    <a href="/management/section.php?slug=<?= urlencode($data['section_slug']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

            <!-- Status & Priority Badges -->
            <div class="nd-command-bar" style="gap: var(--space-3);">
                <span class="nd-pill nd-pill-lg" style="background-color: <?= htmlspecialchars($data['status_color']) ?>">
                    <?= htmlspecialchars($data['status_name']) ?>
                </span>
                <?php
                $priorityColors = [
                    'urgent' => 'var(--error)',
                    'high' => 'var(--warning)',
                    'normal' => 'var(--gray-600)',
                    'low' => 'var(--gray-400)'
                ];
                $priorityColor = $priorityColors[$data['priority']] ?? $priorityColors['normal'];
                ?>
                <span class="nd-chip" style="background-color: <?= $priorityColor ?>">
                    <?= strtoupper($data['priority']) ?> PRIORITY
                </span>
                <?php if ($data['is_draft']): ?>
                <span class="nd-chip" style="background-color: var(--gray-500)">DRAFT</span>
                <?php endif; ?>
            </div>

            <!-- Info Grid -->
            <div class="metric-cards-row" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="metric-card-simple">
                    <div class="metric-label">Submitted By</div>
                    <div class="metric-value-sm"><?= htmlspecialchars($data['submitted_by_name'] ?? 'Anonymous') ?></div>
                </div>
                <div class="metric-card-simple">
                    <div class="metric-label">Assigned To</div>
                    <div class="metric-value-sm"><?= htmlspecialchars($data['assigned_to_name'] ?? 'Unassigned') ?></div>
                </div>
                <div class="metric-card-simple">
                    <div class="metric-label">Created</div>
                    <div class="metric-value-sm"><?= date('M j, Y g:i A', strtotime($data['created_at'])) ?></div>
                </div>
                <div class="metric-card-simple">
                    <div class="metric-label">Last Updated</div>
                    <div class="metric-value-sm"><?= date('M j, Y g:i A', strtotime($data['updated_at'])) ?></div>
                </div>
            </div>

            <!-- Content Grid -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-4); margin-top: var(--space-4);">
                <!-- Main Content -->
                <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                    <!-- Submission Data -->
                    <div class="nd-card">
                        <div class="nd-card-header">
                            <h2 class="nd-card-title">
                                <i class="bi bi-file-text"></i> Submission Details
                            </h2>
                        </div>
                        <div class="nd-card-body">
                            <?php
                            $submissionData = $data['submission_data'] ?? [];
                            if (empty($submissionData)):
                            ?>
                            <p class="text-muted">No submission data available.</p>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                                    <?php foreach ($submissionData as $key => $value): ?>
                                    <div>
                                        <div style="font-size: var(--text-xs); font-weight: var(--font-semibold); color: var(--gray-600); margin-bottom: var(--space-1);">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?>
                                        </div>
                                        <div style="font-size: var(--text-sm); color: var(--gray-800);">
                                            <?php
                                            if (is_array($value)) {
                                                echo '<pre style="margin: 0; font-family: monospace; background: var(--gray-100); padding: var(--space-2); border-radius: 4px;">';
                                                echo htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT));
                                                echo '</pre>';
                                            } else {
                                                echo nl2br(htmlspecialchars($value));
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Comments -->
                    <div class="nd-card">
                        <div class="nd-card-header">
                            <h2 class="nd-card-title">
                                <i class="bi bi-chat-dots"></i> Comments (<?= count($comments) ?>)
                            </h2>
                        </div>
                        <div class="nd-card-body">
                            <?php if (empty($comments)): ?>
                            <p class="text-muted">No comments yet.</p>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                                    <?php foreach ($comments as $comment): ?>
                                    <div class="<?= $comment['is_internal'] ? 'nd-comment-internal' : 'nd-comment' ?>" style="padding: var(--space-3); background: <?= $comment['is_internal'] ? 'var(--warning-light)' : 'var(--gray-100)' ?>; border-radius: 4px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                            <span style="font-weight: var(--font-semibold); color: var(--gray-800);">
                                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($comment['user_name']) ?>
                                                <?php if ($comment['is_internal']): ?>
                                                <span class="nd-chip" style="background-color: var(--warning); margin-left: var(--space-2);">Internal</span>
                                                <?php endif; ?>
                                            </span>
                                            <span style="font-size: var(--text-xs); color: var(--gray-600);">
                                                <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                                            </span>
                                        </div>
                                        <div style="color: var(--gray-800);"><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Add Comment Form -->
                            <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--gray-300);">
                                <form id="add-comment-form" onsubmit="addComment(event)">
                                    <div style="margin-bottom: var(--space-3);">
                                        <textarea class="form-control" id="comment-text" rows="3"
                                                  placeholder="Add a comment..." required
                                                  style="width: 100%; padding: var(--space-2); border: 1px solid var(--gray-400); border-radius: 4px; font-family: inherit;"></textarea>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                                                <input type="checkbox" id="is-internal" style="cursor: pointer;">
                                                <span style="font-size: var(--text-sm); color: var(--gray-700);">
                                                    Internal staff note (not visible to submitter)
                                                </span>
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-send"></i> Add Comment
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                    <!-- Attachments -->
                    <div class="nd-card">
                        <div class="nd-card-header">
                            <h2 class="nd-card-title">
                                <i class="bi bi-paperclip"></i> Attachments (<?= count($attachments) ?>)
                            </h2>
                        </div>
                        <div class="nd-card-body">
                            <?php if (empty($attachments)): ?>
                            <p class="text-muted">No attachments.</p>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                                    <?php foreach ($attachments as $attachment): ?>
                                    <div style="display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2); background: var(--gray-100); border-radius: 4px;">
                                        <div style="font-size: 24px; color: var(--gray-600);">
                                            <i class="bi bi-file-earmark"></i>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--gray-800); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?= htmlspecialchars($attachment['original_filename']) ?>
                                            </div>
                                            <div style="font-size: var(--text-xs); color: var(--gray-600);">
                                                <?= number_format($attachment['file_size'] / 1024, 1) ?> KB •
                                                <?= htmlspecialchars($attachment['uploaded_by_name']) ?> •
                                                <?= date('M j, Y', strtotime($attachment['created_at'])) ?>
                                            </div>
                                        </div>
                                        <a href="/uploads/<?= htmlspecialchars($attachment['file_path']) ?>"
                                           class="btn btn-sm btn-outline-primary" download>
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- History -->
                    <div class="nd-card">
                        <div class="nd-card-header">
                            <h2 class="nd-card-title">
                                <i class="bi bi-clock-history"></i> Activity History
                            </h2>
                        </div>
                        <div class="nd-card-body">
                            <?php if (empty($history)): ?>
                            <p class="text-muted">No activity history.</p>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                                    <?php foreach ($history as $item): ?>
                                    <div style="padding: var(--space-2); border-left: 3px solid var(--gray-400); background: var(--gray-100); border-radius: 0 4px 4px 0;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-1);">
                                            <span style="font-weight: var(--font-semibold); color: var(--gray-800); font-size: var(--text-sm);">
                                                <?= ucfirst(str_replace('_', ' ', $item['action'])) ?>
                                            </span>
                                            <span style="font-size: var(--text-xs); color: var(--gray-600);">
                                                <?= date('M j, g:i A', strtotime($item['created_at'])) ?>
                                            </span>
                                        </div>
                                        <div style="font-size: var(--text-xs); color: var(--gray-700);">
                                            by <strong><?= htmlspecialchars($item['user_name']) ?></strong>
                                            <?php if ($item['notes']): ?>
                                            <br><?= htmlspecialchars($item['notes']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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


    <script nonce="<?php echo CSP_NONCE; ?>">
    const submissionId = <?= $submissionId ?>;

    function changeStatus() {
        // TODO: Show modal with status options
        alert('Status change modal coming soon!');
    }

    function assignTo() {
        // TODO: Show modal with user list
        alert('Assignment modal coming soon!');
    }

    function addComment(event) {
        event.preventDefault();

        const commentText = document.getElementById('comment-text').value;
        const isInternal = document.getElementById('is-internal').checked;

        fetch('/management/api/comments.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                submission_id: submissionId,
                comment_text: commentText,
                is_internal: isInternal ? 1 : 0
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to add comment'));
            }
        })
        .catch(err => alert('Network error: ' + err.message));
    }
    </script>
</body>
</html>
