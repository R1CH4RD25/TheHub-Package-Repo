/**
 * Organization Roles Management JavaScript
 * Handles UI for creating, editing, and managing org roles
 */

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Load org roles when tab is activated
    const orgRolesTab = document.querySelector('[data-subtab="org-roles"]');
    if (orgRolesTab) {
        orgRolesTab.addEventListener('click', loadOrgRoles);
    }
    
    // Create role button
    const createBtn = document.getElementById('createOrgRoleBtn');
    if (createBtn) {
        createBtn.addEventListener('click', showCreateRoleModal);
    }
});

/**
 * Load all organization roles
 */
async function loadOrgRoles() {
    const tbody = document.getElementById('orgRolesTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px;">Loading...</td></tr>';
    
    try {
        const response = await fetch('/api/org-roles.php?action=list');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load roles');
        }
        
        renderOrgRolesTable(data.roles);
    } catch (error) {
        console.error('Error loading org roles:', error);
        showMessage('Failed to load organization roles: ' + error.message, 'error');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px; color: #ef4444;">Error loading roles</td></tr>';
    }
}

/**
 * Render organization roles table
 */
function renderOrgRolesTable(roles) {
    const tbody = document.getElementById('orgRolesTableBody');
    if (!tbody) return;
    
    if (roles.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px;">
                    No organization roles yet. Click "New Role" to create one.
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = roles.map(role => `
        <tr data-role-id="${role.id}">
            <td>
                <strong>${escapeHtml(role.name)}</strong>
            </td>
            <td>
                ${role.description ? escapeHtml(role.description) : '<em style="color: #94a3b8;">No description</em>'}
            </td>
            <td style="text-align: center;">
                <span class="badge ${role.user_count > 0 ? 'badge-success' : 'badge-secondary'}">
                    ${role.user_count}
                </span>
            </td>
            <td>
                ${renderGoogleGroups(role)}
            </td>
            <td style="text-align: center;">
                <button class="btn-icon" onclick="manageRoleUsers(${role.id}, '${escapeHtml(role.name)}')" title="Manage Users">
                    <i class="fas fa-users"></i>
                </button>
                <button class="btn-icon" onclick="manageCloudGroups(${role.id}, '${escapeHtml(role.name)}')" title="Cloud Groups">
                    <i class="fas fa-users-cog"></i>
                </button>
                <button class="btn-icon" onclick="editOrgRole(${role.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon btn-danger" onclick="deleteOrgRole(${role.id}, '${escapeHtml(role.name)}', ${role.user_count})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Render Cloud Groups badges (Google + Microsoft)
 */
function renderGoogleGroups(role) {
    const groups = [];
    
    // Add Google Groups
    if (role.google_groups && role.google_groups.length > 0) {
        groups.push(...role.google_groups.map(group => 
            `<span class="badge badge-info" style="margin: 2px; font-size: 0.85rem;">
                <i class="fab fa-google"></i> ${escapeHtml(group)}
            </span>`
        ));
    }
    
    // Add Microsoft Groups
    if (role.microsoft_groups && role.microsoft_groups.length > 0) {
        groups.push(...role.microsoft_groups.map(group => 
            `<span class="badge badge-primary" style="margin: 2px; font-size: 0.85rem;">
                <i class="fab fa-microsoft"></i> ${escapeHtml(group.azure_group_name || group.azure_group_id)}
            </span>`
        ));
    }
    
    return groups.length > 0 ? groups.join(' ') : '<em style="color: #94a3b8; font-size: 0.9rem;">None</em>';
}

/**
 * Show create role modal
 */
function showCreateRoleModal() {
    const modal = createModal('Create Organization Role', `
        <form id="createOrgRoleForm">
            <div class="form-group">
                <label for="roleName">Role Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="roleName" name="name" class="form-control" required 
                       placeholder="e.g., Principal, Maintenance Director">
            </div>
            
            <div class="form-group">
                <label for="roleDescription">Description</label>
                <textarea id="roleDescription" name="description" class="form-control" rows="3"
                          placeholder="Describe this role's responsibilities..."></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Role
                </button>
            </div>
        </form>
    `);
    
    document.getElementById('createOrgRoleForm').addEventListener('submit', handleCreateRole);
}

/**
 * Handle create role form submission
 */
async function handleCreateRole(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('/api/org-roles.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to create role');
        }
        
        showMessage('Organization role created successfully', 'success');
        closeModal();
        loadOrgRoles();
    } catch (error) {
        console.error('Error creating role:', error);
        showMessage('Failed to create role: ' + error.message, 'error');
    }
}

/**
 * Edit organization role
 */
async function editOrgRole(roleId) {
    // Fetch current role data
    const response = await fetch('/api/org-roles.php?action=list');
    const data = await response.json();
    const role = data.roles.find(r => r.id === roleId);
    
    if (!role) {
        showMessage('Role not found', 'error');
        return;
    }
    
    const modal = createModal('Edit Organization Role', `
        <form id="editOrgRoleForm">
            <input type="hidden" name="id" value="${role.id}">
            
            <div class="form-group">
                <label for="editRoleName">Role Name <span style="color: #ef4444;">*</span></label>
                <input type="text" id="editRoleName" name="name" class="form-control" required 
                       value="${escapeHtml(role.name)}">
            </div>
            
            <div class="form-group">
                <label for="editRoleDescription">Description</label>
                <textarea id="editRoleDescription" name="description" class="form-control" rows="3">${escapeHtml(role.description || '')}</textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    `);
    
    document.getElementById('editOrgRoleForm').addEventListener('submit', handleEditRole);
}

/**
 * Handle edit role form submission
 */
async function handleEditRole(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('/api/org-roles.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to update role');
        }
        
        showMessage('Organization role updated successfully', 'success');
        closeModal();
        loadOrgRoles();
    } catch (error) {
        console.error('Error updating role:', error);
        showMessage('Failed to update role: ' + error.message, 'error');
    }
}

/**
 * Delete organization role
 */
async function deleteOrgRole(roleId, roleName, userCount) {
    if (userCount > 0) {
        showMessage(`Cannot delete "${roleName}" - it has ${userCount} assigned user(s). Remove users first.`, 'error');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete the role "${roleName}"?`)) {
        return;
    }
    
    try {
        const response = await fetch('/api/org-roles.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${roleId}`
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to delete role');
        }
        
        showMessage('Organization role deleted successfully', 'success');
        loadOrgRoles();
    } catch (error) {
        console.error('Error deleting role:', error);
        showMessage('Failed to delete role: ' + error.message, 'error');
    }
}

/**
 * Manage users assigned to a role
 */
async function manageRoleUsers(roleId, roleName) {
    // Fetch all users and current role assignments
    const [usersResponse, roleUsersResponse] = await Promise.all([
        fetch('/api/users.php?action=list'),
        fetch(`/api/org-roles.php?action=users&role_id=${roleId}`)
    ]);
    
    const usersData = await usersResponse.json();
    const roleUsersData = await roleUsersResponse.json();
    
    const allUsers = usersData.users || [];
    const assignedUserIds = roleUsersData.users.map(u => u.id);
    
    const modal = createModal(`Manage Users - ${roleName}`, `
        <div style="margin-bottom: 1rem;">
            <input type="text" id="userSearchInput" class="form-control" 
                   placeholder="Search users..." onkeyup="filterUserList()">
        </div>
        
        <div style="max-height: 400px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
            <div id="userSelectionList">
                ${allUsers.map(user => `
                    <label style="display: flex; align-items: center; padding: 0.5rem; cursor: pointer; border-radius: 4px; margin-bottom: 0.5rem;" 
                           class="user-selection-item" data-user-name="${escapeHtml(user.name).toLowerCase()}">
                        <input type="checkbox" value="${user.id}" 
                               ${assignedUserIds.includes(user.id) ? 'checked' : ''}
                               style="margin-right: 0.75rem;">
                        <img src="${user.picture || '/assets/images/default-avatar.png'}" 
                             style="width: 32px; height: 32px; border-radius: 50%; margin-right: 0.75rem;">
                        <div>
                            <div style="font-weight: 500;">${escapeHtml(user.name)}</div>
                            <div style="font-size: 0.85rem; color: #64748b;">${escapeHtml(user.email)}</div>
                        </div>
                    </label>
                `).join('')}
            </div>
        </div>
        
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveRoleUsers(${roleId})">
                <i class="fas fa-save"></i> Save Assignments
            </button>
        </div>
    `, 'large');
}

/**
 * Filter user list
 */
function filterUserList() {
    const search = document.getElementById('userSearchInput').value.toLowerCase();
    const items = document.querySelectorAll('.user-selection-item');
    
    items.forEach(item => {
        const name = item.dataset.userName;
        item.style.display = name.includes(search) ? 'flex' : 'none';
    });
}

/**
 * Save role user assignments
 */
async function saveRoleUsers(roleId) {
    const checkboxes = document.querySelectorAll('#userSelectionList input[type="checkbox"]:checked');
    const userIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    try {
        const response = await fetch('/api/org-roles.php?action=assign-users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role_id: roleId, user_ids: userIds })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to assign users');
        }
        
        showMessage(result.message || 'Users assigned successfully', 'success');
        closeModal();
        loadOrgRoles();
    } catch (error) {
        console.error('Error assigning users:', error);
        showMessage('Failed to assign users: ' + error.message, 'error');
    }
}

/**
 * Manage Cloud Groups (Google + Microsoft)
 */
async function manageCloudGroups(roleId, roleName) {
    const response = await fetch('/api/org-roles.php?action=list');
    const data = await response.json();
    const role = data.roles.find(r => r.id === roleId);
    
    const modal = createModal(`Cloud Identity Groups - ${roleName}`, `
        <p class="info-text">
            <i class="fas fa-cloud"></i> 
            Users in these cloud identity groups will automatically be assigned this role when they log in.
        </p>
        
        <!-- Google Groups Section -->
        <div class="cloud-groups-section" style="margin-bottom: 2rem;">
            <h4 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <i class="fab fa-google"></i> Google Workspace Groups
            </h4>
            <div id="googleGroupsList" style="margin-bottom: 1rem;">
                ${role.google_groups && role.google_groups.length > 0 ? role.google_groups.map(group => `
                    <div class="badge badge-info" style="margin: 4px; padding: 8px 12px; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px;">
                        ${escapeHtml(group)}
                        <button onclick="removeGoogleGroup(${roleId}, '${escapeHtml(group)}')" 
                                style="background: none; border: none; color: white; cursor: pointer; padding: 0; margin: 0;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `).join('') : '<em style="color: #94a3b8;">No Google Groups mapped</em>'}
            </div>
            
            <form id="addGoogleGroupForm" style="margin-top: 1rem;">
                <div class="form-group">
                    <label>Add Google Group</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="email" id="googleGroupEmail" class="form-control" 
                               placeholder="group@yourdomain.com">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Microsoft Groups Section -->
        <div class="cloud-groups-section" style="margin-bottom: 2rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
            <h4 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <i class="fab fa-microsoft"></i> Microsoft Azure AD Groups
            </h4>
            <div id="microsoftGroupsList" style="margin-bottom: 1rem;">
                ${role.microsoft_groups && role.microsoft_groups.length > 0 ? role.microsoft_groups.map(group => `
                    <div class="badge badge-primary" style="margin: 4px; padding: 8px 12px; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px;">
                        ${escapeHtml(group.azure_group_name || group.azure_group_id)}
                        <button onclick="removeMicrosoftGroup(${roleId}, '${group.id}')" 
                                style="background: none; border: none; color: white; cursor: pointer; padding: 0; margin: 0;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `).join('') : '<em style="color: #94a3b8;">No Microsoft Groups mapped</em>'}
            </div>
            
            <form id="addMicrosoftGroupForm" style="margin-top: 1rem;">
                <div class="form-group">
                    <label>Add Microsoft Group</label>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <input type="text" id="azureGroupId" class="form-control" 
                               placeholder="Azure Group ID (GUID: 12345678-1234-1234-1234-123456789abc)" 
                               pattern="[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}">
                        <input type="text" id="azureGroupName" class="form-control" 
                               placeholder="Display Name (optional)">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                    <small class="form-text text-muted">
                        Find Group Object ID in Azure Portal → Azure Active Directory → Groups
                    </small>
                </div>
            </form>
        </div>
        
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
        </div>
    `);
    
    // Google Groups form handler
    document.getElementById('addGoogleGroupForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('googleGroupEmail').value;
        if (email) {
            await addGoogleGroup(roleId, email);
        }
    });
    
    // Microsoft Groups form handler
    document.getElementById('addMicrosoftGroupForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const groupId = document.getElementById('azureGroupId').value;
        const groupName = document.getElementById('azureGroupName').value;
        if (groupId) {
            await addMicrosoftGroup(roleId, groupId, groupName);
        }
    });
}

/**
 * Manage Google Groups mappings (legacy - keeping for compatibility)
 */
async function manageGoogleGroups(roleId, roleName) {
    // Redirect to the unified cloud groups manager
    await manageCloudGroups(roleId, roleName);
}

/**
 * Add Google Group mapping
 */
async function addGoogleGroup(roleId, googleGroupEmail) {
    try {
        const response = await fetch('/api/org-roles.php?action=add-google-group', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role_id: roleId, google_group_email: googleGroupEmail })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to add Google Group');
        }
        
        showMessage('Google Group mapped successfully', 'success');
        closeModal();
        loadOrgRoles();
    } catch (error) {
        console.error('Error adding Google Group:', error);
        showMessage('Failed to add Google Group: ' + error.message, 'error');
    }
}

/**
 * Add Microsoft Group mapping
 */
async function addMicrosoftGroup(roleId, azureGroupId, azureGroupName) {
    try {
        const response = await fetch('/api/org-roles.php?action=add-microsoft-group', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                role_id: roleId, 
                azure_group_id: azureGroupId,
                azure_group_name: azureGroupName 
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to add Microsoft Group');
        }
        
        showMessage('Microsoft Group mapped successfully', 'success');
        closeModal();
        loadOrgRoles();
    } catch (error) {
        console.error('Error adding Microsoft Group:', error);
        showMessage('Failed to add Microsoft Group: ' + error.message, 'error');
    }
}

/**
 * Remove Microsoft Group mapping
 */
async function removeMicrosoftGroup(roleId, groupId) {
    if (!confirm('Remove this Microsoft Group mapping?')) return;
    
    try {
        const response = await fetch('/api/org-roles.php?action=remove-microsoft-group', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role_id: roleId, group_id: groupId })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to remove Microsoft Group');
        }
        
        showMessage('Microsoft Group removed successfully', 'success');
        closeModal();
        loadOrgRoles();
    } catch (error) {
        console.error('Error removing Microsoft Group:', error);
        showMessage('Failed to remove Microsoft Group: ' + error.message, 'error');
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
