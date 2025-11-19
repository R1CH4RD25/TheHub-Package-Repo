<div id="tab-users" class="admin-tab active">
    <div class="nd-page-header">
        <div>
            <h1>User Management</h1>
            <p class="text-muted">Manage users, invitations, and roles</p>
        </div>
        <button id="sendInvitation" class="btn btn-primary">
            <i class="fas fa-plus"></i> Send Invitation
        </button>
    </div>

    <div class="tab-content-scroll">
        <!-- User Sub-tabs -->
        <div class="user-subtabs">
            <button class="subtab-btn active" data-subtab="active-users">Active Users</button>
            <button class="subtab-btn" data-subtab="pending-users">Pending Approvals</button>
            <button class="subtab-btn" data-subtab="invitations">Invitations</button>
            <?php if ($isSuperAdmin): ?>
            <button class="subtab-btn" data-subtab="role-management">Role Management</button>
            <?php endif; ?>
        </div>

        <!-- Active Users Subtab -->
        <div id="subtab-active-users" class="user-subtab active">
            <!-- Filter Toolbar -->
            <div class="table-toolbar">
                <div class="toolbar-left">
                    <div class="nd-chip active" data-filter="all">All Users</div>
                    <div class="nd-chip" data-filter="active">Active</div>
                    <div class="nd-chip" data-filter="inactive">Inactive</div>
                    <?php if ($isSuperAdmin): ?>
                    <div class="nd-chip" data-filter="super_admin">Super Admin</div>
                    <div class="nd-chip" data-filter="admin">Admin</div>
                    <?php endif; ?>
                    <div class="nd-chip" data-filter="staff">Staff</div>
                </div>
                <div class="toolbar-right">
                    <button class="btn-icon" title="Export Users" onclick="exportUsers()">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>

            <div id="usersTable" class="data-table-container">
                Loading...
            </div>
        </div>

        <!-- Pending Users Subtab -->
        <div id="subtab-pending-users" class="user-subtab">
            <div id="pendingTable" class="data-table-container">
                Loading...
            </div>
        </div>

        <!-- Invitations Subtab -->
        <div id="subtab-invitations" class="user-subtab">
            <div id="invitationsTable" class="data-table-container">
                Loading...
            </div>
        </div>

        <!-- Role Management Subtab (Super Admin Only) -->
        <?php if ($isSuperAdmin): ?>
        <div id="subtab-role-management" class="user-subtab">
            <div class="role-management">
                <p class="info-text">
                    <strong>🎭 Role Management:</strong> Enable or disable roles system-wide.
                    Inactive roles are hidden from all dropdowns, section access, and user assignment forms.
                    <span style="color: #d32f2f; font-weight: 600;">⚠️ Cannot disable Super Admin or Staff roles (core system roles).</span>
                </p>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;">Status</th>
                                <th style="min-width: 200px;">Role</th>
                                <th style="min-width: 250px;">Description</th>
                                <th style="width: 100px; text-align: center;">Hierarchy</th>
                                <th style="width: 120px; text-align: center;">Active Users</th>
                                <th style="min-width: 200px;">Notes</th>
                                <th style="width: 120px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rolesManagementTableBody">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    Loading roles...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="info-panel" style="margin-top: 2rem; padding: 1.5rem; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 8px;">
                    <h3 style="margin: 0 0 1rem 0; color: #1e40af; font-size: 1.1rem;">💡 How to Add New Roles</h3>
                    <p style="margin: 0 0 0.75rem 0;">Roles are defined in the codebase for security and consistency. To add a new role:</p>
                    <ol style="margin: 0; padding-left: 1.5rem;">
                        <li style="margin-bottom: 0.5rem;">Edit <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">/src/Roles.php</code> and add your role to the <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">getAll()</code> method</li>
                        <li style="margin-bottom: 0.5rem;">Set a unique <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">value</code>, descriptive <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">label</code>, and appropriate <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">hierarchy</code> (1-100)</li>
                        <li style="margin-bottom: 0.5rem;">Run database migration: <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">php cli/migrate.php</code></li>
                        <li>Refresh this page - your new role will appear above and can be assigned to users</li>
                    </ol>
                    <p style="margin: 1rem 0 0 0; font-size: 0.9rem; color: #64748b;"><strong>Note:</strong> Higher hierarchy numbers = more permissions. Super Admin (100) has the highest level.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div><!-- end tab-content-scroll -->
</div><!-- end tab-users -->
