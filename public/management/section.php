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
$mgmtIcon = SiteSettings::get('mgmt_icon', 'bi-kanban');

// Get sections for sidebar
if ($userRole === 'super_admin') {
    $sections = $mc->getSectionsWithCounts();
} else {
    $sections = $mc->getSectionsWithCounts($userId);
}

// Build navigation items for sidebar
$navItems = \Hub\Components\EnterpriseSidebar::buildManagementNavItems($sections, $slug);

$pageTitle = $mgmtDisplayName . ' - ' . $section['name'];
$siteName = SiteSettings::get('site_name', 'The Hub');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteName) ?></title>

    <!-- MGMT BUNDLE (Enterprise Design) -->
    <link rel="stylesheet" href="/assets/css/mgmt-bundle.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

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
            'active_item' => $slug
        ]);

        // Render Enterprise Header
        \Hub\Components\EnterpriseHeader::render($user, $userRole, [
            'context' => 'management',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => '/hub.php'],
                ['label' => $mgmtDisplayName, 'url' => '/management/'],
                ['label' => $section['name']]
            ],
            'show_notifications' => true
        ]);
        ?>

        <!-- Main Content Area -->
        <main class="admin-main">
            <div class="admin-page-header">
                <div class="admin-page-header-content">
                    <?php if ($section['icon']): ?>
                        <div class="admin-page-icon">
                            <i class="<?= htmlspecialchars($section['icon']) ?>"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="admin-page-subtitle"><?= htmlspecialchars($mgmtDisplayName) ?></div>
                        <h1 class="admin-page-title"><?= htmlspecialchars($section['name']) ?></h1>
                    </div>
                </div>
                <div class="admin-page-actions">
                    <a href="/management/" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="nd-command-bar">
                <div class="nd-command-bar-section">
                    <div class="nd-filter-group">
                        <label class="nd-filter-label">Status</label>
                        <select id="filter-status" class="nd-filter-select">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status['id'] ?>"><?= htmlspecialchars($status['status_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="nd-filter-group">
                        <label class="nd-filter-label">Priority</label>
                        <select id="filter-priority" class="nd-filter-select">
                            <option value="">All Priorities</option>
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="normal">Normal</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div class="nd-filter-group">
                        <label class="nd-filter-label">Date From</label>
                        <input type="date" id="filter-date-from" class="nd-filter-input">
                    </div>
                    <div class="nd-filter-group">
                        <label class="nd-filter-label">Date To</label>
                        <input type="date" id="filter-date-to" class="nd-filter-input">
                    </div>
                </div>
                <div class="nd-command-bar-section">
                    <button id="btn-apply-filters" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel-fill"></i> Apply
                    </button>
                    <button id="btn-clear-filters" class="btn btn-sm btn-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </button>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <div class="nd-bulk-actions" id="bulk-actions-bar" style="display: none;">
                <div class="nd-bulk-actions-inner">
                    <div class="nd-bulk-actions-label">
                        <i class="bi bi-check-square"></i> <span id="selected-count">0</span> selected
                    </div>
                    <select id="bulk-action" class="nd-filter-select" style="width: 200px;">
                        <option value="">Bulk Actions...</option>
                        <option value="assign">Assign To...</option>
                        <option value="status">Change Status...</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    <button id="btn-apply-bulk" class="btn btn-sm btn-warning">Apply</button>
                    <button id="btn-cancel-bulk" class="btn btn-sm btn-secondary">Cancel</button>
                </div>
            </div>

            <!-- Submissions Table -->
            <div class="nd-card">
                <table id="submissions-table" class="nd-table" style="width:100%">
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
document.addEventListener('DOMContentLoaded', function() {
    const sectionId = <?= $section['id'] ?>;
    let selectedRows = new Set();

    // Initialize DataTable
    const table = $('#submissions-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/management/api/submissions.php',
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
                    return `<a href="/management/submission.php?id=${row.id}" class="display-id">${data || row.id}</a>`;
                }
            },
            {
                data: 'status_name',
                render: function(data, type, row) {
                    return `<span class="nd-pill" style="background-color: ${row.status_color}">${data}</span>`;
                }
            },
            {
                data: 'priority',
                render: function(data) {
                    const colors = {
                        urgent: 'var(--error)',
                        high: 'var(--warning)',
                        normal: 'var(--gray-600)',
                        low: 'var(--gray-400)'
                    };
                    return `<span class="nd-chip" style="background-color: ${colors[data] || colors.normal}">${data.toUpperCase()}</span>`;
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
                        <div class="btn-group btn-group-sm">
                            <a href="/management/submission.php?id=${data}" class="btn btn-sm btn-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-secondary btn-assign" data-id="${data}" title="Assign">
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
        fetch('/management/api/submissions.php', {
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

    <!-- jQuery & DataTables -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Management JS -->
    <script src="/assets/js/management.js"></script>
</body>
</html>
