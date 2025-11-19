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

// Require login
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

$pageTitle = $mgmtDisplayName . ' - ' . ($data['display_id'] ?? $data['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php Hub\Layout::renderHead($pageTitle, 'command'); ?>
</head>
<body>

<!-- Styles loaded from management.css -->
<?php Hub\Layout::renderHeader($user, $userRole, 'command'); ?>


<div class="mgmt-submission-container">
    <!-- Header -->
    <div class="mgmt-submission-header">
        <div class="mgmt-submission-meta">
            <div class="mgmt-submission-title">
                <h1>
                    <i class="bi bi-file-earmark-text"></i>
                    <?= htmlspecialchars($data['display_id'] ?? 'Submission #' . $data['id']) ?>
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/admin/">Admin</a></li>
                        <li class="breadcrumb-item"><a href="/management/">Management</a></li>
                        <li class="breadcrumb-item">
                            <a href="/management/section.php?slug=<?= urlencode($data['section_slug']) ?>">
                                <?= htmlspecialchars($data['section_name']) ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($data['display_id'] ?? $data['id']) ?></li>
                    </ol>
                </nav>
            </div>
            <div class="mgmt-submission-actions">
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

        <div class="mgmt-submission-badges">
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
                <div class="mgmt-submission-data">
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
                        <div class="mgmt-comment-header">
                            <span class="mgmt-comment-author">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($comment['user_name']) ?>
                                <?php if ($comment['is_internal']): ?>
                                <span class="badge bg-warning text-dark">Internal</span>
                                <?php endif; ?>
                            </span>
                            <span class="mgmt-comment-time">
                                <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                            </span>
                        </div>
                        <div class="mgmt-comment-text"><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Add Comment Form -->
                <div class="mgmt-comment-form">
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
                        <div class="mgmt-attachment-icon">
                            <i class="bi bi-file-earmark"></i>
                        </div>
                        <div class="mgmt-attachment-info">
                            <div class="mgmt-attachment-name">
                                <?= htmlspecialchars($attachment['original_filename']) ?>
                            </div>
                            <div class="mgmt-attachment-meta">
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
                    <div class="mgmt-history-item <?= htmlspecialchars($item['severity']) ?>">
                        <div class="mgmt-history-header">
                            <span class="mgmt-history-action">
                                <?= ucfirst(str_replace('_', ' ', $item['action'])) ?>
                            </span>
                            <span class="mgmt-history-time">
                                <?= date('M j, g:i A', strtotime($item['created_at'])) ?>
                            </span>
                        </div>
                        <div class="mgmt-history-details">
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

<script nonce="<?php echo CSP_NONCE; ?>">
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
