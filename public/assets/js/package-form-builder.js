/**
 * Package Form Builder
 * Dynamic form creation with drag-drop interface
 */

// =============================================================================
// State Management
// =============================================================================
let currentForm = null;
let currentFields = [];
let editingFieldIndex = null;

// =============================================================================
// Form Builder Initialization
// =============================================================================

/**
 * Load form builder for a package
 */
async function loadFormBuilder(packageId, packageName) {
    console.log(`Loading form builder for package ${packageId}`);

    const modal = createModal(`Form Builder - ${packageName}`, `
        <div class="form-builder-container">
            <!-- Form List -->
            <div class="form-builder-sidebar">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="margin: 0;">Forms</h3>
                    <button class="btn btn-sm btn-primary" onclick="showCreateFormDialog(${packageId})">
                        <i class="fas fa-plus"></i> New Form
                    </button>
                </div>
                <div id="formsList" style="min-height: 200px;">
                    <div style="text-align: center; padding: 2rem; color: #94a3b8;">
                        Loading forms...
                    </div>
                </div>
            </div>

            <!-- Form Editor -->
            <div class="form-builder-main">
                <div id="formEditorPlaceholder" style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94a3b8;">
                    <div style="text-align: center;">
                        <i class="fas fa-file-alt" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                        <p>Select a form to edit or create a new one</p>
                    </div>
                </div>
                <div id="formEditor" style="display: none;">
                    <!-- Form settings will be rendered here -->
                </div>
            </div>
        </div>
    `, 'modal-xl');

    // Load forms for this package
    await refreshFormsList(packageId);
}

/**
 * Refresh the forms list
 */
async function refreshFormsList(packageId) {
    try {
        const response = await fetch(`/api/package-forms.php?action=list&package_id=${packageId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load forms');
        }

        renderFormsList(result.forms, packageId);
    } catch (error) {
        console.error('Error loading forms:', error);
        document.getElementById('formsList').innerHTML = `
            <div style="color: #ef4444; padding: 1rem; text-align: center;">
                Error loading forms: ${error.message}
            </div>
        `;
    }
}

/**
 * Render forms list
 */
function renderFormsList(forms, packageId) {
    const container = document.getElementById('formsList');

    if (forms.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #94a3b8;">
                No forms yet. Click "New Form" to create one.
            </div>
        `;
        return;
    }

    container.innerHTML = forms.map(form => `
        <div class="form-list-item" data-form-id="${form.id}" onclick="loadFormEditor(${form.id})">
            <div>
                <i class="${form.icon}"></i>
                <strong>${escapeHtml(form.name)}</strong>
                <span class="badge badge-info">${form.context}</span>
            </div>
            <button class="btn-icon btn-danger btn-sm" onclick="event.stopPropagation(); deleteFormConfirm(${form.id}, '${escapeHtml(form.name)}', ${packageId})" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `).join('');
}

/**
 * Show create form dialog
 */
function showCreateFormDialog(packageId) {
    const dialog = createModal('Create New Form', `
        <form id="createFormForm">
            <input type="hidden" name="package_id" value="${packageId}">

            <div class="form-group">
                <label>Form Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="Maintenance Request">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Submit maintenance and repair requests"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Context <span style="color: #ef4444;">*</span></label>
                    <select name="context" class="form-control" required>
                        <option value="hub">Hub (User-facing)</option>
                        <option value="management">Management Console</option>
                        <option value="admin">Admin Dashboard</option>
                    </select>
                </div>

                <div class="form-group" style="flex: 1;">
                    <label>Icon</label>
                    <input type="text" name="icon" class="form-control" value="bi-file-text" placeholder="bi-file-text">
                    <small style="color: #64748b;">Bootstrap Icons (bi-*) or Font Awesome (fas fa-*)</small>
                </div>
            </div>

            <div class="form-group">
                <label>Success Message</label>
                <textarea name="success_message" class="form-control" rows="2" placeholder="Your request has been submitted successfully!"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Form
                </button>
            </div>
        </form>
    `);

    document.getElementById('createFormForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await handleCreateForm(e.target, packageId);
    });
}

/**
 * Handle create form submission
 */
async function handleCreateForm(form, packageId) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    try {
        const response = await fetch('/api/package-forms.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to create form');
        }

        showMessage('Form created successfully', 'success');
        closeModal();
        await refreshFormsList(packageId);
        
        // Load the new form in editor
        await loadFormEditor(result.form_id);
    } catch (error) {
        console.error('Error creating form:', error);
        showMessage('Failed to create form: ' + error.message, 'error');
    }
}

/**
 * Load form editor
 */
async function loadFormEditor(formId) {
    try {
        const response = await fetch(`/api/package-forms.php?action=get&form_id=${formId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load form');
        }

        currentForm = result.form;
        currentFields = result.form.fields || [];

        renderFormEditor();
    } catch (error) {
        console.error('Error loading form:', error);
        showMessage('Failed to load form: ' + error.message, 'error');
    }
}

/**
 * Render form editor
 */
function renderFormEditor() {
    document.getElementById('formEditorPlaceholder').style.display = 'none';
    document.getElementById('formEditor').style.display = 'block';

    document.getElementById('formEditor').innerHTML = `
        <div class="form-editor-header">
            <div>
                <h3>${escapeHtml(currentForm.name)}</h3>
                <p style="color: #64748b; margin: 0;">${escapeHtml(currentForm.description || 'No description')}</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn btn-sm btn-secondary" onclick="editFormSettings()">
                    <i class="fas fa-cog"></i> Settings
                </button>
                <button class="btn btn-sm btn-primary" onclick="showAddFieldDialog()">
                    <i class="fas fa-plus"></i> Add Field
                </button>
            </div>
        </div>

        <div class="form-editor-body">
            <div class="form-fields-list" id="formFieldsList">
                ${currentFields.length === 0 ? `
                    <div style="text-align: center; padding: 3rem; color: #94a3b8;">
                        <i class="fas fa-file-alt" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>No fields yet. Click "Add Field" to start building your form.</p>
                    </div>
                ` : renderFieldsList()}
            </div>

            <div class="form-preview">
                <h4 style="margin-bottom: 1rem;">Preview</h4>
                <div class="form-preview-content">
                    ${renderFormPreview()}
                </div>
            </div>
        </div>
    `;
}

/**
 * Render fields list
 */
function renderFieldsList() {
    return currentFields.map((field, index) => `
        <div class="field-item" data-field-index="${index}">
            <div class="field-item-handle">
                <i class="fas fa-grip-vertical"></i>
            </div>
            <div class="field-item-content">
                <div class="field-item-header">
                    <div>
                        <strong>${escapeHtml(field.label)}</strong>
                        <span class="badge badge-secondary">${field.field_type}</span>
                        ${field.is_required ? '<span class="badge badge-danger">Required</span>' : ''}
                    </div>
                    <code style="color: #64748b; font-size: 0.875rem;">${field.field_key}</code>
                </div>
                ${field.help_text ? `<p style="margin: 0.5rem 0 0 0; color: #64748b; font-size: 0.875rem;">${escapeHtml(field.help_text)}</p>` : ''}
            </div>
            <div class="field-item-actions">
                <button class="btn-icon" onclick="editField(${index})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon btn-danger" onclick="deleteField(${index})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

/**
 * Render form preview
 */
function renderFormPreview() {
    if (currentFields.length === 0) {
        return '<p style="color: #94a3b8; text-align: center;">Add fields to see preview</p>';
    }

    return currentFields.map(field => {
        let input = '';

        switch (field.field_type) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
            case 'number':
                input = `<input type="${field.field_type}" class="form-control" placeholder="${escapeHtml(field.placeholder || '')}" ${field.is_required ? 'required' : ''}>`;
                break;

            case 'textarea':
                input = `<textarea class="form-control" rows="3" placeholder="${escapeHtml(field.placeholder || '')}" ${field.is_required ? 'required' : ''}></textarea>`;
                break;

            case 'date':
            case 'datetime':
            case 'time':
                input = `<input type="${field.field_type === 'datetime' ? 'datetime-local' : field.field_type}" class="form-control" ${field.is_required ? 'required' : ''}>`;
                break;

            case 'dropdown':
                const options = field.options || [];
                input = `
                    <select class="form-control" ${field.is_required ? 'required' : ''}>
                        <option value="">Select...</option>
                        ${options.map(opt => `<option value="${escapeHtml(opt.value)}">${escapeHtml(opt.label)}</option>`).join('')}
                    </select>
                `;
                break;

            case 'radio':
                const radioOptions = field.options || [];
                input = radioOptions.map((opt, i) => `
                    <div class="form-check">
                        <input type="radio" class="form-check-input" name="${field.field_key}" value="${escapeHtml(opt.value)}" id="${field.field_key}_${i}">
                        <label class="form-check-label" for="${field.field_key}_${i}">${escapeHtml(opt.label)}</label>
                    </div>
                `).join('');
                break;

            case 'checkbox':
                input = `
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="${field.field_key}">
                        <label class="form-check-label" for="${field.field_key}">${escapeHtml(field.label)}</label>
                    </div>
                `;
                break;

            default:
                input = `<input type="text" class="form-control" placeholder="Unsupported field type: ${field.field_type}">`;
        }

        return `
            <div class="form-group">
                <label>
                    ${escapeHtml(field.label)}
                    ${field.is_required ? '<span style="color: #ef4444;">*</span>' : ''}
                </label>
                ${input}
                ${field.help_text ? `<small style="color: #64748b;">${escapeHtml(field.help_text)}</small>` : ''}
            </div>
        `;
    }).join('');
}

// Continue in next file...

// =============================================================================
// Field Management
// =============================================================================

/**
 * Show add field dialog
 */
function showAddFieldDialog() {
    editingFieldIndex = null;
    showFieldDialog({
        field_key: '',
        label: '',
        field_type: 'text',
        placeholder: '',
        help_text: '',
        is_required: 0,
        display_order: currentFields.length,
        options: []
    });
}

/**
 * Edit existing field
 */
function editField(index) {
    editingFieldIndex = index;
    showFieldDialog(currentFields[index]);
}

/**
 * Show field configuration dialog
 */
function showFieldDialog(fieldData) {
    const isEdit = editingFieldIndex !== null;

    const modal = createModal(isEdit ? 'Edit Field' : 'Add Field', `
        <form id="fieldConfigForm">
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label>Field Label <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="label" class="form-control" required value="${escapeHtml(fieldData.label || '')}">
                </div>

                <div class="form-group" style="flex: 1;">
                    <label>Field Key <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="field_key" class="form-control" required value="${escapeHtml(fieldData.field_key || '')}" pattern="[a-z_][a-z0-9_]*" ${isEdit ? 'readonly' : ''}>
                    <small style="color: #64748b;">Lowercase, underscores only (e.g., priority_level)</small>
                </div>
            </div>

            <div class="form-group">
                <label>Field Type <span style="color: #ef4444;">*</span></label>
                <select name="field_type" class="form-control" required onchange="handleFieldTypeChange(this.value)">
                    <option value="text" ${fieldData.field_type === 'text' ? 'selected' : ''}>Text</option>
                    <option value="textarea" ${fieldData.field_type === 'textarea' ? 'selected' : ''}>Text Area</option>
                    <option value="number" ${fieldData.field_type === 'number' ? 'selected' : ''}>Number</option>
                    <option value="email" ${fieldData.field_type === 'email' ? 'selected' : ''}>Email</option>
                    <option value="tel" ${fieldData.field_type === 'tel' ? 'selected' : ''}>Phone</option>
                    <option value="date" ${fieldData.field_type === 'date' ? 'selected' : ''}>Date</option>
                    <option value="datetime" ${fieldData.field_type === 'datetime' ? 'selected' : ''}>Date & Time</option>
                    <option value="time" ${fieldData.field_type === 'time' ? 'selected' : ''}>Time</option>
                    <option value="dropdown" ${fieldData.field_type === 'dropdown' ? 'selected' : ''}>Dropdown</option>
                    <option value="radio" ${fieldData.field_type === 'radio' ? 'selected' : ''}>Radio Buttons</option>
                    <option value="checkbox" ${fieldData.field_type === 'checkbox' ? 'selected' : ''}>Checkbox</option>
                    <option value="file" ${fieldData.field_type === 'file' ? 'selected' : ''}>File Upload</option>
                </select>
            </div>

            <div class="form-group">
                <label>Placeholder</label>
                <input type="text" name="placeholder" class="form-control" value="${escapeHtml(fieldData.placeholder || '')}">
            </div>

            <div class="form-group">
                <label>Help Text</label>
                <textarea name="help_text" class="form-control" rows="2">${escapeHtml(fieldData.help_text || '')}</textarea>
            </div>

            <div id="optionsContainer" style="display: ${['dropdown', 'radio', 'multi_select'].includes(fieldData.field_type) ? 'block' : 'none'};">
                <div class="form-group">
                    <label>Options</label>
                    <div id="optionsList">
                        ${(fieldData.options || []).map((opt, i) => `
                            <div class="option-item" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <input type="text" class="form-control" placeholder="Value" value="${escapeHtml(opt.value)}" onchange="updateOption(${i}, 'value', this.value)">
                                <input type="text" class="form-control" placeholder="Label" value="${escapeHtml(opt.label)}" onchange="updateOption(${i}, 'label', this.value)">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeOption(${i})">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addOption()">
                        <i class="fas fa-plus"></i> Add Option
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_required" value="1" ${fieldData.is_required ? 'checked' : ''}>
                    Required Field
                </label>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> ${isEdit ? 'Update' : 'Add'} Field
                </button>
            </div>
        </form>
    `, 'modal-lg');

    // Store options in form data attribute
    document.getElementById('fieldConfigForm').dataset.options = JSON.stringify(fieldData.options || []);

    document.getElementById('fieldConfigForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await handleSaveField(e.target);
    });
}

/**
 * Handle field type change
 */
function handleFieldTypeChange(fieldType) {
    const optionsContainer = document.getElementById('optionsContainer');
    optionsContainer.style.display = ['dropdown', 'radio', 'multi_select'].includes(fieldType) ? 'block' : 'none';
}

/**
 * Update option in temporary storage
 */
function updateOption(index, key, value) {
    const form = document.getElementById('fieldConfigForm');
    const options = JSON.parse(form.dataset.options || '[]');
    options[index][key] = value;
    form.dataset.options = JSON.stringify(options);
}

/**
 * Add new option
 */
function addOption() {
    const form = document.getElementById('fieldConfigForm');
    const options = JSON.parse(form.dataset.options || '[]');
    options.push({ value: '', label: '' });
    form.dataset.options = JSON.stringify(options);

    const index = options.length - 1;
    const optionsList = document.getElementById('optionsList');
    optionsList.insertAdjacentHTML('beforeend', `
        <div class="option-item" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
            <input type="text" class="form-control" placeholder="Value" onchange="updateOption(${index}, 'value', this.value)">
            <input type="text" class="form-control" placeholder="Label" onchange="updateOption(${index}, 'label', this.value)">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeOption(${index})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `);
}

/**
 * Remove option
 */
function removeOption(index) {
    const form = document.getElementById('fieldConfigForm');
    const options = JSON.parse(form.dataset.options || '[]');
    options.splice(index, 1);
    form.dataset.options = JSON.stringify(options);

    // Re-render options list
    const fieldType = form.querySelector('[name="field_type"]').value;
    if (['dropdown', 'radio', 'multi_select'].includes(fieldType)) {
        document.getElementById('optionsList').innerHTML = options.map((opt, i) => `
            <div class="option-item" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                <input type="text" class="form-control" placeholder="Value" value="${escapeHtml(opt.value)}" onchange="updateOption(${i}, 'value', this.value)">
                <input type="text" class="form-control" placeholder="Label" value="${escapeHtml(opt.label)}" onchange="updateOption(${i}, 'label', this.value)">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeOption(${i})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    }
}

/**
 * Save field
 */
async function handleSaveField(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Add options from temporary storage
    data.options = JSON.parse(form.dataset.options || '[]');
    data.form_id = currentForm.id;

    try {
        const isEdit = editingFieldIndex !== null;
        
        if (isEdit) {
            // Update existing field
            const fieldId = currentFields[editingFieldIndex].id;
            data.id = fieldId;

            const response = await fetch('/api/package-forms.php?action=update-field', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            });

            const result = await response.json();
            if (!result.success) throw new Error(result.error);

            currentFields[editingFieldIndex] = { ...currentFields[editingFieldIndex], ...data };
        } else {
            // Add new field
            const response = await fetch('/api/package-forms.php?action=add-field', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (!result.success) throw new Error(result.error);

            data.id = result.field_id;
            currentFields.push(data);
        }

        showMessage(isEdit ? 'Field updated successfully' : 'Field added successfully', 'success');
        closeModal();
        renderFormEditor();
    } catch (error) {
        console.error('Error saving field:', error);
        showMessage('Failed to save field: ' + error.message, 'error');
    }
}

/**
 * Delete field
 */
async function deleteField(index) {
    if (!confirm('Delete this field? All associated data will be lost.')) {
        return;
    }

    const field = currentFields[index];

    try {
        const response = await fetch('/api/package-forms.php?action=delete-field', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${field.id}`
        });

        const result = await response.json();
        if (!result.success) throw new Error(result.error);

        currentFields.splice(index, 1);
        showMessage('Field deleted successfully', 'success');
        renderFormEditor();
    } catch (error) {
        console.error('Error deleting field:', error);
        showMessage('Failed to delete field: ' + error.message, 'error');
    }
}

/**
 * Delete form confirmation
 */
async function deleteFormConfirm(formId, formName, packageId) {
    if (!confirm(`Delete form "${formName}"? All fields and submissions will be lost.`)) {
        return;
    }

    try {
        const response = await fetch('/api/package-forms.php?action=delete', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${formId}`
        });

        const result = await response.json();
        if (!result.success) throw new Error(result.error);

        showMessage('Form deleted successfully', 'success');
        
        // Clear editor if this form was being edited
        if (currentForm && currentForm.id === formId) {
            currentForm = null;
            currentFields = [];
            document.getElementById('formEditor').style.display = 'none';
            document.getElementById('formEditorPlaceholder').style.display = 'flex';
        }

        await refreshFormsList(packageId);
    } catch (error) {
        console.error('Error deleting form:', error);
        showMessage('Failed to delete form: ' + error.message, 'error');
    }
}

console.log('✅ Package Form Builder loaded');
