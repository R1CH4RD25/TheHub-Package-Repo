/**
 * Organization Roles Management JavaScript
 * Handles UI for creating, editing, and managing org roles
 */

/**
 * Show message notification
 */
function showMessage(message, type = 'success') {
    // Remove existing toast if any
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.style.cssText = `
        position: fixed !important;
        bottom: 20px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        background: ${type === 'success' ? '#107C10' : type === 'error' ? '#D13438' : '#0078D4'} !important;
        color: white !important;
        padding: 16px 24px !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2) !important;
        z-index: 10000 !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    // Slide in
    setTimeout(() => {
        toast.style.bottom = '30px !important';
    }, 10);

    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.bottom = '-100px !important';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Create a modal with title and content
 */
function createModal(title, content) {
    // Remove existing modal if any
    let modal = document.getElementById('dynamicModal');
    if (modal) modal.remove();

    // Create modal backdrop
    modal = document.createElement('div');
    modal.id = 'dynamicModal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 1.5rem; transition: background 0.3s ease; opacity: 0;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background: white; border-radius: 12px; max-width: 900px; width: 100%; max-height: 92vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); transform: scale(0.9); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); opacity: 0;';
    
    modalContent.innerHTML = `
        <div class="modal-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between;">
            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 500; color: #111827;">${title}</h3>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; transition: all 0.2s;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 1.5rem; overflow-y: auto; max-height: calc(92vh - 120px);">
            ${content}
        </div>
    `;

    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // Trigger animations
    requestAnimationFrame(() => {
        modal.style.background = 'rgba(0,0,0,0.5)';
        modal.style.opacity = '1';
        modalContent.style.transform = 'scale(1)';
        modalContent.style.opacity = '1';
    });

    // Close on backdrop click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Close on Escape key
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);

    return modal;
}

/**
 * Close the modal
 */
function closeModal() {
    const modal = document.getElementById('dynamicModal');
    if (!modal) return;

    const modalContent = modal.querySelector('div');
    
    // Animate out
    modal.style.background = 'rgba(0,0,0,0)';
    modal.style.opacity = '0';
    modalContent.style.transform = 'scale(0.9)';
    modalContent.style.opacity = '0';

    setTimeout(() => modal.remove(), 300);
}

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
    console.group(`📋 Manage Users Modal - ${roleName} (ID: ${roleId})`);
    console.log('Fetching data...');
    
    // Store role info globally for use in add/remove functions
    window.currentRoleManagement = { roleId, roleName };
    
    // Fetch all users and current role assignments
    const [usersResponse, roleUsersResponse] = await Promise.all([
        fetch('/api/users.php?action=list'),
        fetch(`/api/org-roles.php?action=users&role_id=${roleId}`)
    ]);

    console.log('Users API Response Status:', usersResponse.status);
    console.log('Role Users API Response Status:', roleUsersResponse.status);

    const usersData = await usersResponse.json();
    const roleUsersData = await roleUsersResponse.json();

    console.log('Raw Users Data:', usersData);
    console.log('Raw Role Users Data:', roleUsersData);

    // Handle different API response formats
    // users.php returns array directly: [{...}, {...}]
    // org-roles.php returns object: {success: true, users: [...]}
    const allUsers = Array.isArray(usersData) ? usersData : (usersData.users || []);
    const currentMembers = roleUsersData.users || [];
    const currentMemberIds = currentMembers.map(u => u.id);
    const availableUsers = allUsers.filter(u => !currentMemberIds.includes(u.id));

    console.log('📊 Summary:');
    console.log(`  Total Users in System: ${allUsers.length}`);
    console.log(`  Current Members: ${currentMembers.length}`, currentMembers.map(u => u.name));
    console.log(`  Available to Add: ${availableUsers.length}`, availableUsers.map(u => u.name));
    console.groupEnd();

    const modal = createModal(`Manage Users - ${roleName}`, `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; min-height: 600px;">
            <!-- Current Members Panel -->
            <div style="border-right: 1px solid #e5e7eb; padding-right: 2rem;">
                <h4 style="margin: 0 0 1rem 0; display: flex; align-items: center; justify-content: space-between;">
                    <span>
                        <i class="fas fa-users"></i> Current Members
                        <span class="badge" style="margin-left: 0.5rem; background: #3B82F6; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">${currentMembers.length}</span>
                    </span>
                    ${currentMembers.length > 0 ? `<button type="button" class="btn btn-sm btn-danger" onclick="removeAllRoleUsers(${roleId})" style="font-size: 0.85rem; padding: 4px 12px;">Remove All</button>` : ''}
                </h4>
                
                <div id="currentMembersList" style="max-height: 560px; overflow-y: auto;">
                    ${currentMembers.length > 0 ? currentMembers.map(user => `
                        <div class="member-item" data-user-id="${user.id}" style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 0.5rem; background: #f9fafb;">
                            <div style="display: flex; align-items: center; flex: 1;">
                                <img src="${user.picture || '/assets/images/default-avatar.png'}"
                                     style="width: 40px; height: 40px; border-radius: 50%; margin-right: 0.75rem; border: 2px solid #e5e7eb;">
                                <div>
                                    <div style="font-weight: 500; color: #111827;">${escapeHtml(user.name)}</div>
                                    <div style="font-size: 0.85rem; color: #6b7280;">${escapeHtml(user.email)}</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeSingleRoleUser(${roleId}, ${user.id})" style="padding: 6px 12px;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `).join('') : '<div style="padding: 2rem; text-align: center; color: #94a3b8;"><i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>No members assigned</div>'}
                </div>
            </div>

            <!-- Available Users Panel -->
            <div>
                <h4 style="margin: 0 0 1rem 0; display: flex; align-items: center; justify-content: space-between;">
                    <span>
                        <i class="fas fa-user-plus"></i> Add Members
                        <span class="badge" style="margin-left: 0.5rem; background: #10B981; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">${availableUsers.length}</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addSelectedUsers(${roleId})" id="addSelectedBtn" disabled style="font-size: 0.85rem; padding: 4px 12px;">
                        <i class="fas fa-plus"></i> Add Selected (<span id="selectedCount">0</span>)
                    </button>
                </h4>

                <div style="margin-bottom: 1rem;">
                    <div style="position: relative;">
                        <input type="text" id="userSearchInput" class="form-control"
                               placeholder="Search by name or email..."
                               onkeyup="filterAvailableUsers()"
                               style="width: 100%; padding: 0.75rem 1rem; padding-left: 2.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem; line-height: 1.5; min-height: 48px; transition: all 0.2s;">
                        <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none;"></i>
                    </div>
                </div>

                <div id="availableUsersList" style="max-height: 460px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.5rem; background: #fafafa;">
                    ${availableUsers.length > 0 ? availableUsers.map(user => `
                        <label class="available-user-item" data-user-id="${user.id}" data-user-name="${escapeHtml(user.name).toLowerCase()} ${escapeHtml(user.email).toLowerCase()}"
                               style="display: flex; align-items: center; padding: 0.75rem; cursor: pointer; border-radius: 8px; margin-bottom: 0.5rem; border: 1px solid transparent; transition: all 0.2s;">
                            <input type="checkbox" value="${user.id}" class="user-checkbox" onchange="updateSelectedCount()"
                                   style="margin-right: 0.75rem; width: 18px; height: 18px; cursor: pointer;">
                            <img src="${user.picture || '/assets/images/default-avatar.png'}"
                                 style="width: 40px; height: 40px; border-radius: 50%; margin-right: 0.75rem; border: 2px solid #e5e7eb;">
                            <div>
                                <div style="font-weight: 500; color: #111827;">${escapeHtml(user.name)}</div>
                                <div style="font-size: 0.85rem; color: #6b7280;">${escapeHtml(user.email)}</div>
                            </div>
                        </label>
                    `).join('') : `<div style="padding: 2rem; text-align: center; color: #94a3b8;">
                        <i class="fas fa-${currentMembers.length === allUsers.length && allUsers.length > 0 ? 'check-circle' : 'users-slash'}" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                        <p style="margin: 0; font-weight: 500; color: #6b7280;">
                            ${currentMembers.length === allUsers.length && allUsers.length > 0 
                                ? 'All users are already members' 
                                : allUsers.length === 0 
                                    ? 'No active users in the system' 
                                    : 'No available users to add'}
                        </p>
                    </div>`}
                </div>
            </div>
        </div>

        <style>
            #userSearchInput:focus {
                outline: none;
                border-color: #3B82F6 !important;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
            }
            .available-user-item:hover {
                background: #f3f4f6 !important;
                border-color: #d1d5db !important;
            }
            .available-user-item:has(input:checked) {
                background: #EFF6FF !important;
                border-color: #3B82F6 !important;
            }
        </style>
        
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveRoleUserChanges(${roleId})" id="saveRoleChangesBtn">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    `);

    // Store roleId, roleName, and initial state for change tracking
    window.currentRoleManagement = { 
        roleId, 
        roleName,
        initialMembers: new Set(currentMembers.map(u => u.id)),
        currentMembers: new Set(currentMembers.map(u => u.id)),
        allUsers: new Map(allUsers.map(u => [u.id, u]))
    };
}

/**
 * Filter available users
 */
function filterAvailableUsers() {
    const search = document.getElementById('userSearchInput').value.toLowerCase();
    const items = document.querySelectorAll('.available-user-item');

    items.forEach(item => {
        const searchText = item.dataset.userName;
        item.style.display = searchText.includes(search) ? 'flex' : 'none';
    });
}

/**
 * Update selected count badge
 */
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const count = checkboxes.length;
    const countSpan = document.getElementById('selectedCount');
    const addBtn = document.getElementById('addSelectedBtn');
    
    if (countSpan) countSpan.textContent = count;
    if (addBtn) addBtn.disabled = count === 0;
}

/**
 * Add selected users to role (in-memory, not saved yet)
 */
function addSelectedUsers(roleId) {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const userIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

    if (userIds.length === 0) {
        return;
    }

    const availableList = document.getElementById('availableUsersList');
    const currentList = document.getElementById('currentMembersList');
    
    userIds.forEach(userId => {
        const userLabel = availableList.querySelector(`label[data-user-id="${userId}"]`);
        if (userLabel) {
            // Extract user info
            const img = userLabel.querySelector('img');
            const nameDiv = userLabel.querySelector('div > div:first-child');
            const emailDiv = userLabel.querySelector('div > div:last-child');
            
            const userName = nameDiv.textContent;
            const userEmail = emailDiv.textContent;
            const userPicture = img.src;
            
            // Remove from available list
            userLabel.remove();
            
            // Add to current members list
            const memberHtml = `
                <div class="member-item" data-user-id="${userId}" style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 0.5rem; background: #f9fafb;">
                    <div style="display: flex; align-items: center; flex: 1;">
                        <img src="${userPicture}"
                             style="width: 40px; height: 40px; border-radius: 50%; margin-right: 0.75rem; border: 2px solid #e5e7eb;">
                        <div>
                            <div style="font-weight: 500; color: #111827;">${userName}</div>
                            <div style="font-size: 0.85rem; color: #6b7280;">${userEmail}</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeSingleRoleUser(${roleId}, ${userId})" style="padding: 6px 12px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Replace "No members" message if exists
            const noMembersMsg = currentList.querySelector('div[style*="text-align: center"]');
            if (noMembersMsg) {
                currentList.innerHTML = memberHtml;
            } else {
                currentList.insertAdjacentHTML('beforeend', memberHtml);
            }
            
            // Track change in memory
            window.currentRoleManagement.currentMembers.add(userId);
        }
    });
    
    // Update badges
    updateBadgesAndEmptyStates();
    
    // Clear selection
    updateSelectedCount();
}

/**
 * Remove single user from role (in-memory, not saved yet)
 */
function removeSingleRoleUser(roleId, userId) {
    // Get user info before removing
    const memberItem = document.querySelector(`.member-item[data-user-id="${userId}"]`);
    if (memberItem) {
        const img = memberItem.querySelector('img');
        const nameDiv = memberItem.querySelector('div > div:first-child');
        const emailDiv = memberItem.querySelector('div > div:last-child');
        
        const userName = nameDiv.textContent;
        const userEmail = emailDiv.textContent;
        const userPicture = img.src;
        
        // Remove from current members
        memberItem.remove();
        
        // Add to available users
        const availableList = document.getElementById('availableUsersList');
        const userHtml = `
            <label class="available-user-item" data-user-id="${userId}" data-user-name="${userName.toLowerCase()} ${userEmail.toLowerCase()}"
                   style="display: flex; align-items: center; padding: 0.75rem; cursor: pointer; border-radius: 8px; margin-bottom: 0.5rem; border: 1px solid transparent; transition: all 0.2s;">
                <input type="checkbox" value="${userId}" class="user-checkbox" onchange="updateSelectedCount()"
                       style="margin-right: 0.75rem; width: 18px; height: 18px; cursor: pointer;">
                <img src="${userPicture}"
                     style="width: 40px; height: 40px; border-radius: 50%; margin-right: 0.75rem; border: 2px solid #e5e7eb;">
                <div>
                    <div style="font-weight: 500; color: #111827;">${userName}</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">${userEmail}</div>
                </div>
            </label>
        `;
        
        // Remove empty state message if exists
        const emptyMsg = availableList.querySelector('div[style*="text-align: center"]');
        if (emptyMsg) {
            availableList.innerHTML = userHtml;
        } else {
            availableList.insertAdjacentHTML('beforeend', userHtml);
        }
        
        // Track change in memory
        window.currentRoleManagement.currentMembers.delete(userId);
        
        // Update badges
        updateBadgesAndEmptyStates();
    }
}

/**
 * Remove all users from role
 */
async function removeAllRoleUsers(roleId) {
    if (!confirm('Remove ALL users from this role? This action cannot be undone.')) return;

    try {
        const memberItems = document.querySelectorAll('.member-item');
        const userIds = Array.from(memberItems).map(item => parseInt(item.dataset.userId));

        const response = await fetch(`/api/org-roles.php?action=remove-users`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role_id: roleId, user_ids: userIds })
        });

        const data = await response.json();

        if (data.success) {
            showMessage('All users removed from role', 'success');
            
            // Move all members to available list
            const currentList = document.getElementById('currentMembersList');
            const availableList = document.getElementById('availableUsersList');
            const memberItems = currentList.querySelectorAll('.member-item');
            
            memberItems.forEach(memberItem => {
                const userId = memberItem.dataset.userId;
                const img = memberItem.querySelector('img');
                const nameDiv = memberItem.querySelector('div > div:first-child');
                const emailDiv = memberItem.querySelector('div > div:last-child');
                
                const userName = nameDiv.textContent;
                const userEmail = emailDiv.textContent;
                const userPicture = img.src;
                
                // Add to available users
                const userHtml = `
                    <label class="available-user-item" data-user-id="${userId}" data-user-name="${userName.toLowerCase()} ${userEmail.toLowerCase()}"
                           style="display: flex; align-items: center; padding: 0.75rem; cursor: pointer; border-radius: 8px; margin-bottom: 0.5rem; border: 1px solid transparent; transition: all 0.2s;">
                        <input type="checkbox" value="${userId}" class="user-checkbox" onchange="updateSelectedCount()"
                               style="margin-right: 0.75rem; width: 18px; height: 18px; cursor: pointer;">
                        <img src="${userPicture}"
                             style="width: 40px; height: 40px; border-radius: 50%; margin-right: 0.75rem; border: 2px solid #e5e7eb;">
                        <div>
                            <div style="font-weight: 500; color: #111827;">${userName}</div>
                            <div style="font-size: 0.85rem; color: #6b7280;">${userEmail}</div>
                        </div>
                    </label>
                `;
                
                // Remove empty state if exists
                const emptyMsg = availableList.querySelector('div[style*="text-align: center"]');
                if (emptyMsg) {
                    availableList.innerHTML = '';
                }
                
                availableList.insertAdjacentHTML('beforeend', userHtml);
            });
            
            // Clear current members list
            currentList.innerHTML = '<div style="padding: 2rem; text-align: center; color: #94a3b8;"><i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>No members assigned</div>';
            
            // Update badges
            const currentBadge = currentList.closest('div').previousElementSibling.querySelector('.badge');
            const availableBadge = availableList.closest('div').previousElementSibling.querySelector('.badge');
            const availableCount = availableList.querySelectorAll('label').length;
            
            if (currentBadge) currentBadge.textContent = '0';
            if (availableBadge) availableBadge.textContent = availableCount;
            
            // Refresh main table in background
            loadOrgRoles();
        } else {
            throw new Error(data.error || 'Failed to remove users');
        }
    } catch (error) {
        showMessage('Failed to remove users: ' + error.message, 'error');
    }
}

/**
 * Update badges and empty states in modal
 */
function updateBadgesAndEmptyStates() {
    const currentList = document.getElementById('currentMembersList');
    const availableList = document.getElementById('availableUsersList');
    
    const currentBadge = currentList.closest('div').previousElementSibling.querySelector('.badge');
    const availableBadge = availableList.closest('div').previousElementSibling.querySelector('.badge');
    const currentCount = currentList.querySelectorAll('.member-item').length;
    const availableCount = availableList.querySelectorAll('label').length;
    
    if (currentBadge) currentBadge.textContent = currentCount;
    if (availableBadge) availableBadge.textContent = availableCount;
    
    // Show empty state in available list if needed
    if (availableCount === 0) {
        availableList.innerHTML = `<div style="padding: 2rem; text-align: center; color: #94a3b8;">
            <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
            <p style="margin: 0; font-weight: 500; color: #6b7280;">All users are already members</p>
        </div>`;
    }
    
    // Show empty state in current members if needed
    if (currentCount === 0) {
        currentList.innerHTML = '<div style="padding: 2rem; text-align: center; color: #94a3b8;"><i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>No members assigned</div>';
    }
}

/**
 * Save all role user changes
 */
async function saveRoleUserChanges(roleId) {
    const { initialMembers, currentMembers } = window.currentRoleManagement;
    
    // Calculate what changed
    const usersToAdd = [...currentMembers].filter(id => !initialMembers.has(id));
    const usersToRemove = [...initialMembers].filter(id => !currentMembers.has(id));
    
    if (usersToAdd.length === 0 && usersToRemove.length === 0) {
        showMessage('No changes to save', 'info');
        return;
    }
    
    const saveBtn = document.getElementById('saveRoleChangesBtn');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }
    
    try {
        // Remove users first
        if (usersToRemove.length > 0) {
            const removeResponse = await fetch(`/api/org-roles.php?action=remove-users`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_id: roleId, user_ids: usersToRemove })
            });
            const removeData = await removeResponse.json();
            if (!removeData.success) {
                throw new Error(removeData.error || 'Failed to remove users');
            }
        }
        
        // Add users second
        if (usersToAdd.length > 0) {
            const addResponse = await fetch(`/api/org-roles.php?action=assign-users`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role_id: roleId, user_ids: usersToAdd })
            });
            const addData = await addResponse.json();
            if (!addData.success) {
                throw new Error(addData.error || 'Failed to add users');
            }
        }
        
        // Build success message
        const changes = [];
        if (usersToAdd.length > 0) changes.push(`Added ${usersToAdd.length}`);
        if (usersToRemove.length > 0) changes.push(`Removed ${usersToRemove.length}`);
        
        showMessage(`Changes saved! ${changes.join(', ')} user(s)`, 'success');
        
        // Close modal and refresh table
        closeModal();
        loadOrgRoles();
        
    } catch (error) {
        showMessage('Failed to save changes: ' + error.message, 'error');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    }
}

/**
 * Filter user list (DEPRECATED - keeping for backwards compatibility)
 */
function filterUserList() {
    filterAvailableUsers();
}

/**
 * Save role user assignments (DEPRECATED - replaced with addSelectedUsers)
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
    // Fetch role data and settings in parallel
    const [roleResponse, settingsResponse] = await Promise.all([
        fetch('/api/org-roles.php?action=list'),
        fetch('/api/site-settings.php')
    ]);
    
    const data = await roleResponse.json();
    const settings = await settingsResponse.json();
    const role = data.roles.find(r => r.id === roleId);

    // Check which providers are enabled
    const googleEnabled = settings.enable_google_groups === true || settings.enable_google_groups === 1;
    const microsoftEnabled = settings.enable_microsoft_groups === true || settings.enable_microsoft_groups === 1;

    // Build modal content based on enabled providers
    let modalContent = `
        <p class="info-text" style="margin-bottom: 1.5rem; padding: 1rem; background: #EFF6FF; border-left: 4px solid #3B82F6; color: #1E40AF;">
            <i class="fas fa-cloud"></i>
            Users in these cloud identity groups will automatically be assigned this role when they log in.
        </p>
    `;

    // Only show sections for enabled providers
    if (!googleEnabled && !microsoftEnabled) {
        modalContent += `
            <div style="padding: 2rem; text-align: center; color: #6b7280;">
                <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; color: #94a3b8;"></i>
                <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No Cloud Providers Enabled</p>
                <p style="font-size: 0.9rem;">Enable Google Groups or Microsoft Groups in <a href="/admin/settings" style="color: #3B82F6; text-decoration: underline;">Settings → Authentication</a></p>
            </div>
        `;
    } else {
        // Google Groups Section (only if enabled)
        if (googleEnabled) {
            modalContent += `
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
            `;
        }

        // Microsoft Groups Section (only if enabled)
        if (microsoftEnabled) {
            modalContent += `
                <div class="cloud-groups-section" style="margin-bottom: 2rem; ${googleEnabled ? 'padding-top: 1.5rem; border-top: 1px solid #e5e7eb;' : ''}">
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
            `;
        }
    }

    modalContent += `
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
        </div>
    `;

    const modal = createModal(`Cloud Identity Groups - ${roleName}`, modalContent);

    // Attach form handlers only if the forms exist
    if (googleEnabled) {
        const googleForm = document.getElementById('addGoogleGroupForm');
        if (googleForm) {
            googleForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const email = document.getElementById('googleGroupEmail').value;
                if (email) {
                    await addGoogleGroup(roleId, email);
                }
            });
        }
    }

    if (microsoftEnabled) {
        const microsoftForm = document.getElementById('addMicrosoftGroupForm');
        if (microsoftForm) {
            microsoftForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const groupId = document.getElementById('azureGroupId').value;
                const groupName = document.getElementById('azureGroupName').value;
                if (groupId) {
                    await addMicrosoftGroup(roleId, groupId, groupName);
                }
            });
        }
    }
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
