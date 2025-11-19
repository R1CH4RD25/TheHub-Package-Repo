<?php
/**
 * Management - Section Submissions List
 *
 * DataTables-powered list view for all submissions in a section.
 * Supports filtering, sorting, bulk actions.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\ManagementCenter;
use Hub\Database;
use Hub\SiteSettings;

// Require login and admin/super_admin role
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$userId = $_SESSION['user_id'];
$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$slug = $_GET['slug'] ?? null;

if (!$slug) {
    header('Location: /management/index.php');
    exit;
}

// Get section details
$db = Database::getInstance();
$sql = "SELECT id, name, slug, mgmt_prefix, icon FROM sections WHERE slug = ? AND is_active = 1";
$section = $db->fetchOne($sql, [$slug]);

if (!$section) {
    header('Location: /management/index.php');
    exit;
}

$mc = new ManagementCenter();
$statuses = $mc->getStatuses($section['id']);

// Get Management branding
$mgmtDisplayName = SiteSettings::get('mgmt_display_name', 'Management');

$pageTitle = $mgmtDisplayName . ' - ' . $section['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php Hub\Layout::renderHead($pageTitle, 'command'); ?>
</head>
<body>

<?php Hub\Layout::renderHeader($user, $userRole, 'command'); ?>

<!-- Styles loaded from management.css -->
}
</style>

<div class="mgmt-section-container">
    <!-- Section Header with Back Button -->
    <div class="mgmt-section-header">
        <div class="mgmt-section-info">
            <a href="/management/" class="btn btn-outline-secondary me-3" title="Back to Section Selector">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <?php if ($section['icon']): ?>
            <div class="mgmt-section-icon-large"><i class="<?= htmlspecialchars($section['icon']) ?>"></i></div>
            <?php endif; ?>
                        <div class="mgmt-section-details">
                <div class="mgmt-section-subtitle"><?= htmlspecialchars($mgmtDisplayName) ?></div>
                <h1><?= htmlspecialchars($section['name']) ?></h1>
            </div>
        </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mgmt-filters-bar">
        <div class="mgmt-filters-row">
            <div class="mgmt-filter-group">
                <label>Status</label>
                <select id="filter-status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $status): ?>
                    <option value="<?= $status['id'] ?>"><?= htmlspecialchars($status['status_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mgmt-filter-group">
                <label>Priority</label>
                <select id="filter-priority" class="form-select">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div class="mgmt-filter-group">
                <label>Date From</label>
                <input type="date" id="filter-date-from" class="form-control">
            </div>
            <div class="mgmt-filter-group">
                <label>Date To</label>
                <input type="date" id="filter-date-to" class="form-control">
            </div>
            <div class="mgmt-filter-group">
                <label>Actions</label>
                <button id="btn-apply-filters" class="btn btn-primary w-100">
                    <i class="bi bi-funnel-fill"></i> Apply Filters
                </button>
            </div>
            <div class="mgmt-filter-group">
                <label>&nbsp;</label>
                <button id="btn-clear-filters" class="btn btn-secondary w-100">
                    <i class="bi bi-x-circle-fill"></i> Clear Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div class="mgmt-bulk-actions" id="bulk-actions-bar">
        <div class="mgmt-bulk-actions-label">
            <i class="bi bi-check-square"></i> <span id="selected-count">0</span> selected
        </div>
        <select id="bulk-action" class="form-select" style="width: auto;">
            <option value="">Bulk Actions...</option>
            <option value="assign">Assign To...</option>
            <option value="status">Change Status...</option>
            <option value="delete">Delete Selected</option>
        </select>
        <button id="btn-apply-bulk" class="btn btn-warning btn-sm">Apply</button>
        <button id="btn-cancel-bulk" class="btn btn-outline-secondary btn-sm">Cancel</button>
    </div>

    <!-- Submissions Table -->
    <div class="submissions-table-container">
        <table id="submissions-table" class="table table-hover" style="width:100%">
            <thead>
                <tr>
                    <th width="40"><input type="checkbox" id="select-all"></th>
                    <th>Display ID</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Submitted By</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by DataTables -->
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?php echo CSP_NONCE; ?>">
document.addEventListener('DOMContentLoaded', function() {
    const sectionId = <?= $section['id'] ?>;
    let selectedRows = new Set();

    // Initialize DataTable
    const table = $('#submissions-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/command/api/submissions.php',
            type: 'GET',
            data: function(d) {
                d.section_id = sectionId;
                d.status_id = $('#filter-status').val();
                d.priority = $('#filter-priority').val();
                d.date_from = $('#filter-date-from').val();
                d.date_to = $('#filter-date-to').val();
            }
        },
        columns: [
            {
                data: 'id',
                orderable: false,
                render: function(data) {
                    return `<input type="checkbox" class="row-select" value="${data}">`;
                }
            },
            {
                data: 'display_id',
                render: function(data, type, row) {
                    return `<a href="/command/submission.php?id=${row.id}" class="display-id">${data || row.id}</a>`;
                }
            },
            {
                data: 'status_name',
                render: function(data, type, row) {
                    return `<span class="mgmt-status-badge" style="background-color: ${row.status_color}">${data}</span>`;
                }
            },
            {
                data: 'priority',
                render: function(data) {
                    return `<span class="priority-badge priority-${data}">${data.toUpperCase()}</span>`;
                }
            },
            {
                data: 'submitted_by_name',
                render: function(data) {
                    return data || '<em>Anonymous</em>';
                }
            },
            {
                data: 'assigned_to_name',
                render: function(data) {
                    return data || '<em class="text-muted">Unassigned</em>';
                }
            },
            {
                data: 'created_at',
                render: function(data) {
                    const date = new Date(data);
                    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                }
            },
            {
                data: 'id',
                orderable: false,
                render: function(data) {
                    return `
                        <div class="mgmt-action-buttons">
                            <a href="/command/submission.php?id=${data}" class="btn btn-primary btn-sm" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button class="btn btn-outline-secondary btn-sm btn-assign" data-id="${data}" title="Assign">
                                <i class="bi bi-person-plus"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[6, 'desc']], // Sort by created_at DESC
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            emptyTable: 'No submissions found',
            zeroRecords: 'No matching submissions found'
        }
    });

    // Filter handlers
    $('#btn-apply-filters').on('click', function() {
        table.ajax.reload();
    });

    $('#btn-clear-filters').on('click', function() {
        $('#filter-status').val('');
        $('#filter-priority').val('');
        $('#filter-date-from').val('');
        $('#filter-date-to').val('');
        table.ajax.reload();
    });

    // Select all checkbox
    $('#select-all').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.row-select').prop('checked', isChecked);

        if (isChecked) {
            $('.row-select').each(function() {
                selectedRows.add($(this).val());
            });
        } else {
            selectedRows.clear();
        }

        updateBulkActions();
    });

    // Individual row selection
    $(document).on('change', '.row-select', function() {
        const id = $(this).val();
        if ($(this).prop('checked')) {
            selectedRows.add(id);
        } else {
            selectedRows.delete(id);
            $('#select-all').prop('checked', false);
        }
        updateBulkActions();
    });

    function updateBulkActions() {
        const count = selectedRows.size;
        $('#selected-count').text(count);

        if (count > 0) {
            $('#bulk-actions-bar').addClass('active');
        } else {
            $('#bulk-actions-bar').removeClass('active');
        }
    }

    // Bulk actions
    $('#btn-apply-bulk').on('click', function() {
        const action = $('#bulk-action').val();
        if (!action || selectedRows.size === 0) return;

        const ids = Array.from(selectedRows);

        if (action === 'delete') {
            if (confirm(`Delete ${ids.length} submission(s)?`)) {
                applyBulkAction(action, ids);
            }
        } else {
            // TODO: Show modal for assign/status change
            alert('Assign/Status change modals coming in Phase 2!');
        }
    });

    $('#btn-cancel-bulk').on('click', function() {
        $('.row-select').prop('checked', false);
        $('#select-all').prop('checked', false);
        selectedRows.clear();
        updateBulkActions();
    });

    function applyBulkAction(action, ids) {
        fetch('/command/api/submissions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: action, ids: ids})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                table.ajax.reload();
                selectedRows.clear();
                updateBulkActions();
                alert(data.message || 'Action completed successfully');
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => alert('Network error: ' + err.message));
    }
});
</script>

<?php Hub\Layout::renderFooter($user, 'command'); ?>
</body>
</html>
