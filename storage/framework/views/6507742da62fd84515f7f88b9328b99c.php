<?php $__env->startSection('title', 'Activity Logs'); ?>

<?php $__env->startSection('content'); ?>
<div class="admin-tab active">
    <div class="tab-header">
        <div>
            <h1><i class="fas fa-list-alt"></i> Activity Logs</h1>
            <p class="text-muted">View all system activity and changes</p>
        </div>
        <div class="tab-actions">
            <button id="refreshLogs" class="btn btn-secondary">
                <i class="fas fa-sync"></i> Refresh
            </button>
            <button id="clearLogFilters" class="btn btn-secondary">Clear Filters</button>
        </div>
    </div>

    <div class="tab-content-scroll">
        <!-- Log Filters -->
        <div class="logs-filters" style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.875rem; font-weight: 600;">Action</label>
                    <select id="filterAction" style="width: 100%;">
                        <option value="">All Actions</option>
                        <option value="create">Create</option>
                        <option value="update">Update</option>
                        <option value="delete">Delete</option>
                        <option value="approve">Approve</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="grant_access">Grant Access</option>
                        <option value="revoke_access">Revoke Access</option>
                        <option value="login_success">Login Success</option>
                        <option value="login_failed">Login Failed</option>
                        <option value="logout">Logout</option>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.875rem; font-weight: 600;">Table</label>
                    <select id="filterTable" style="width: 100%;">
                        <option value="">All Tables</option>
                        <option value="users">Users</option>
                        <option value="user_global_roles">User Roles</option>
                        <option value="section_packages">Packages</option>
                        <option value="section_package_installs">Package Installs</option>
                        <option value="site_settings">Site Settings</option>
                        <option value="invitations">Invitations</option>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 0.875rem; font-weight: 600;">Limit</label>
                    <select id="filterLimit" style="width: 100%;">
                        <option value="50">50 records</option>
                        <option value="100" selected>100 records</option>
                        <option value="250">250 records</option>
                        <option value="500">500 records</option>
                    </select>
                </div>
            </div>

            <button id="applyLogFilters" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
        </div>

        <!-- Logs Table -->
        <div id="logsTable" class="data-table-container">
            <p class="text-center">Loading activity logs...</p>
        </div>

        <!-- Pagination -->
        <div id="logsPagination" style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1.5rem;">
            <!-- Pagination controls added by JavaScript -->
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
const csrfToken = '<?php echo e(csrf_token()); ?>';
let currentPage = 0;
let currentLimit = 100;
let currentFilters = {};
let totalLogs = 0;

// Load logs
function loadLogs() {
    const params = new URLSearchParams({
        limit: currentLimit,
        offset: currentPage * currentLimit,
        ...currentFilters
    });

    fetch(`/admin/logs/list?${params}`)
        .then(r => r.json())
        .then(data => {
            if (data.logs) {
                totalLogs = data.total;
                renderLogsTable(data.logs);
                renderPagination(data.total);
            } else {
                notyf.error('Failed to load logs');
            }
        })
        .catch(err => {
            notyf.error('Failed to load logs');
            console.error(err);
        });
}

function renderLogsTable(logs) {
    const actionColors = {
        'create': 'success',
        'update': 'info',
        'delete': 'danger',
        'approve': 'success',
        'activate': 'success',
        'deactivate': 'warning',
        'login_success': 'info',
        'login_failed': 'danger',
        'logout': 'secondary'
    };

    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 150px;">Timestamp</th>
                    <th style="width: 200px;">User</th>
                    <th style="width: 100px;">Action</th>
                    <th style="width: 150px;">Table</th>
                    <th>Details</th>
                    <th style="width: 100px;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                ${logs.length === 0 ? '<tr><td colspan="6" class="text-center">No logs found</td></tr>' : ''}
                ${logs.map(log => {
                    const changes = getChangeSummary(log);
                    return `
                        <tr>
                            <td style="font-size: 0.875rem;">${formatDateTime(log.created_at)}</td>
                            <td>
                                <strong>${log.user_name || 'System'}</strong>
                                ${log.user_email ? `<br><small style="color: #6c757d;">${log.user_email}</small>` : ''}
                            </td>
                            <td>
                                <span class="badge badge-${actionColors[log.action] || 'secondary'}">
                                    ${log.action}
                                </span>
                            </td>
                            <td style="font-family: monospace; font-size: 0.875rem;">${log.table_name || 'N/A'}</td>
                            <td style="font-size: 0.875rem;">
                                ${changes}
                                ${log.record_id ? `<br><small style="color: #6c757d;">Record ID: ${log.record_id}</small>` : ''}
                            </td>
                            <td style="font-family: monospace; font-size: 0.875rem;">${log.ip_address || 'N/A'}</td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('logsTable').innerHTML = html;
}

function getChangeSummary(log) {
    if (!log.new_values && !log.old_values) {
        return '<em>No details</em>';
    }

    try {
        const newVals = log.new_values ? JSON.parse(log.new_values) : {};
        const oldVals = log.old_values ? JSON.parse(log.old_values) : {};

        const changes = [];

        // Find changed fields
        Object.keys(newVals).forEach(key => {
            if (oldVals[key] !== newVals[key]) {
                changes.push(`<strong>${key}:</strong> ${oldVals[key] || 'null'} → ${newVals[key]}`);
            }
        });

        if (changes.length === 0 && Object.keys(newVals).length > 0) {
            // New record
            const fields = Object.keys(newVals).slice(0, 3).map(k => `${k}: ${newVals[k]}`).join(', ');
            return fields + (Object.keys(newVals).length > 3 ? '...' : '');
        }

        return changes.length > 0 ? changes.slice(0, 2).join('<br>') + (changes.length > 2 ? '<br>...' : '') : '<em>No changes</em>';
    } catch (e) {
        return '<em>Invalid data</em>';
    }
}

function formatDateTime(dateStr) {
    const date = new Date(dateStr);
    const dateOptions = { month: 'short', day: 'numeric' };
    const timeOptions = { hour: '2-digit', minute: '2-digit', hour12: true };
    return date.toLocaleDateString('en-US', dateOptions) + '<br>' +
           date.toLocaleTimeString('en-US', timeOptions);
}

function renderPagination(total) {
    const totalPages = Math.ceil(total / currentLimit);

    if (totalPages <= 1) {
        document.getElementById('logsPagination').innerHTML = '';
        return;
    }

    const html = `
        <button class="btn btn-sm btn-secondary" ${currentPage === 0 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <span style="padding: 0 1rem;">
            Page ${currentPage + 1} of ${totalPages} (${total} total)
        </span>
        <button class="btn btn-sm btn-secondary" ${currentPage >= totalPages - 1 ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
            Next <i class="fas fa-chevron-right"></i>
        </button>
    `;

    document.getElementById('logsPagination').innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    loadLogs();
}

// Apply filters
document.getElementById('applyLogFilters').addEventListener('click', function() {
    currentPage = 0;
    currentFilters = {};

    const action = document.getElementById('filterAction').value;
    const table = document.getElementById('filterTable').value;

    if (action) currentFilters.action = action;
    if (table) currentFilters.table = table;

    currentLimit = parseInt(document.getElementById('filterLimit').value);

    loadLogs();
});

// Clear filters
document.getElementById('clearLogFilters').addEventListener('click', function() {
    document.getElementById('filterAction').value = '';
    document.getElementById('filterTable').value = '';
    document.getElementById('filterLimit').value = '100';

    currentPage = 0;
    currentLimit = 100;
    currentFilters = {};

    loadLogs();
});

// Refresh logs
document.getElementById('refreshLogs').addEventListener('click', function() {
    loadLogs();
    notyf.success('Logs refreshed');
});

// Initial load
loadLogs();
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/woodson/thehub/resources/views/admin/logs.blade.php ENDPATH**/ ?>