/**
 * Package Creator Wizard — Visual Package Builder
 *
 * Multi-step wizard that guides users through creating package.json files.
 * Each step corresponds to a section of the package definition:
 *   1. Package Info (name, category, icon, description)
 *   2. Database (connection, primaryTable)
 *   3. Fields (define your data columns)
 *   4. Pages & Components (pick "stacks" and arrange them)
 *   5. Policy (roles, access mapping)
 *   6. Preview & Validate (see JSON, fix errors, download)
 *
 * Uses the ComponentSchema API for real-time validation and
 * the scaffold endpoint to generate valid package.json.
 */

document.addEventListener('DOMContentLoaded', () => {
    const wizard = document.getElementById('package-wizard');
    if (!wizard) return;

    const state = {
        step: 1,
        totalSteps: 6,
        // Step 1: Package info
        name: '',
        category: 'local',
        icon: 'lucide-package',
        description: '',
        author: 'Woodson ISD',
        // Step 2: Database
        database: '',
        tableName: '',
        // Step 3: Fields
        fields: [],
        // Step 4: Pages
        pages: [],
        // Step 5: Policy
        roles: ['viewer', 'user', 'manager', 'admin'],
        defaultRole: 'user',
        // Step 6: Generated
        generatedJson: null,
        validation: null,
    };

    // Available icons for picker
    const iconOptions = [
        'lucide-package', 'lucide-users', 'lucide-truck', 'lucide-graduation-cap',
        'lucide-clipboard-edit', 'lucide-calendar', 'lucide-file-text', 'lucide-wrench',
        'lucide-building', 'lucide-mail', 'lucide-bell', 'lucide-shield', 'lucide-database',
        'lucide-bar-chart', 'lucide-map-pin', 'lucide-phone', 'lucide-book-open',
        'lucide-briefcase', 'lucide-heart', 'lucide-star', 'lucide-settings',
        'lucide-folder', 'lucide-globe', 'lucide-key', 'lucide-credit-card',
    ];

    // Field types from ComponentSchema
    const fieldTypes = {
        'text':     { label: 'Text',          icon: 'bi-fonts',           description: 'Single-line text' },
        'textarea': { label: 'Long Text',     icon: 'bi-text-paragraph',  description: 'Multi-line text area' },
        'number':   { label: 'Number',        icon: 'bi-123',             description: 'Numeric input' },
        'currency': { label: 'Currency ($)',   icon: 'bi-currency-dollar', description: 'Dollar amount' },
        'email':    { label: 'Email',         icon: 'bi-envelope',        description: 'Email address' },
        'date':     { label: 'Date',          icon: 'bi-calendar',        description: 'Date picker' },
        'datetime': { label: 'Date & Time',   icon: 'bi-calendar-event',  description: 'Date and time picker' },
        'select':   { label: 'Dropdown',      icon: 'bi-list',            description: 'Select from options' },
        'radio':    { label: 'Radio Buttons',  icon: 'bi-ui-radios',      description: 'Choose one option' },
        'checkbox': { label: 'Checkbox',      icon: 'bi-check-square',    description: 'Yes/No toggle' },
        'file':     { label: 'File Upload',   icon: 'bi-upload',          description: 'File attachment' },
        'tel':      { label: 'Phone',         icon: 'bi-telephone',       description: 'Telephone number' },
        'url':      { label: 'URL',           icon: 'bi-link',            description: 'Web address' },
    };

    // Component stack types
    const stackTypes = {
        'dashboard': { label: 'Dashboard',  icon: 'bi-speedometer2',   description: 'KPI cards & charts — shows at-a-glance stats' },
        'filters':   { label: 'Filters',    icon: 'bi-funnel',         description: 'Search & filter bar — targets a data table' },
        'table':     { label: 'Data Table', icon: 'bi-table',          description: 'Paginated, sortable table with row actions' },
        'form':      { label: 'Form',       icon: 'bi-pencil-square',  description: 'Create/edit form with sections & validation' },
        'detail':    { label: 'Detail View', icon: 'bi-file-earmark-text', description: 'Read-only record detail with sections' },
    };

    // ── RENDER ──────────────────────────────────────────

    function render() {
        wizard.innerHTML = `
            ${renderProgressBar()}
            <div class="wizard-body">
                ${renderStep()}
            </div>
            ${renderNavigation()}
        `;
        attachEventListeners();
    }

    function renderProgressBar() {
        const steps = ['Package Info', 'Database', 'Fields', 'Pages', 'Policy', 'Preview'];
        return `<div class="wizard-progress">
            ${steps.map((label, i) => {
                const num = i + 1;
                const cls = num < state.step ? 'completed' : num === state.step ? 'active' : '';
                return `<div class="wizard-step-indicator ${cls}" data-step="${num}">
                    <div class="step-number">${num < state.step ? '<i class="bi bi-check-lg"></i>' : num}</div>
                    <div class="step-label">${label}</div>
                </div>`;
            }).join('<div class="step-connector"></div>')}
        </div>`;
    }

    function renderStep() {
        switch (state.step) {
            case 1: return renderStep1();
            case 2: return renderStep2();
            case 3: return renderStep3();
            case 4: return renderStep4();
            case 5: return renderStep5();
            case 6: return renderStep6();
        }
    }

    // ── STEP 1: Package Info ────────────────────────────

    function renderStep1() {
        return `
        <h3><i class="bi bi-box-seam"></i> Package Information</h3>
        <p class="wizard-desc">Start by naming your package and selecting a category. This is like naming your app.</p>
        <div class="wizard-form-grid">
            <div class="wizard-field">
                <label>Package Name <span class="required">*</span></label>
                <input type="text" id="wiz-name" value="${esc(state.name)}" placeholder="e.g., Student Directory"
                       class="form-control" maxlength="100" required>
                <small class="text-muted">Human-readable name for your package</small>
            </div>
            <div class="wizard-field">
                <label>Category <span class="required">*</span></label>
                <select id="wiz-category" class="form-select">
                    ${['district', 'operations', 'local', 'admin'].map(c =>
                        `<option value="${c}"${state.category === c ? ' selected' : ''}>${c.charAt(0).toUpperCase() + c.slice(1)}</option>`
                    ).join('')}
                </select>
                <small class="text-muted">Determines where the package lives in the file system</small>
            </div>
            <div class="wizard-field wizard-field-full">
                <label>Description</label>
                <textarea id="wiz-description" class="form-control" rows="2" maxlength="500"
                          placeholder="Brief description of what this package does...">${esc(state.description)}</textarea>
            </div>
            <div class="wizard-field">
                <label>Author</label>
                <input type="text" id="wiz-author" value="${esc(state.author)}" class="form-control"
                       placeholder="Woodson ISD">
            </div>
            <div class="wizard-field">
                <label>Icon</label>
                <div class="icon-picker" id="wiz-icon-picker">
                    <div class="icon-picker-selected" id="wiz-icon-selected" tabindex="0">
                        <i class="${iconClass(state.icon)}"></i>
                        <span>${state.icon}</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="icon-picker-dropdown" id="wiz-icon-dropdown" style="display:none;">
                        ${iconOptions.map(ic => `
                            <div class="icon-option${ic === state.icon ? ' selected' : ''}" data-icon="${ic}">
                                <i class="${iconClass(ic)}"></i>
                                <span>${ic.replace('lucide-', '')}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        </div>
        ${state.name ? `<div class="wizard-preview-badge">
            <strong>Package ID:</strong> <code>${state.category}.${slugify(state.name)}</code>
        </div>` : ''}`;
    }

    // ── STEP 2: Database ────────────────────────────────

    function renderStep2() {
        const autoDb = state.database || (state.name ? 'woodson_' + slugify(state.name).replace(/-/g, '_') : '');
        const autoTable = state.tableName || (state.name ? slugify(state.name).replace(/-/g, '_') : '');

        return `
        <h3><i class="bi bi-database"></i> Database Configuration</h3>
        <p class="wizard-desc">Tell us which database and table your package reads from. The GenericPackageHandler will auto-generate SQL from these settings.</p>
        <div class="wizard-form-grid">
            <div class="wizard-field">
                <label>Database Name <span class="required">*</span></label>
                <input type="text" id="wiz-database" value="${esc(autoDb)}" class="form-control"
                       placeholder="woodson_students" pattern="^[a-z][a-z0-9_]+$">
                <small class="text-muted">MySQL database name (lowercase, underscores)</small>
            </div>
            <div class="wizard-field">
                <label>Primary Table <span class="required">*</span></label>
                <input type="text" id="wiz-tableName" value="${esc(autoTable)}" class="form-control"
                       placeholder="students" pattern="^[a-z][a-z0-9_]+$">
                <small class="text-muted">Main data table (queries will target this)</small>
            </div>
        </div>
        <div class="wizard-info-box">
            <i class="bi bi-info-circle"></i>
            <div>
                <strong>How it works:</strong> The GenericPackageHandler reads these settings to auto-build
                SQL queries. When someone searches, filters, or sorts — it generates <code>SELECT</code>
                statements against your table automatically. No PHP code needed!
            </div>
        </div>`;
    }

    // ── STEP 3: Fields ──────────────────────────────────

    function renderStep3() {
        return `
        <h3><i class="bi bi-list-columns-reverse"></i> Define Your Fields</h3>
        <p class="wizard-desc">Fields are the columns in your database and the inputs in your forms. Each field becomes a table column, filter option, form input, AND detail field — all from this one definition.</p>

        <div class="wizard-fields-list" id="wiz-fields-list">
            ${state.fields.map((f, i) => renderFieldRow(f, i)).join('')}
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm" id="wiz-add-field">
            <i class="bi bi-plus-lg"></i> Add Field
        </button>

        <div class="wizard-field-types-ref" style="margin-top: 1.5rem;">
            <h5>Available Field Types</h5>
            <div class="field-type-grid">
                ${Object.entries(fieldTypes).map(([type, info]) => `
                    <div class="field-type-badge" title="${info.description}">
                        <i class="${info.icon}"></i> ${info.label}
                    </div>
                `).join('')}
            </div>
        </div>`;
    }

    function renderFieldRow(field, index) {
        return `
        <div class="wizard-field-row" data-index="${index}">
            <div class="field-row-drag"><i class="bi bi-grip-vertical"></i></div>
            <div class="field-row-body">
                <div class="field-row-main">
                    <input type="text" class="form-control form-control-sm wiz-field-key"
                           value="${esc(field.key)}" placeholder="column_name" data-index="${index}">
                    <select class="form-select form-select-sm wiz-field-type" data-index="${index}">
                        ${Object.entries(fieldTypes).map(([t, info]) =>
                            `<option value="${t}"${field.type === t ? ' selected' : ''}>${info.label}</option>`
                        ).join('')}
                    </select>
                    <input type="text" class="form-control form-control-sm wiz-field-label"
                           value="${esc(field.label)}" placeholder="Display Label" data-index="${index}">
                </div>
                <div class="field-row-options">
                    <label class="form-check-inline">
                        <input type="checkbox" class="form-check-input wiz-field-required"
                               data-index="${index}" ${field.required ? 'checked' : ''}>
                        <span class="form-check-label">Required</span>
                    </label>
                    <label class="form-check-inline">
                        <input type="checkbox" class="form-check-input wiz-field-sortable"
                               data-index="${index}" ${field.sortable !== false ? 'checked' : ''}>
                        <span class="form-check-label">Sortable</span>
                    </label>
                    <label class="form-check-inline">
                        <input type="checkbox" class="form-check-input wiz-field-searchable"
                               data-index="${index}" ${field.searchable ? 'checked' : ''}>
                        <span class="form-check-label">Searchable</span>
                    </label>
                </div>
                ${field.type === 'select' || field.type === 'radio' ? renderOptionsEditor(field, index) : ''}
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger wiz-remove-field" data-index="${index}">
                <i class="bi bi-trash"></i>
            </button>
        </div>`;
    }

    function renderOptionsEditor(field, index) {
        const options = field.options || [];
        return `
        <div class="field-options-editor" data-index="${index}">
            <small class="text-muted">Options (value → label):</small>
            <div class="options-list">
                ${options.map((opt, j) => `
                    <div class="option-row">
                        <input type="text" class="form-control form-control-sm wiz-option-value"
                               value="${esc(opt.value)}" placeholder="value" data-field="${index}" data-opt="${j}">
                        <span>→</span>
                        <input type="text" class="form-control form-control-sm wiz-option-label"
                               value="${esc(opt.label)}" placeholder="label" data-field="${index}" data-opt="${j}">
                        <button type="button" class="btn btn-xs btn-outline-danger wiz-remove-option"
                                data-field="${index}" data-opt="${j}"><i class="bi bi-x"></i></button>
                    </div>
                `).join('')}
            </div>
            <button type="button" class="btn btn-xs btn-outline-secondary wiz-add-option" data-field="${index}">
                <i class="bi bi-plus"></i> Add Option
            </button>
        </div>`;
    }

    // ── STEP 4: Pages & Components ──────────────────────

    function renderStep4() {
        if (state.pages.length === 0) {
            // Default pages
            state.pages = [
                { id: 'index', title: state.name || 'Home', route: '/', components: ['dashboard', 'filters', 'table'] },
                { id: 'add', title: 'Add Record', route: '/add', components: ['form'] },
                { id: 'detail', title: 'Record Detail', route: '/{id}', components: ['detail'] },
            ];
        }

        return `
        <h3><i class="bi bi-layout-text-sidebar-reverse"></i> Pages & Components (Stacks)</h3>
        <p class="wizard-desc">Build your app's pages by choosing component "stacks." Each stack type has defined rules — the system validates everything automatically.</p>

        <div class="wizard-pages-list" id="wiz-pages-list">
            ${state.pages.map((p, i) => renderPageCard(p, i)).join('')}
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm" id="wiz-add-page">
            <i class="bi bi-plus-lg"></i> Add Page
        </button>

        <div class="wizard-stacks-ref" style="margin-top: 1.5rem;">
            <h5>Available Stack Types</h5>
            <div class="stack-type-grid">
                ${Object.entries(stackTypes).map(([type, info]) => `
                    <div class="stack-type-card">
                        <i class="${info.icon}"></i>
                        <strong>${info.label}</strong>
                        <small>${info.description}</small>
                    </div>
                `).join('')}
            </div>
        </div>`;
    }

    function renderPageCard(page, index) {
        return `
        <div class="wizard-page-card" data-index="${index}">
            <div class="page-card-header">
                <div class="page-card-title">
                    <input type="text" class="form-control form-control-sm wiz-page-title"
                           value="${esc(page.title)}" data-index="${index}" placeholder="Page Title">
                    <input type="text" class="form-control form-control-sm wiz-page-route"
                           value="${esc(page.route)}" data-index="${index}" placeholder="/route"
                           style="max-width: 200px;">
                </div>
                ${index > 0 ? `<button type="button" class="btn btn-xs btn-outline-danger wiz-remove-page" data-index="${index}">
                    <i class="bi bi-trash"></i></button>` : ''}
            </div>
            <div class="page-card-stacks">
                ${(page.components || []).map((comp, j) => {
                    const compType = typeof comp === 'string' ? comp : comp.type;
                    const info = stackTypes[compType] || { label: compType, icon: 'bi-puzzle' };
                    return `
                    <div class="page-stack-chip" data-page="${index}" data-comp="${j}">
                        <i class="${info.icon}"></i> ${info.label}
                        <button type="button" class="stack-chip-remove wiz-remove-comp"
                                data-page="${index}" data-comp="${j}"><i class="bi bi-x"></i></button>
                    </div>`;
                }).join('')}
                <div class="page-add-stack">
                    <select class="form-select form-select-sm wiz-add-comp-select" data-page="${index}">
                        <option value="">+ Add Stack...</option>
                        ${Object.entries(stackTypes).map(([type, info]) =>
                            `<option value="${type}">${info.label}</option>`
                        ).join('')}
                    </select>
                </div>
            </div>
        </div>`;
    }

    // ── STEP 5: Policy ──────────────────────────────────

    function renderStep5() {
        return `
        <h3><i class="bi bi-shield-lock"></i> Access Policy</h3>
        <p class="wizard-desc">Define who can access your package. The system maps global Hub roles to your package roles automatically.</p>

        <div class="wizard-form-grid">
            <div class="wizard-field">
                <label>Default Role</label>
                <select id="wiz-defaultRole" class="form-select">
                    ${state.roles.map(r =>
                        `<option value="${r}"${state.defaultRole === r ? ' selected' : ''}>${r}</option>`
                    ).join('')}
                </select>
                <small class="text-muted">Role assigned to users who aren't specifically granted a role</small>
            </div>
        </div>

        <h5 style="margin-top: 1.5rem;">Global Role Mapping</h5>
        <p class="text-muted">Map Hub system roles → your package roles</p>
        <table class="table table-sm" style="max-width: 500px;">
            <thead><tr><th>System Role</th><th>→</th><th>Package Role</th></tr></thead>
            <tbody>
                ${['super_admin', 'admin', 'manager', 'user', 'viewer'].map(sr => {
                    const defaultMap = { super_admin: 'admin', admin: 'admin', manager: 'manager', user: 'user', viewer: 'viewer' };
                    return `<tr>
                        <td><code>${sr}</code></td>
                        <td>→</td>
                        <td>
                            <select class="form-select form-select-sm wiz-role-map" data-system-role="${sr}">
                                ${state.roles.map(r =>
                                    `<option value="${r}"${r === defaultMap[sr] ? ' selected' : ''}>${r}</option>`
                                ).join('')}
                            </select>
                        </td>
                    </tr>`;
                }).join('')}
            </tbody>
        </table>`;
    }

    // ── STEP 6: Preview & Validate ──────────────────────

    function renderStep6() {
        return `
        <h3><i class="bi bi-code-square"></i> Preview & Validate</h3>
        <p class="wizard-desc">Review your generated package.json. The validator checks all component rules and cross-references automatically.</p>

        <div class="wizard-actions-bar">
            <button type="button" class="btn btn-primary" id="wiz-generate">
                <i class="bi bi-magic"></i> Generate Package JSON
            </button>
            <button type="button" class="btn btn-success" id="wiz-download" ${state.generatedJson ? '' : 'disabled'}>
                <i class="bi bi-download"></i> Download package.json
            </button>
            <button type="button" class="btn btn-outline-secondary" id="wiz-copy" ${state.generatedJson ? '' : 'disabled'}>
                <i class="bi bi-clipboard"></i> Copy to Clipboard
            </button>
        </div>

        ${state.validation ? renderValidationResult(state.validation) : ''}

        <div class="wizard-json-preview" id="wiz-json-preview">
            ${state.generatedJson
                ? `<pre><code>${esc(JSON.stringify(state.generatedJson, null, 2))}</code></pre>`
                : '<div class="text-muted text-center" style="padding:3rem;">Click "Generate" to see your package.json</div>'
            }
        </div>`;
    }

    function renderValidationResult(validation) {
        const { valid, errors, warnings, summary } = validation;
        const cls = valid ? 'validation-success' : 'validation-error';
        const icon = valid ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';

        let html = `<div class="wizard-validation ${cls}">
            <div class="validation-header">
                <i class="bi ${icon}"></i> <strong>${summary}</strong>
            </div>`;

        if (errors && errors.length > 0) {
            html += '<div class="validation-list">';
            errors.forEach(e => {
                html += `<div class="validation-item error">
                    <span class="validation-path">${esc(e.path)}</span>
                    <span class="validation-msg">${esc(e.message)}</span>
                    ${e.fix ? `<span class="validation-fix"><i class="bi bi-lightbulb"></i> ${esc(e.fix)}</span>` : ''}
                </div>`;
            });
            html += '</div>';
        }

        if (warnings && warnings.length > 0) {
            html += '<div class="validation-list">';
            warnings.forEach(w => {
                html += `<div class="validation-item warning">
                    <span class="validation-path">${esc(w.path)}</span>
                    <span class="validation-msg">${esc(w.message)}</span>
                </div>`;
            });
            html += '</div>';
        }

        html += '</div>';
        return html;
    }

    function renderNavigation() {
        return `
        <div class="wizard-nav">
            ${state.step > 1 ? `<button type="button" class="btn btn-outline-secondary" id="wiz-prev">
                <i class="bi bi-arrow-left"></i> Previous</button>` : '<div></div>'}
            ${state.step < state.totalSteps ? `<button type="button" class="btn btn-primary" id="wiz-next">
                Next <i class="bi bi-arrow-right"></i></button>` : ''}
        </div>`;
    }

    // ── EVENT LISTENERS ─────────────────────────────────

    function attachEventListeners() {
        // Navigation
        const prev = document.getElementById('wiz-prev');
        const next = document.getElementById('wiz-next');
        if (prev) prev.addEventListener('click', () => { saveCurrentStep(); state.step--; render(); });
        if (next) next.addEventListener('click', () => { saveCurrentStep(); state.step++; render(); });

        // Step indicators (jump to step)
        document.querySelectorAll('.wizard-step-indicator').forEach(el => {
            el.addEventListener('click', () => {
                const targetStep = parseInt(el.dataset.step);
                if (targetStep <= state.step) {
                    saveCurrentStep();
                    state.step = targetStep;
                    render();
                }
            });
        });

        // Step-specific
        switch (state.step) {
            case 1: attachStep1Listeners(); break;
            case 2: attachStep2Listeners(); break;
            case 3: attachStep3Listeners(); break;
            case 4: attachStep4Listeners(); break;
            case 5: attachStep5Listeners(); break;
            case 6: attachStep6Listeners(); break;
        }
    }

    function attachStep1Listeners() {
        // Icon picker
        const selected = document.getElementById('wiz-icon-selected');
        const dropdown = document.getElementById('wiz-icon-dropdown');
        if (selected && dropdown) {
            selected.addEventListener('click', () => {
                dropdown.style.display = dropdown.style.display === 'none' ? 'grid' : 'none';
            });
            dropdown.querySelectorAll('.icon-option').forEach(opt => {
                opt.addEventListener('click', () => {
                    state.icon = opt.dataset.icon;
                    render();
                });
            });
        }
    }

    function attachStep2Listeners() {
        // Auto-fill from name
        const dbInput = document.getElementById('wiz-database');
        const tableInput = document.getElementById('wiz-tableName');
        if (dbInput) dbInput.addEventListener('input', () => state.database = dbInput.value);
        if (tableInput) tableInput.addEventListener('input', () => state.tableName = tableInput.value);
    }

    function attachStep3Listeners() {
        // Add field
        document.getElementById('wiz-add-field')?.addEventListener('click', () => {
            state.fields.push({ key: '', type: 'text', label: '', required: false, sortable: true, searchable: false });
            render();
        });

        // Remove field
        document.querySelectorAll('.wiz-remove-field').forEach(btn => {
            btn.addEventListener('click', () => {
                state.fields.splice(parseInt(btn.dataset.index), 1);
                render();
            });
        });

        // Field inputs (live)
        document.querySelectorAll('.wiz-field-key').forEach(input => {
            input.addEventListener('input', () => {
                const i = parseInt(input.dataset.index);
                state.fields[i].key = input.value;
                // Auto-generate label
                if (!state.fields[i]._labelEdited) {
                    state.fields[i].label = input.value.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                    const labelInput = document.querySelector(`.wiz-field-label[data-index="${i}"]`);
                    if (labelInput) labelInput.value = state.fields[i].label;
                }
            });
        });

        document.querySelectorAll('.wiz-field-type').forEach(sel => {
            sel.addEventListener('change', () => {
                const i = parseInt(sel.dataset.index);
                const oldType = state.fields[i].type;
                state.fields[i].type = sel.value;
                // Re-render if switching to/from select (shows options editor)
                if ((sel.value === 'select' || sel.value === 'radio') !== (oldType === 'select' || oldType === 'radio')) {
                    if (sel.value === 'select' || sel.value === 'radio') {
                        state.fields[i].options = state.fields[i].options || [];
                    }
                    render();
                }
            });
        });

        document.querySelectorAll('.wiz-field-label').forEach(input => {
            input.addEventListener('input', () => {
                const i = parseInt(input.dataset.index);
                state.fields[i].label = input.value;
                state.fields[i]._labelEdited = true;
            });
        });

        document.querySelectorAll('.wiz-field-required').forEach(cb => {
            cb.addEventListener('change', () => {
                state.fields[parseInt(cb.dataset.index)].required = cb.checked;
            });
        });

        document.querySelectorAll('.wiz-field-sortable').forEach(cb => {
            cb.addEventListener('change', () => {
                state.fields[parseInt(cb.dataset.index)].sortable = cb.checked;
            });
        });

        document.querySelectorAll('.wiz-field-searchable').forEach(cb => {
            cb.addEventListener('change', () => {
                state.fields[parseInt(cb.dataset.index)].searchable = cb.checked;
            });
        });

        // Options management
        document.querySelectorAll('.wiz-add-option').forEach(btn => {
            btn.addEventListener('click', () => {
                const i = parseInt(btn.dataset.field);
                state.fields[i].options = state.fields[i].options || [];
                state.fields[i].options.push({ value: '', label: '' });
                render();
            });
        });

        document.querySelectorAll('.wiz-remove-option').forEach(btn => {
            btn.addEventListener('click', () => {
                const fi = parseInt(btn.dataset.field);
                const oi = parseInt(btn.dataset.opt);
                state.fields[fi].options.splice(oi, 1);
                render();
            });
        });

        document.querySelectorAll('.wiz-option-value').forEach(input => {
            input.addEventListener('input', () => {
                const fi = parseInt(input.dataset.field);
                const oi = parseInt(input.dataset.opt);
                state.fields[fi].options[oi].value = input.value;
            });
        });

        document.querySelectorAll('.wiz-option-label').forEach(input => {
            input.addEventListener('input', () => {
                const fi = parseInt(input.dataset.field);
                const oi = parseInt(input.dataset.opt);
                state.fields[fi].options[oi].label = input.value;
            });
        });
    }

    function attachStep4Listeners() {
        // Add page
        document.getElementById('wiz-add-page')?.addEventListener('click', () => {
            state.pages.push({
                id: 'page-' + (state.pages.length + 1),
                title: 'New Page',
                route: '/page-' + (state.pages.length + 1),
                components: [],
            });
            render();
        });

        // Remove page
        document.querySelectorAll('.wiz-remove-page').forEach(btn => {
            btn.addEventListener('click', () => {
                state.pages.splice(parseInt(btn.dataset.index), 1);
                render();
            });
        });

        // Page title/route
        document.querySelectorAll('.wiz-page-title').forEach(input => {
            input.addEventListener('input', () => {
                state.pages[parseInt(input.dataset.index)].title = input.value;
            });
        });
        document.querySelectorAll('.wiz-page-route').forEach(input => {
            input.addEventListener('input', () => {
                state.pages[parseInt(input.dataset.index)].route = input.value;
            });
        });

        // Add component to page
        document.querySelectorAll('.wiz-add-comp-select').forEach(sel => {
            sel.addEventListener('change', () => {
                if (sel.value) {
                    const pi = parseInt(sel.dataset.page);
                    state.pages[pi].components.push(sel.value);
                    render();
                }
            });
        });

        // Remove component from page
        document.querySelectorAll('.wiz-remove-comp').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const pi = parseInt(btn.dataset.page);
                const ci = parseInt(btn.dataset.comp);
                state.pages[pi].components.splice(ci, 1);
                render();
            });
        });
    }

    function attachStep5Listeners() {
        document.getElementById('wiz-defaultRole')?.addEventListener('change', function() {
            state.defaultRole = this.value;
        });
    }

    function attachStep6Listeners() {
        document.getElementById('wiz-generate')?.addEventListener('click', generatePackage);
        document.getElementById('wiz-download')?.addEventListener('click', downloadPackage);
        document.getElementById('wiz-copy')?.addEventListener('click', copyPackage);
    }

    // ── SAVE CURRENT STEP ───────────────────────────────

    function saveCurrentStep() {
        switch (state.step) {
            case 1:
                state.name = document.getElementById('wiz-name')?.value || state.name;
                state.category = document.getElementById('wiz-category')?.value || state.category;
                state.description = document.getElementById('wiz-description')?.value || state.description;
                state.author = document.getElementById('wiz-author')?.value || state.author;
                break;
            case 2:
                state.database = document.getElementById('wiz-database')?.value || state.database;
                state.tableName = document.getElementById('wiz-tableName')?.value || state.tableName;
                break;
        }
    }

    // ── GENERATE / DOWNLOAD / COPY ──────────────────────

    async function generatePackage() {
        saveCurrentStep();

        const btn = document.getElementById('wiz-generate');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                              document.querySelector('[name="csrf_token"]')?.value || '';

            const resp = await fetch('/api/package-schema.php?action=scaffold', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    name: state.name,
                    category: state.category,
                    description: state.description,
                    author: state.author,
                    icon: state.icon,
                    database: state.database,
                    tableName: state.tableName,
                    fields: state.fields.filter(f => f.key),
                    pages: state.pages,
                    defaultRole: state.defaultRole,
                }),
            });

            const data = await resp.json();

            if (data.package) {
                state.generatedJson = data.package;
                state.validation = data.validation;
            } else if (data.error) {
                state.validation = { valid: false, errors: [{ path: '$', message: data.error }], warnings: [], summary: data.error };
            }
        } catch (err) {
            state.validation = { valid: false, errors: [{ path: '$', message: 'Network error: ' + err.message }], warnings: [], summary: 'Generation failed' };
        }

        render();
    }

    function downloadPackage() {
        if (!state.generatedJson) return;
        const blob = new Blob([JSON.stringify(state.generatedJson, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'package.json';
        a.click();
        URL.revokeObjectURL(url);
    }

    function copyPackage() {
        if (!state.generatedJson) return;
        navigator.clipboard.writeText(JSON.stringify(state.generatedJson, null, 2)).then(() => {
            const btn = document.getElementById('wiz-copy');
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
            setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy to Clipboard'; }, 2000);
        });
    }

    // ── UTILITIES ───────────────────────────────────────

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function slugify(text) {
        return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }

    function iconClass(icon) {
        if (icon.startsWith('lucide-')) return 'icon-' + icon;
        if (icon.startsWith('bi-')) return 'bi ' + icon;
        return icon;
    }

    // ── INITIAL RENDER ──────────────────────────────────
    render();
});
