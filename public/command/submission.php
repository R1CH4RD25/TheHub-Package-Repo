<?php
/**
 * Command Center - Submission Detail View
 *
 * Full submission details with comments, attachments, and history.
 * Allows status changes, assignment, and adding comments.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Submission;
use Hub\CommandCenter;

// Require login
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$userId = $_SESSION['user_id'];
$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$submissionId = $_GET['id'] ?? null;

if (!$submissionId) {
    header('Location: /command/index.php');
    exit;
}

$submission = new Submission();
$cc = new CommandCenter();

$data = $submission->getById($submissionId, true); // Include drafts for admin view

if (!$data) {
    header('Location: /command/index.php');
    exit;
}

$comments = $submission->getComments($submissionId, true); // Include internal
$attachments = $submission->getAttachments($submissionId);
$history = $submission->getHistory($submissionId);
$statuses = $cc->getStatuses($data['section_id']);

$pageTitle = 'Submission ' . ($data['display_id'] ?? $data['id']);
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

.submission-container {
    flex: 1 0 auto;
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    width: 100%;
}

footer {
    flex-shrink: 0;
    margin-top: auto;
}

/* Submission Detail Styles */
.submission-header {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.submission-meta {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.submission-title h1 {
    margin: 0 0 10px;
    font-size: 32px;
    font-weight: 700;
    color: #2c3e50;
}

.submission-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.status-badge-large {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    color: white;
}

.priority-badge-large {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
}

.draft-badge {
    background: #95a5a6;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.submission-actions {
    display: flex;
    gap: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.info-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #3498db;
}

.info-label {
    font-size: 11px;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.info-value {
    font-size: 16px;
    font-weight: 600;
    color: #2c3e50;
}

.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.content-main,
.content-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #ecf0f1;
}

.card-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

.submission-data {
    display: grid;
    gap: 15px;
}

.data-field {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 4px;
}

.data-field-label {
    font-size: 12px;
    color: #7f8c8d;
    font-weight: 600;
    margin-bottom: 5px;
}

.data-field-value {
    font-size: 14px;
    color: #2c3e50;
}

/* Comments */
.comment {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 10px;
    border-left: 3px solid #3498db;
}

.comment.internal {
    background: #fff3cd;
    border-left-color: #ffc107;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.comment-author {
    font-weight: 600;
    color: #2c3e50;
}

.comment-time {
    font-size: 12px;
    color: #95a5a6;
}

.comment-text {
    color: #7f8c8d;
    line-height: 1.6;
}

.comment-form {
    margin-top: 15px;
}

/* Attachments */
.attachment {
    display: flex;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 10px;
    transition: background 0.2s;
}

.attachment:hover {
    background: #e9ecef;
}

.attachment-icon {
    font-size: 24px;
    margin-right: 12px;
    color: #3498db;
}

.attachment-info {
    flex: 1;
}

.attachment-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 2px;
}

.attachment-meta {
    font-size: 12px;
    color: #95a5a6;
}

/* History */
.history-item {
    padding: 12px;
    border-left: 3px solid #3498db;
    background: #f8f9fa;
    border-radius: 4px;
    margin-bottom: 10px;
}

.history-item.critical {
    border-left-color: #e74c3c;
}

.history-item.warning {
    border-left-color: #f39c12;
}

.history-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.history-action {
    font-weight: 600;
    color: #2c3e50;
}

.history-time {
    font-size: 12px;
    color: #95a5a6;
}

.history-details {
    font-size: 13px;
    color: #7f8c8d;
}

@media (max-width: 1024px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="submission-container">
    <!-- Header -->
    <div class="submission-header">
        <div class="submission-meta">
            <div class="submission-title">
                <h1>
                    <i class="bi bi-file-earmark-text"></i>
                    <?= htmlspecialchars($data['display_id'] ?? 'Submission #' . $data['id']) ?>
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/admin/">Admin</a></li>
                        <li class="breadcrumb-item"><a href="/command/">Command Center</a></li>
                        <li class="breadcrumb-item">
                            <a href="/command/section.php?slug=<?= urlencode($data['section_slug']) ?>">
                                <?= htmlspecialchars($data['section_name']) ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($data['display_id'] ?? $data['id']) ?></li>
                    </ol>
                </nav>
            </div>
            <div class="submission-actions">
                <button class="btn btn-primary" onclick="changeStatus()">
                    <i class="bi bi-arrow-repeat"></i> Change Status
                </button>
                <button class="btn btn-outline-secondary" onclick="assignTo()">
                    <i class="bi bi-person-plus"></i> Assign
                </button>
                <a href="/command/section.php?slug=<?= urlencode($data['section_slug']) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <div class="submission-badges">
            <span class="status-badge-large" style="background-color: <?= htmlspecialchars($data['status_color']) ?>">
                <?= htmlspecialchars($data['status_name']) ?>
            </span>
            <span class="priority-badge-large priority-<?= htmlspecialchars($data['priority']) ?>">
                <?= strtoupper($data['priority']) ?>
            </span>
            <?php if ($data['is_draft']): ?>
            <span class="draft-badge">DRAFT</span>
            <?php endif; ?>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Submitted By</div>
                <div class="info-value"><?= htmlspecialchars($data['submitted_by_name'] ?? 'Anonymous') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Assigned To</div>
                <div class="info-value"><?= htmlspecialchars($data['assigned_to_name'] ?? 'Unassigned') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Created</div>
                <div class="info-value"><?= date('M j, Y g:i A', strtotime($data['created_at'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Last Updated</div>
                <div class="info-value"><?= date('M j, Y g:i A', strtotime($data['updated_at'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Main Content -->
        <div class="content-main">
            <!-- Submission Data -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="bi bi-file-text"></i> Submission Details</h2>
                </div>
                <div class="submission-data">
                    <?php
                    $submissionData = $data['submission_data'] ?? [];
                    if (empty($submissionData)):
                    ?>
                    <p class="text-muted">No submission data available.</p>
                    <?php else: ?>
                        <?php foreach ($submissionData as $key => $value): ?>
                        <div class="data-field">
                            <div class="data-field-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></div>
                            <div class="data-field-value">
                                <?php
                                if (is_array($value)) {
                                    echo htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT));
                                } else {
                                    echo nl2br(htmlspecialchars($value));
                                }
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comments -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="bi bi-chat-dots"></i> Comments (<?= count($comments) ?>)</h2>
                </div>

                <?php if (empty($comments)): ?>
                <p class="text-muted">No comments yet.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                    <div class="comment <?= $comment['is_internal'] ? 'internal' : '' ?>">
                        <div class="comment-header">
                            <span class="comment-author">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($comment['user_name']) ?>
                                <?php if ($comment['is_internal']): ?>
                                <span class="badge bg-warning text-dark">Internal</span>
                                <?php endif; ?>
                            </span>
                            <span class="comment-time">
                                <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                            </span>
                        </div>
                        <div class="comment-text"><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Add Comment Form -->
                <div class="comment-form">
                    <form id="add-comment-form" onsubmit="addComment(event)">
                        <div class="mb-3">
                            <textarea class="form-control" id="comment-text" rows="3"
                                      placeholder="Add a comment..." required></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is-internal">
                                <label class="form-check-label" for="is-internal">
                                    Internal staff note (not visible to submitter)
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Add Comment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="content-sidebar">
            <!-- Attachments -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="bi bi-paperclip"></i> Attachments (<?= count($attachments) ?>)</h2>
                </div>

                <?php if (empty($attachments)): ?>
                <p class="text-muted">No attachments.</p>
                <?php else: ?>
                    <?php foreach ($attachments as $attachment): ?>
                    <div class="attachment">
                        <div class="attachment-icon">
                            <i class="bi bi-file-earmark"></i>
                        </div>
                        <div class="attachment-info">
                            <div class="attachment-name">
                                <?= htmlspecialchars($attachment['original_filename']) ?>
                            </div>
                            <div class="attachment-meta">
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
                <?php endif; ?>
            </div>

            <!-- History -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="bi bi-clock-history"></i> Activity History</h2>
                </div>

                <?php if (empty($history)): ?>
                <p class="text-muted">No activity history.</p>
                <?php else: ?>
                    <?php foreach ($history as $item): ?>
                    <div class="history-item <?= htmlspecialchars($item['severity']) ?>">
                        <div class="history-header">
                            <span class="history-action">
                                <?= ucfirst(str_replace('_', ' ', $item['action'])) ?>
                            </span>
                            <span class="history-time">
                                <?= date('M j, g:i A', strtotime($item['created_at'])) ?>
                            </span>
                        </div>
                        <div class="history-details">
                            by <strong><?= htmlspecialchars($item['user_name']) ?></strong>
                            <?php if ($item['notes']): ?>
                            <br><?= htmlspecialchars($item['notes']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const submissionId = <?= $submissionId ?>;

function changeStatus() {
    // TODO: Show modal with status options
    alert('Status change modal coming in Phase 2!');
}

function assignTo() {
    // TODO: Show modal with user list
    alert('Assignment modal coming in Phase 2!');
}

function addComment(event) {
    event.preventDefault();

    const commentText = document.getElementById('comment-text').value;
    const isInternal = document.getElementById('is-internal').checked;

    fetch('/command/api/comments.php', {
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

    }
}
</script>

<?php Hub\Layout::renderFooter($user, 'command'); ?>
</body>
</html>
</body>
</html>
