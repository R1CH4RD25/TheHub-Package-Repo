@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="admin-tab active">
    <div class="tab-header">
        <div>
            <h1><i class="fas fa-users"></i> User Management</h1>
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
            @if($isSuperAdmin)
                <button class="subtab-btn" data-subtab="role-management">Role Management</button>
            @endif
        </div>

        <!-- Active Users Subtab -->
        <div id="subtab-active-users" class="user-subtab active">
            <div id="usersTable" class="data-table-container">
                <p class="text-center">Loading active users...</p>
            </div>
        </div>

        <!-- Pending Users Subtab -->
        <div id="subtab-pending-users" class="user-subtab">
            <div id="pendingTable" class="data-table-container">
                <p class="text-center">Loading pending approvals...</p>
            </div>
        </div>

        <!-- Invitations Subtab -->
        <div id="subtab-invitations" class="user-subtab">
            <div id="invitationsTable" class="data-table-container">
                <p class="text-center">Loading invitations...</p>
            </div>
        </div>

        <!-- Role Management Subtab (Super Admin Only) -->
        @if($isSuperAdmin)
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
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
// CSRF token from Laravel
const csrfToken = '{{ csrf_token() }}';

// Subtab switching
document.querySelectorAll('.subtab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const subtab = this.getAttribute('data-subtab');

        // Update buttons
        document.querySelectorAll('.subtab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        // Update subtabs
        document.querySelectorAll('.user-subtab').forEach(s => s.classList.remove('active'));
        document.getElementById(`subtab-${subtab}`).classList.add('active');

        // Load data for the active subtab
        loadSubtabData(subtab);
    });
});

// Load subtab data
function loadSubtabData(subtab) {
    switch(subtab) {
        case 'active-users':
            loadActiveUsers();
            break;
        case 'pending-users':
            loadPendingUsers();
            break;
        case 'invitations':
            loadInvitations();
            break;
        case 'role-management':
            loadRoleManagement();
            break;
    }
}

// Load active users
function loadActiveUsers() {
    fetch('/admin/users/list')
        .then(r => r.json())
        .then(users => {
            renderUsersTable(users, 'usersTable');
        })
        .catch(err => {
            notyf.error('Failed to load users');
            console.error(err);
        });
}

// Load pending users
function loadPendingUsers() {
    fetch('/admin/users/list?pending=true')
        .then(r => r.json())
        .then(users => {
            renderPendingTable(users);
        })
        .catch(err => {
            notyf.error('Failed to load pending users');
            console.error(err);
        });
}

// Load invitations
function loadInvitations() {
    fetch('/admin/invitations')
        .then(r => r.json())
        .then(invites => {
            renderInvitationsTable(invites);
        })
        .catch(err => {
            notyf.error('Failed to load invitations');
            console.error(err);
        });
}

// Render users table
function renderUsersTable(users, containerId) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${users.map(u => `
                    <tr>
                        <td>${u.name}</td>
                        <td>${u.email}</td>
                        <td><span class="badge badge-${u.role}">${u.role}</span></td>
                        <td>${u.last_login ? new Date(u.last_login).toLocaleDateString() : 'Never'}</td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="changeUserRole(${u.id})">
                                <i class="fas fa-user-tag"></i> Change Role
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deactivateUser(${u.id})">
                                <i class="fas fa-ban"></i> Deactivate
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById(containerId).innerHTML = html;
}

// Render pending table
function renderPendingTable(users) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${users.length === 0 ? '<tr><td colspan="4" class="text-center">No pending approvals</td></tr>' : ''}
                ${users.map(u => `
                    <tr>
                        <td>${u.name}</td>
                        <td>${u.email}</td>
                        <td>${new Date(u.created_at).toLocaleDateString()}</td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="approveUser(${u.id})">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})">
                                <i class="fas fa-trash"></i> Reject
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('pendingTable').innerHTML = html;
}

// Render invitations table
function renderInvitationsTable(invites) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Sent</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${invites.length === 0 ? '<tr><td colspan="5" class="text-center">No invitations</td></tr>' : ''}
                ${invites.map(inv => `
                    <tr>
                        <td>${inv.email}</td>
                        <td><span class="badge badge-${inv.role}">${inv.role}</span></td>
                        <td>${new Date(inv.created_at).toLocaleDateString()}</td>
                        <td><span class="badge badge-${inv.used_at ? 'success' : 'warning'}">${inv.used_at ? 'Accepted' : 'Pending'}</span></td>
                        <td>
                            ${!inv.used_at ? `<button class="btn btn-sm btn-danger" onclick="revokeInvitation(${inv.id})">
                                <i class="fas fa-times"></i> Revoke
                            </button>` : ''}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('invitationsTable').innerHTML = html;
}

// User actions
function changeUserRole(userId) {
    // TODO: Show modal with role dropdown
    Swal.fire({
        title: 'Change User Role',
        input: 'select',
        inputOptions: {
            'staff': 'Staff',
            'maintenance': 'Maintenance',
            'admin': 'Admin',
            'super_admin': 'Super Admin'
        },
        showCancelButton: true,
        confirmButtonText: 'Update Role'
    }).then((result) => {
        if (result.isConfirmed) {
            updateUser(userId, 'change_role', { role: result.value });
        }
    });
}

function approveUser(userId) {
    Swal.fire({
        title: 'Approve User?',
        text: 'User will receive an approval email',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve'
    }).then((result) => {
        if (result.isConfirmed) {
            updateUser(userId, 'approve');
        }
    });
}

function deactivateUser(userId) {
    Swal.fire({
        title: 'Deactivate User?',
        text: 'User will lose access immediately',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, deactivate',
        confirmButtonColor: '#d32f2f'
    }).then((result) => {
        if (result.isConfirmed) {
            updateUser(userId, 'deactivate');
        }
    });
}

function deleteUser(userId) {
    Swal.fire({
        title: 'Delete User?',
        text: 'This cannot be undone',
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        confirmButtonColor: '#d32f2f'
    }).then((result) => {
        if (result.isConfirmed) {
            updateUser(userId, 'delete');
        }
    });
}

function updateUser(userId, action, extraData = {}) {
    fetch(`/admin/users/${userId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            action: action,
            csrf_token: csrfToken,
            ...extraData
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notyf.success(data.message);
            // Reload current subtab
            const activeSubtab = document.querySelector('.subtab-btn.active').getAttribute('data-subtab');
            loadSubtabData(activeSubtab);
        } else {
            notyf.error(data.error || 'Operation failed');
        }
    })
    .catch(err => {
        notyf.error('Request failed');
        console.error(err);
    });
}

function revokeInvitation(invitationId) {
    Swal.fire({
        title: 'Revoke Invitation?',
        text: 'Token will be invalidated',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, revoke'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/admin/invitations/${invitationId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notyf.success(data.message);
                    loadInvitations();
                } else {
                    notyf.error(data.error || 'Failed to revoke');
                }
            });
        }
    });
}

// Send invitation button
document.getElementById('sendInvitation').addEventListener('click', function() {
    Swal.fire({
        title: 'Send Invitation',
        html: `
            <input id="swal-email" class="swal2-input" placeholder="Email address">
            <select id="swal-role" class="swal2-input">
                <option value="staff">Staff</option>
                <option value="maintenance">Maintenance</option>
                <option value="admin">Admin</option>
            </select>
        `,
        showCancelButton: true,
        confirmButtonText: 'Send Invitation',
        preConfirm: () => {
            const email = document.getElementById('swal-email').value;
            const role = document.getElementById('swal-role').value;

            if (!email) {
                Swal.showValidationMessage('Email is required');
                return false;
            }

            return { email, role };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/admin/invitations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(result.value)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notyf.success(data.message);
                    loadInvitations();
                } else {
                    notyf.error(data.error || 'Failed to send invitation');
                }
            });
        }
    });
});

// Load active users on page load
loadActiveUsers();
</script>
@endpush
@endsection
