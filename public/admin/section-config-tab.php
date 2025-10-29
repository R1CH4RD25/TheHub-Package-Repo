<!-- Section Configuration Tab Content -->
<div class="tab-header">
    <h1>Section Configuration</h1>
    <div class="tab-actions">
        <button class="btn btn-primary" onclick="refreshSectionConfig()">
            <i class="fas fa-sync"></i> Refresh
        </button>
    </div>
</div>

<div class="tab-content-scroll">
    <div class="info-text" style="margin-bottom: 2rem;">
        <strong>Configure section categories, permissions, notifications, and guidelines.</strong>
        <br>Each section must be assigned a category which determines required configuration settings.
    </div>
    
    <!-- Sections List -->
    <div id="sectionsConfigList" class="sections-config-list">
        <p style="text-align: center; padding: 2rem; color: #666;">
            <i class="fas fa-spinner fa-spin"></i> Loading sections...
        </p>
    </div>
</div>

<style>
.sections-config-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.section-config-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    border-left: 4px solid var(--primary-color);
}

.section-config-card.unconfigured {
    border-left-color: #f59e0b;
}

.section-config-card.error {
    border-left-color: #ef4444;
}

.section-config-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    background: #f8f9fa;
    cursor: pointer;
    user-select: none;
    transition: background 0.2s;
}

.section-config-header:hover {
    background: #e9ecef;
}

.section-config-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.section-config-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-color);
    color: white;
    border-radius: 12px;
}

.section-config-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.25rem;
    color: #333;
}

.section-config-meta {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    background: #e0e7ff;
    color: #3730a3;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-badge.configured {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.unconfigured {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.error {
    background: #fee2e2;
    color: #991b1b;
}

.section-config-toggle {
    font-size: 1.5rem;
    color: #666;
    transition: transform 0.3s;
}

.section-config-toggle.expanded {
    transform: rotate(180deg);
}

.section-config-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.section-config-body.expanded {
    max-height: 5000px;
}

.section-config-content {
    padding: 2rem;
    border-top: 1px solid #e5e7eb;
}

.config-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.config-section h4 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.config-section-required {
    color: #ef4444;
    font-size: 0.85rem;
}

.permissions-grid {
    display: grid;
    gap: 1rem;
}

.permission-row {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 1rem;
    align-items: start;
    padding: 1rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}

.permission-role {
    font-weight: 600;
    color: #333;
    padding-top: 0.5rem;
}

.permission-checkboxes {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.permission-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.permission-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.permission-checkbox label {
    cursor: pointer;
    margin: 0;
    font-size: 0.9rem;
    color: #555;
}

.add-permission-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: white;
    border: 2px dashed var(--primary-color);
    color: var(--primary-color);
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.add-permission-btn:hover {
    background: var(--primary-light);
}

.guideline-item {
    padding: 1rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    margin-bottom: 1rem;
}

.guideline-item input[type="text"] {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.guideline-item textarea {
    width: 100%;
    min-height: 100px;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 0.95rem;
    font-family: inherit;
    resize: vertical;
}

.guideline-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.save-config-btn {
    position: sticky;
    bottom: 2rem;
    left: 0;
    right: 0;
    margin-top: 2rem;
    padding: 1rem 2rem;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: all 0.2s;
}

.save-config-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

.validation-alerts {
    margin-bottom: 1rem;
}

.validation-alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 0.5rem;
}

.validation-alert.error {
    background: #fee2e2;
    border-left: 4px solid #ef4444;
    color: #991b1b;
}

.validation-alert.warning {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    color: #92400e;
}

.form-select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
    background: white;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

@media (max-width: 768px) {
    .permission-row {
        grid-template-columns: 1fr;
    }
    
    .permission-checkboxes {
        flex-direction: column;
        gap: 0.75rem;
    }
}
</style>

<script>
let sectionsConfigData = [];

// Load section configuration data
async function loadSectionConfig() {
    try {
        const response = await fetch('/api/section-config.php');
        const data = await response.json();
        
        if (data.success) {
            sectionsConfigData = data.sections;
            renderSectionConfigList(data.sections, data.categories);
        } else {
            showMessage('Failed to load section configuration', 'error');
        }
    } catch (error) {
        console.error('Error loading section config:', error);
        showMessage('Error loading section configuration', 'error');
    }
}

// Render the sections list
function renderSectionConfigList(sections, categories) {
    const container = document.getElementById('sectionsConfigList');
    
    if (!sections || sections.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #666;">No sections found.</p>';
        return;
    }
    
    container.innerHTML = sections.map(section => {
        const hasErrors = section.validation && section.validation.errors.length > 0;
        const hasWarnings = section.validation && section.validation.warnings.length > 0;
        const statusClass = hasErrors ? 'error' : (section.is_configured ? 'configured' : 'unconfigured');
        const statusText = hasErrors ? 'Configuration Error' : (section.is_configured ? 'Configured' : 'Not Configured');
        const statusIcon = hasErrors ? '❌' : (section.is_configured ? '✅' : '⚠️');
        
        // Render icon - check if it's a Bootstrap Icons class or emoji/unicode
        const iconHtml = section.icon && section.icon.startsWith('bi-') 
            ? `<i class="bi ${section.icon}"></i>`
            : (section.icon || '📦');
        
        return `
            <div class="section-config-card ${statusClass}" data-section-id="${section.id}">
                <div class="section-config-header" onclick="toggleSectionConfig(${section.id})">
                    <div class="section-config-header-left">
                        <div class="section-config-icon">${iconHtml}</div>
                        <div class="section-config-info">
                            <h3>${section.display_name}</h3>
                            <div class="section-config-meta">
                                ${section.category_display_name ? `<span class="category-badge">📂 ${section.category_display_name}</span>` : '<span class="category-badge" style="background: #fee2e2; color: #991b1b;">⚠️ No Category</span>'}
                                <span class="status-badge ${statusClass}">${statusIcon} ${statusText}</span>
                            </div>
                        </div>
                    </div>
                    <div class="section-config-toggle" id="toggle-${section.id}">▼</div>
                </div>
                <div class="section-config-body" id="body-${section.id}">
                    <div class="section-config-content" id="content-${section.id}">
                        <div style="text-align: center; padding: 2rem; color: #666;">
                            <i class="fas fa-spinner fa-spin"></i> Loading configuration...
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Toggle section configuration panel
async function toggleSectionConfig(sectionId) {
    const body = document.getElementById(`body-${sectionId}`);
    const toggle = document.getElementById(`toggle-${sectionId}`);
    const content = document.getElementById(`content-${sectionId}`);
    
    const isExpanded = body.classList.contains('expanded');
    
    // Close all other panels
    document.querySelectorAll('.section-config-body.expanded').forEach(el => {
        if (el.id !== `body-${sectionId}`) {
            el.classList.remove('expanded');
            const otherId = el.id.replace('body-', '');
            document.getElementById(`toggle-${otherId}`)?.classList.remove('expanded');
        }
    });
    
    if (isExpanded) {
        body.classList.remove('expanded');
        toggle.classList.remove('expanded');
    } else {
        body.classList.add('expanded');
        toggle.classList.add('expanded');
        
        // Load detailed configuration if not already loaded
        if (content.querySelector('.fa-spinner')) {
            await loadSectionDetail(sectionId);
        }
    }
}

// Load detailed configuration for a section
async function loadSectionDetail(sectionId) {
    const content = document.getElementById(`content-${sectionId}`);
    
    try {
        const response = await fetch(`/api/section-config.php?section_id=${sectionId}`);
        const data = await response.json();
        
        if (data.success) {
            renderSectionDetail(sectionId, data.section);
        } else {
            content.innerHTML = '<p style="color: red;">Failed to load configuration</p>';
        }
    } catch (error) {
        console.error('Error loading section detail:', error);
        content.innerHTML = '<p style="color: red;">Error loading configuration</p>';
    }
}

// Render detailed section configuration
function renderSectionDetail(sectionId, section) {
    const content = document.getElementById(`content-${sectionId}`);
    
    // Validation alerts
    let validationHtml = '';
    if (section.validation) {
        if (section.validation.errors && section.validation.errors.length > 0) {
            validationHtml += '<div class="validation-alerts">';
            section.validation.errors.forEach(error => {
                validationHtml += `<div class="validation-alert error"><strong>Error:</strong> ${error}</div>`;
            });
            validationHtml += '</div>';
        }
        if (section.validation.warnings && section.validation.warnings.length > 0) {
            validationHtml += '<div class="validation-alerts">';
            section.validation.warnings.forEach(warning => {
                validationHtml += `<div class="validation-alert warning"><strong>Warning:</strong> ${warning}</div>`;
            });
            validationHtml += '</div>';
        }
    }
    
    content.innerHTML = `
        ${validationHtml}
        
        <!-- Category Selection -->
        <div class="config-section">
            <h4>📂 Section Category ${section.category?.requires_submissions ? '<span class="config-section-required">*Required</span>' : ''}</h4>
            <select class="form-select" id="category-${sectionId}" onchange="handleCategoryChange(${sectionId})">
                <option value="">Select Category...</option>
                <!-- Will be populated dynamically -->
            </select>
            <small style="display: block; margin-top: 0.5rem; color: #666;">
                Category determines which configuration options are required
            </small>
        </div>
        
        <!-- Submission Permissions -->
        <div class="config-section" id="submission-section-${sectionId}" style="display: none;">
            <h4>👥 Submission Permissions <span class="config-section-required">*Required for Reporting category</span></h4>
            <p style="margin-bottom: 1rem; color: #666; font-size: 0.9rem;">Configure which roles can submit forms/reports</p>
            <div class="permissions-grid" id="submission-perms-${sectionId}"></div>
            <button class="add-permission-btn" onclick="addSubmissionPerm(${sectionId})">
                <i class="fas fa-plus"></i> Add Role
            </button>
        </div>
        
        <!-- Review Permissions -->
        <div class="config-section" id="review-section-${sectionId}" style="display: none;">
            <h4>👁️ Review Permissions <span class="config-section-required">*Required for Reporting category</span></h4>
            <p style="margin-bottom: 1rem; color: #666; font-size: 0.9rem;">Configure which roles can view and manage submissions</p>
            <div class="permissions-grid" id="review-perms-${sectionId}"></div>
            <button class="add-permission-btn" onclick="addReviewPerm(${sectionId})">
                <i class="fas fa-plus"></i> Add Role
            </button>
        </div>
        
        <!-- Notification Rules -->
        <div class="config-section" id="notification-section-${sectionId}" style="display: none;">
            <h4>🔔 Notification Rules</h4>
            <p style="margin-bottom: 1rem; color: #666; font-size: 0.9rem;">Configure who receives notifications and how</p>
            <div class="permissions-grid" id="notification-rules-${sectionId}"></div>
            <button class="add-permission-btn" onclick="addNotificationRule(${sectionId})">
                <i class="fas fa-plus"></i> Add Notification Rule
            </button>
        </div>
        
        <!-- Guidelines -->
        <div class="config-section" id="guidelines-section-${sectionId}" style="display: none;">
            <h4>📝 Guidelines & Instructions</h4>
            <p style="margin-bottom: 1rem; color: #666; font-size: 0.9rem;">Add instructions shown to users when submitting or reviewing</p>
            <div id="guidelines-${sectionId}"></div>
            <button class="add-permission-btn" onclick="addGuideline(${sectionId})">
                <i class="fas fa-plus"></i> Add Guideline
            </button>
        </div>
        
        <!-- Configuration Options -->
        <div class="config-section" id="config-options-${sectionId}" style="display: none;">
            <h4>⚙️ Additional Options</h4>
            <div class="checkbox-grid">
                <div class="checkbox-item">
                    <input type="checkbox" id="enable-status-${sectionId}">
                    <label for="enable-status-${sectionId}">Enable Status Tracking</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" id="enable-priority-${sectionId}">
                    <label for="enable-priority-${sectionId}">Enable Priority Levels</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" id="enable-attachments-${sectionId}">
                    <label for="enable-attachments-${sectionId}">Enable File Attachments</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" id="enable-notes-${sectionId}">
                    <label for="enable-notes-${sectionId}">Enable Follow-up Notes</label>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" id="enable-assignment-${sectionId}">
                    <label for="enable-assignment-${sectionId}">Enable Assignment</label>
                </div>
            </div>
        </div>
        
        <button class="save-config-btn" onclick="saveSectionConfig(${sectionId})">
            💾 Save Configuration
        </button>
    `;
    
    // Populate category dropdown
    // This will be done after content is rendered
    populateSectionConfig(sectionId, section);
}

// Placeholder functions - will implement in next file part
function handleCategoryChange(sectionId) { console.log('Category changed', sectionId); }
function addSubmissionPerm(sectionId) { console.log('Add submission perm', sectionId); }
function addReviewPerm(sectionId) { console.log('Add review perm', sectionId); }
function addNotificationRule(sectionId) { console.log('Add notification', sectionId); }
function addGuideline(sectionId) { console.log('Add guideline', sectionId); }
function saveSectionConfig(sectionId) { console.log('Save config', sectionId); }
function populateSectionConfig(sectionId, section) { console.log('Populate config', sectionId); }
function refreshSectionConfig() { loadSectionConfig(); }

// Initialize on tab load
document.addEventListener('DOMContentLoaded', function() {
    // Load when tab is clicked
    const sectionConfigTab = document.querySelector('[data-tab="section-config"]');
    if (sectionConfigTab) {
        sectionConfigTab.addEventListener('click', function() {
            if (sectionsConfigData.length === 0) {
                loadSectionConfig();
            }
        });
    }
});
</script>
