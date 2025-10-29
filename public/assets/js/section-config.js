/**
 * Section Configuration JavaScript - Part 2
 * Implementation functions for section config management
 */

// Store current section data
let currentSectionData = {};
let availableCategories = [];

// Populate section configuration with data
function populateSectionConfig(sectionId, section) {
    currentSectionData[sectionId] = section;
    
    // Populate category dropdown
    const categorySelect = document.getElementById(`category-${sectionId}`);
    if (categorySelect && sectionsConfigData.length > 0) {
        const allData = sectionsConfigData[0]; // Get categories from first load
        // For now, hardcode the categories
        categorySelect.innerHTML = `
            <option value="">Select Category...</option>
            <option value="1" ${section.category_id == 1 ? 'selected' : ''}>📋 Reporting & Forms</option>
            <option value="2" ${section.category_id == 2 ? 'selected' : ''}>📊 Analytics & Dashboards</option>
            <option value="3" ${section.category_id == 3 ? 'selected' : ''}>🔧 Tools & Utilities</option>
            <option value="4" ${section.category_id == 4 ? 'selected' : ''}>📚 Resources & Documents</option>
            <option value="5" ${section.category_id == 5 ? 'selected' : ''}>⚙️ Administration</option>
        `;
        
        // Trigger category change to show relevant sections
        if (section.category_id) {
            handleCategoryChange(sectionId);
        }
    }
    
    // Populate submission permissions
    renderSubmissionPerms(sectionId, section.submission_permissions || []);
    
    // Populate review permissions
    renderReviewPerms(sectionId, section.review_permissions || []);
    
    // Populate notification rules
    renderNotificationRules(sectionId, section.notification_rules || []);
    
    // Populate guidelines
    renderGuidelines(sectionId, section.guidelines || []);
    
    // Populate configuration options
    if (section.configuration) {
        const config = section.configuration;
        document.getElementById(`enable-status-${sectionId}`).checked = config.enable_status_tracking == 1;
        document.getElementById(`enable-priority-${sectionId}`).checked = config.enable_priority_levels == 1;
        document.getElementById(`enable-attachments-${sectionId}`).checked = config.enable_attachments == 1;
        document.getElementById(`enable-notes-${sectionId}`).checked = config.enable_follow_up_notes == 1;
        document.getElementById(`enable-assignment-${sectionId}`).checked = config.enable_assignment == 1;
    }
}

// Handle category change - show/hide relevant sections
function handleCategoryChange(sectionId) {
    const categorySelect = document.getElementById(`category-${sectionId}`);
    const categoryId = parseInt(categorySelect.value);
    
    // Show/hide sections based on category requirements
    const showSubmission = categoryId === 1 || categoryId === 3; // Reporting or Tools
    const showReview = categoryId === 1; // Reporting
    const showNotifications = categoryId === 1; // Reporting
    const showGuidelines = categoryId !== 5; // All except Administration
    const showConfig = categoryId === 1; // Reporting
    
    document.getElementById(`submission-section-${sectionId}`).style.display = showSubmission ? 'block' : 'none';
    document.getElementById(`review-section-${sectionId}`).style.display = showReview ? 'block' : 'none';
    document.getElementById(`notification-section-${sectionId}`).style.display = showNotifications ? 'block' : 'none';
    document.getElementById(`guidelines-section-${sectionId}`).style.display = showGuidelines ? 'block' : 'none';
    document.getElementById(`config-options-${sectionId}`).style.display = showConfig ? 'block' : 'none';
}

// Render submission permissions
function renderSubmissionPerms(sectionId, perms) {
    const container = document.getElementById(`submission-perms-${sectionId}`);
    
    container.innerHTML = perms.map((perm, idx) => `
        <div class="permission-row" data-perm-idx="${idx}">
            <div class="permission-role">
                <select class="form-select" data-field="role_name">
                    ${renderRoleOptions(perm.role_name)}
                </select>
            </div>
            <div class="permission-checkboxes">
                <div class="permission-checkbox">
                    <input type="checkbox" id="submit-can-${sectionId}-${idx}" 
                           ${perm.can_submit ? 'checked' : ''} 
                           data-field="can_submit">
                    <label for="submit-can-${sectionId}-${idx}">Can Submit</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="submit-anon-${sectionId}-${idx}" 
                           ${perm.allow_anonymous ? 'checked' : ''} 
                           data-field="allow_anonymous">
                    <label for="submit-anon-${sectionId}-${idx}">Allow Anonymous</label>
                </div>
                <button class="btn btn-sm btn-danger" onclick="removePermission('submission-perms-${sectionId}', ${idx})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

// Render review permissions
function renderReviewPerms(sectionId, perms) {
    const container = document.getElementById(`review-perms-${sectionId}`);
    
    container.innerHTML = perms.map((perm, idx) => `
        <div class="permission-row" data-perm-idx="${idx}">
            <div class="permission-role">
                <select class="form-select" data-field="role_name">
                    ${renderRoleOptions(perm.role_name)}
                </select>
            </div>
            <div class="permission-checkboxes">
                <div class="permission-checkbox">
                    <input type="checkbox" id="review-view-${sectionId}-${idx}" ${perm.can_view ? 'checked' : ''} data-field="can_view">
                    <label for="review-view-${sectionId}-${idx}">View</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="review-edit-${sectionId}-${idx}" ${perm.can_edit ? 'checked' : ''} data-field="can_edit">
                    <label for="review-edit-${sectionId}-${idx}">Edit</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="review-delete-${sectionId}-${idx}" ${perm.can_delete ? 'checked' : ''} data-field="can_delete">
                    <label for="review-delete-${sectionId}-${idx}">Delete</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="review-notes-${sectionId}-${idx}" ${perm.can_add_notes ? 'checked' : ''} data-field="can_add_notes">
                    <label for="review-notes-${sectionId}-${idx}">Add Notes</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="review-status-${sectionId}-${idx}" ${perm.can_change_status ? 'checked' : ''} data-field="can_change_status">
                    <label for="review-status-${sectionId}-${idx}">Change Status</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="review-assign-${sectionId}-${idx}" ${perm.can_assign ? 'checked' : ''} data-field="can_assign">
                    <label for="review-assign-${sectionId}-${idx}">Assign</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="review-export-${sectionId}-${idx}" ${perm.can_export ? 'checked' : ''} data-field="can_export">
                    <label for="review-export-${sectionId}-${idx}">Export</label>
                </div>
                <button class="btn btn-sm btn-danger" onclick="removePermission('review-perms-${sectionId}', ${idx})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

// Render notification rules
function renderNotificationRules(sectionId, rules) {
    const container = document.getElementById(`notification-rules-${sectionId}`);
    
    container.innerHTML = rules.map((rule, idx) => `
        <div class="permission-row" data-perm-idx="${idx}">
            <div class="permission-role">
                <select class="form-select" data-field="role_name">
                    ${renderRoleOptions(rule.role_name)}
                </select>
            </div>
            <div class="permission-checkboxes">
                <div class="permission-checkbox">
                    <input type="checkbox" id="notif-submit-${sectionId}-${idx}" ${rule.notify_on_submission ? 'checked' : ''} data-field="notify_on_submission">
                    <label for="notif-submit-${sectionId}-${idx}">On Submission</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="notif-status-${sectionId}-${idx}" ${rule.notify_on_status_change ? 'checked' : ''} data-field="notify_on_status_change">
                    <label for="notif-status-${sectionId}-${idx}">Status Change</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="notif-assign-${sectionId}-${idx}" ${rule.notify_on_assignment ? 'checked' : ''} data-field="notify_on_assignment">
                    <label for="notif-assign-${sectionId}-${idx}">On Assignment</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="notif-comment-${sectionId}-${idx}" ${rule.notify_on_comment ? 'checked' : ''} data-field="notify_on_comment">
                    <label for="notif-comment-${sectionId}-${idx}">On Comment</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="notif-email-${sectionId}-${idx}" ${rule.email_enabled ? 'checked' : ''} data-field="email_enabled">
                    <label for="notif-email-${sectionId}-${idx}">📧 Email</label>
                </div>
                <div class="permission-checkbox">
                    <input type="checkbox" id="notif-sms-${sectionId}-${idx}" ${rule.sms_enabled ? 'checked' : ''} data-field="sms_enabled">
                    <label for="notif-sms-${sectionId}-${idx}">📱 SMS</label>
                </div>
                <button class="btn btn-sm btn-danger" onclick="removePermission('notification-rules-${sectionId}', ${idx})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

// Render guidelines
function renderGuidelines(sectionId, guidelines) {
    const container = document.getElementById(`guidelines-${sectionId}`);
    
    container.innerHTML = guidelines.map((guideline, idx) => `
        <div class="guideline-item" data-guideline-idx="${idx}">
            <select class="form-select" style="margin-bottom: 0.5rem;" data-field="guideline_type">
                <option value="general" ${guideline.guideline_type === 'general' ? 'selected' : ''}>General</option>
                <option value="submission" ${guideline.guideline_type === 'submission' ? 'selected' : ''}>Submission</option>
                <option value="review" ${guideline.guideline_type === 'review' ? 'selected' : ''}>Review</option>
            </select>
            <input type="text" placeholder="Title" value="${guideline.title || ''}" data-field="title">
            <textarea placeholder="Content" data-field="content">${guideline.content || ''}</textarea>
            <div class="guideline-actions">
                <button class="btn btn-sm btn-danger" onclick="removeGuideline(${sectionId}, ${idx})">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
        </div>
    `).join('');
}

// Render role options for dropdowns
function renderRoleOptions(selectedRole) {
    const roles = ['student', 'parent', 'teacher', 'staff', 'counselor', 'principal', 'admin', 'super_admin', 
                   'maintenance', 'maintenance_director', 'secretary', 'librarian', 'it_support', 'superintendent'];
    
    return roles.map(role => `
        <option value="${role}" ${role === selectedRole ? 'selected' : ''}>
            ${role.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
        </option>
    `).join('');
}

// Add new submission permission
function addSubmissionPerm(sectionId) {
    const section = currentSectionData[sectionId];
    if (!section.submission_permissions) section.submission_permissions = [];
    
    section.submission_permissions.push({
        role_name: 'staff',
        can_submit: true,
        allow_anonymous: false
    });
    
    renderSubmissionPerms(sectionId, section.submission_permissions);
}

// Add new review permission
function addReviewPerm(sectionId) {
    const section = currentSectionData[sectionId];
    if (!section.review_permissions) section.review_permissions = [];
    
    section.review_permissions.push({
        role_name: 'admin',
        can_view: true,
        can_edit: false,
        can_delete: false,
        can_add_notes: true,
        can_change_status: true,
        can_assign: false,
        can_export: true
    });
    
    renderReviewPerms(sectionId, section.review_permissions);
}

// Add new notification rule
function addNotificationRule(sectionId) {
    const section = currentSectionData[sectionId];
    if (!section.notification_rules) section.notification_rules = [];
    
    section.notification_rules.push({
        role_name: 'admin',
        notify_on_submission: true,
        notify_on_status_change: false,
        notify_on_assignment: false,
        notify_on_comment: false,
        email_enabled: true,
        sms_enabled: false
    });
    
    renderNotificationRules(sectionId, section.notification_rules);
}

// Add new guideline
function addGuideline(sectionId) {
    const section = currentSectionData[sectionId];
    if (!section.guidelines) section.guidelines = [];
    
    section.guidelines.push({
        guideline_type: 'general',
        title: '',
        content: '',
        display_order: section.guidelines.length
    });
    
    renderGuidelines(sectionId, section.guidelines);
}

// Remove permission
function removePermission(containerId, idx) {
    const container = document.getElementById(containerId);
    const row = container.querySelector(`[data-perm-idx="${idx}"]`);
    if (row) row.remove();
}

// Remove guideline
function removeGuideline(sectionId, idx) {
    const container = document.getElementById(`guidelines-${sectionId}`);
    const item = container.querySelector(`[data-guideline-idx="${idx}"]`);
    if (item) item.remove();
}

// Save section configuration
async function saveSectionConfig(sectionId) {
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '💾 Saving...';
    
    try {
        // Collect category
        const categoryId = document.getElementById(`category-${sectionId}`).value;
        
        // Collect submission permissions
        const submissionPerms = [];
        document.querySelectorAll(`#submission-perms-${sectionId} .permission-row`).forEach(row => {
            submissionPerms.push({
                role_name: row.querySelector('[data-field="role_name"]').value,
                can_submit: row.querySelector('[data-field="can_submit"]').checked,
                allow_anonymous: row.querySelector('[data-field="allow_anonymous"]').checked
            });
        });
        
        // Collect review permissions
        const reviewPerms = [];
        document.querySelectorAll(`#review-perms-${sectionId} .permission-row`).forEach(row => {
            reviewPerms.push({
                role_name: row.querySelector('[data-field="role_name"]').value,
                can_view: row.querySelector('[data-field="can_view"]').checked,
                can_edit: row.querySelector('[data-field="can_edit"]').checked,
                can_delete: row.querySelector('[data-field="can_delete"]').checked,
                can_add_notes: row.querySelector('[data-field="can_add_notes"]').checked,
                can_change_status: row.querySelector('[data-field="can_change_status"]').checked,
                can_assign: row.querySelector('[data-field="can_assign"]').checked,
                can_export: row.querySelector('[data-field="can_export"]').checked
            });
        });
        
        // Collect notification rules
        const notificationRules = [];
        document.querySelectorAll(`#notification-rules-${sectionId} .permission-row`).forEach(row => {
            notificationRules.push({
                role_name: row.querySelector('[data-field="role_name"]').value,
                notify_on_submission: row.querySelector('[data-field="notify_on_submission"]').checked,
                notify_on_status_change: row.querySelector('[data-field="notify_on_status_change"]').checked,
                notify_on_assignment: row.querySelector('[data-field="notify_on_assignment"]').checked,
                notify_on_comment: row.querySelector('[data-field="notify_on_comment"]').checked,
                email_enabled: row.querySelector('[data-field="email_enabled"]').checked,
                sms_enabled: row.querySelector('[data-field="sms_enabled"]').checked
            });
        });
        
        // Collect guidelines
        const guidelines = [];
        document.querySelectorAll(`#guidelines-${sectionId} .guideline-item`).forEach(item => {
            guidelines.push({
                guideline_type: item.querySelector('[data-field="guideline_type"]').value,
                title: item.querySelector('[data-field="title"]').value,
                content: item.querySelector('[data-field="content"]').value
            });
        });
        
        // Collect configuration
        const configuration = {
            enable_status_tracking: document.getElementById(`enable-status-${sectionId}`).checked,
            enable_priority_levels: document.getElementById(`enable-priority-${sectionId}`).checked,
            enable_attachments: document.getElementById(`enable-attachments-${sectionId}`).checked,
            enable_follow_up_notes: document.getElementById(`enable-notes-${sectionId}`).checked,
            enable_assignment: document.getElementById(`enable-assignment-${sectionId}`).checked
        };
        
        // Send to API
        const formData = new FormData();
        formData.append('section_id', sectionId);
        formData.append('category_id', categoryId);
        formData.append('submission_permissions', JSON.stringify(submissionPerms));
        formData.append('review_permissions', JSON.stringify(reviewPerms));
        formData.append('notification_rules', JSON.stringify(notificationRules));
        formData.append('guidelines', JSON.stringify(guidelines));
        formData.append('configuration', JSON.stringify(configuration));
        
        const response = await fetch('/api/section-config.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('✅ Section configuration saved successfully', 'success');
            // Reload the sections list to show updated status
            await loadSectionConfig();
        } else {
            showMessage('❌ Error: ' + (data.error || 'Failed to save configuration'), 'error');
        }
    } catch (error) {
        console.error('Save error:', error);
        showMessage('❌ Network error while saving', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Save Configuration';
    }
}
