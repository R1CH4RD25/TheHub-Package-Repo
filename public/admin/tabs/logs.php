<div id="tab-logs" class="admin-tab">
    <div class="tab-header">
        <h1>Activity Logs</h1>
        <div class="tab-actions">
            <button id="refreshLogs" class="btn btn-secondary">Refresh</button>
            <button id="clearLogFilters" class="btn btn-secondary">Clear Filters</button>
        </div>
    </div>

    <div class="tab-content-scroll">
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
                    <option value="sections">Packages</option>
                    <option value="section_access">Package Access</option>
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
        <button id="applyLogFilters" class="btn btn-primary" style="margin-top: 1rem;">Apply Filters</button>
    </div>

    <div id="logsTable" class="data-table-container">
        Loading...
    </div>

    <div id="logsPagination" style="display: flex; justify-content: center; gap: 1rem; margin-top: 1.5rem;">
        <!-- Pagination will be added by JavaScript -->
    </div>
    </div><!-- end tab-content-scroll -->
</div><!-- end tab-logs -->
