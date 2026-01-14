<?php
/**
 * Package Form Builder
 * Drag-drop visual form designer with live preview
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;

// Require super admin
Auth::requireRole(['super_admin']);

$user = Auth::user();
$pageTitle = 'Form Builder';

// Get package list for form creation
$db = Database::getInstance();
$stmt = $db->query("
    SELECT id, name, icon 
    FROM sections 
    WHERE is_active = 1 
    ORDER BY name
");
$packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="<?php echo CSP_HEADER; ?>">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(\Hub\SiteSettings::get('site_name', 'The Hub')) ?></title>

    <!-- CSS Bundles -->
    <link rel="stylesheet" href="/assets/css/admin-bundle.css?v=<?= CACHE_VERSION ?>">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Notyf -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    
    <!-- SortableJS for drag-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <style nonce="<?php echo CSP_NONCE; ?>">
        .form-builder-container {
            display: grid;
            grid-template-columns: 280px 1fr 380px;
            gap: var(--space-6);
            height: calc(100vh - 200px);
        }

        /* Field Palette */
        .field-palette {
            background: var(--gray-50);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            overflow-y: auto;
        }

        .palette-section {
            margin-bottom: var(--space-4);
        }

        .palette-section h3 {
            font-size: var(--text-sm);
            font-weight: var(--font-semibold);
            color: var(--gray-700);
            margin-bottom: var(--space-2);
            text-transform: uppercase;
        }

        .field-item {
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            padding: var(--space-3);
            margin-bottom: var(--space-2);
            cursor: grab;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .field-item:hover {
            border-color: var(--ms-blue);
            box-shadow: var(--elevation-2);
        }

        .field-item:active {
            cursor: grabbing;
        }

        .field-item i {
            font-size: 18px;
            color: var(--ms-blue);
        }

        .field-item-label {
            font-size: var(--text-sm);
            color: var(--gray-800);
        }

        /* Form Canvas */
        .form-canvas {
            background: white;
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            min-height: 500px;
            overflow-y: auto;
        }

        .form-canvas.drag-over {
            background: var(--ms-blue-light);
            border-color: var(--ms-blue);
        }

        .canvas-empty {
            text-align: center;
            padding: var(--space-16);
            color: var(--gray-500);
        }

        .canvas-empty i {
            font-size: 64px;
            margin-bottom: var(--space-4);
            opacity: 0.3;
        }

        /* Form Field in Canvas */
        .canvas-field {
            background: var(--gray-50);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
            cursor: move;
            position: relative;
        }

        .canvas-field:hover {
            border-color: var(--ms-blue);
            box-shadow: var(--elevation-1);
        }

        .canvas-field.selected {
            border-color: var(--ms-blue);
            box-shadow: 0 0 0 3px var(--ms-blue-light);
        }

        .field-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-2);
        }

        .field-label {
            font-weight: var(--font-medium);
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .field-required {
            color: var(--error);
        }

        .field-actions {
            display: flex;
            gap: var(--space-1);
        }

        .field-action-btn {
            background: transparent;
            border: none;
            padding: var(--space-1);
            cursor: pointer;
            color: var(--gray-600);
            border-radius: var(--radius-sm);
        }

        .field-action-btn:hover {
            background: var(--gray-200);
            color: var(--gray-900);
        }

        .field-preview {
            margin-top: var(--space-3);
        }

        .field-preview input,
        .field-preview select,
        .field-preview textarea {
            width: 100%;
            padding: var(--space-2);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            font-size: var(--text-sm);
        }

        /* Properties Panel */
        .properties-panel {
            background: var(--gray-50);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            overflow-y: auto;
        }

        .properties-panel h3 {
            font-size: var(--text-md);
            font-weight: var(--font-semibold);
            margin-bottom: var(--space-4);
        }

        .property-group {
            margin-bottom: var(--space-4);
        }

        .property-group label {
            display: block;
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--gray-700);
            margin-bottom: var(--space-1);
        }

        .property-group input,
        .property-group select,
        .property-group textarea {
            width: 100%;
            padding: var(--space-2);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            font-size: var(--text-sm);
        }

        .property-group textarea {
            resize: vertical;
            min-height: 60px;
        }

        .form-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-4);
            padding: var(--space-4);
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }

        .toolbar-left {
            display: flex;
            gap: var(--space-3);
            align-items: center;
        }

        .btn {
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-base);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            cursor: pointer;
            transition: all var(--transition-fast);
            border: none;
        }

        .btn-primary {
            background: var(--ms-blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--ms-blue-hover);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }
    </style>
</head>
<body class="admin-root">
    <div class="admin-shell">
        <?php
        require_once __DIR__ . '/../../src/Components/EnterpriseHeader.php';
        \Hub\Components\EnterpriseHeader::render($user, 'admin', [
            'title' => $pageTitle,
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => '/admin/'],
                ['label' => 'Form Builder']
            ]
        ]);
        ?>

        <main class="admin-main">
            <!-- Toolbar -->
            <div class="form-toolbar">
                <div class="toolbar-left">
                    <div class="property-group" style="margin: 0; min-width: 250px;">
                        <label for="packageSelect">Package:</label>
                        <select id="packageSelect" required>
                            <option value="">Select a package...</option>
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="property-group" style="margin: 0; min-width: 200px;">
                        <label for="formName">Form Name:</label>
                        <input type="text" id="formName" placeholder="e.g., Maintenance Request" required>
                    </div>
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="previewForm()">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                    <button class="btn btn-primary" onclick="saveForm()">
                        <i class="bi bi-save"></i> Save Form
                    </button>
                </div>
            </div>

            <!-- Form Builder -->
            <div class="form-builder-container">
                <!-- Field Palette -->
                <div class="field-palette">
                    <div class="palette-section">
                        <h3>Basic Fields</h3>
                        <div class="field-item" data-field-type="text">
                            <i class="bi bi-input-cursor-text"></i>
                            <span class="field-item-label">Text Input</span>
                        </div>
                        <div class="field-item" data-field-type="textarea">
                            <i class="bi bi-text-paragraph"></i>
                            <span class="field-item-label">Text Area</span>
                        </div>
                        <div class="field-item" data-field-type="number">
                            <i class="bi bi-123"></i>
                            <span class="field-item-label">Number</span>
                        </div>
                        <div class="field-item" data-field-type="email">
                            <i class="bi bi-envelope"></i>
                            <span class="field-item-label">Email</span>
                        </div>
                        <div class="field-item" data-field-type="tel">
                            <i class="bi bi-telephone"></i>
                            <span class="field-item-label">Phone</span>
                        </div>
                    </div>

                    <div class="palette-section">
                        <h3>Choice Fields</h3>
                        <div class="field-item" data-field-type="dropdown">
                            <i class="bi bi-list-ul"></i>
                            <span class="field-item-label">Dropdown</span>
                        </div>
                        <div class="field-item" data-field-type="radio">
                            <i class="bi bi-ui-radios"></i>
                            <span class="field-item-label">Radio Buttons</span>
                        </div>
                        <div class="field-item" data-field-type="checkbox">
                            <i class="bi bi-check2-square"></i>
                            <span class="field-item-label">Checkboxes</span>
                        </div>
                    </div>

                    <div class="palette-section">
                        <h3>Date & Time</h3>
                        <div class="field-item" data-field-type="date">
                            <i class="bi bi-calendar-date"></i>
                            <span class="field-item-label">Date</span>
                        </div>
                        <div class="field-item" data-field-type="datetime">
                            <i class="bi bi-calendar-event"></i>
                            <span class="field-item-label">Date & Time</span>
                        </div>
                        <div class="field-item" data-field-type="time">
                            <i class="bi bi-clock"></i>
                            <span class="field-item-label">Time</span>
                        </div>
                    </div>

                    <div class="palette-section">
                        <h3>Advanced</h3>
                        <div class="field-item" data-field-type="file">
                            <i class="bi bi-file-earmark-arrow-up"></i>
                            <span class="field-item-label">File Upload</span>
                        </div>
                        <div class="field-item" data-field-type="user_select">
                            <i class="bi bi-person-check"></i>
                            <span class="field-item-label">User Selector</span>
                        </div>
                    </div>
                </div>

                <!-- Form Canvas -->
                <div class="form-canvas" id="formCanvas">
                    <div class="canvas-empty">
                        <i class="bi bi-file-earmark-plus"></i>
                        <h3>Drag fields here to build your form</h3>
                        <p>Choose from the field palette on the left</p>
                    </div>
                </div>

                <!-- Properties Panel -->
                <div class="properties-panel" id="propertiesPanel">
                    <h3>Field Properties</h3>
                    <p style="color: var(--gray-500); font-size: var(--text-sm);">
                        Select a field to edit its properties
                    </p>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
        const notyf = new Notyf({duration: 3000, position: {x: 'right', y: 'top'}});
        
        let formFields = [];
        let selectedFieldIndex = null;
        let fieldCounter = 0;

        // Initialize drag-drop
        const palette = document.querySelector('.field-palette');
        const canvas = document.getElementById('formCanvas');

        // Make canvas sortable
        const sortable = new Sortable(canvas, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                // Reorder formFields array
                const movedField = formFields.splice(evt.oldIndex, 1)[0];
                formFields.splice(evt.newIndex, 0, movedField);
                renderCanvas();
            }
        });

        // Drag from palette to canvas
        palette.addEventListener('dragstart', (e) => {
            if (e.target.classList.contains('field-item')) {
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('fieldType', e.target.dataset.fieldType);
            }
        });

        canvas.addEventListener('dragover', (e) => {
            e.preventDefault();
            canvas.classList.add('drag-over');
        });

        canvas.addEventListener('dragleave', () => {
            canvas.classList.remove('drag-over');
        });

        canvas.addEventListener('drop', (e) => {
            e.preventDefault();
            canvas.classList.remove('drag-over');
            
            const fieldType = e.dataTransfer.getData('fieldType');
            if (fieldType) {
                addField(fieldType);
            }
        });

        function addField(fieldType) {
            const field = {
                id: ++fieldCounter,
                field_key: `field_${fieldCounter}`,
                field_type: fieldType,
                label: getFieldLabel(fieldType),
                placeholder: '',
                help_text: '',
                is_required: false,
                options: fieldType === 'dropdown' || fieldType === 'radio' || fieldType === 'checkbox' ? 
                    [{value: 'option1', label: 'Option 1'}] : []
            };
            
            formFields.push(field);
            renderCanvas();
            selectField(formFields.length - 1);
        }

        function getFieldLabel(fieldType) {
            const labels = {
                text: 'Text Input',
                textarea: 'Text Area',
                number: 'Number',
                email: 'Email Address',
                tel: 'Phone Number',
                dropdown: 'Dropdown',
                radio: 'Radio Selection',
                checkbox: 'Checkboxes',
                date: 'Date',
                datetime: 'Date & Time',
                time: 'Time',
                file: 'File Upload',
                user_select: 'User Selection'
            };
            return labels[fieldType] || 'Field';
        }

        function renderCanvas() {
            if (formFields.length === 0) {
                canvas.innerHTML = `
                    <div class="canvas-empty">
                        <i class="bi bi-file-earmark-plus"></i>
                        <h3>Drag fields here to build your form</h3>
                        <p>Choose from the field palette on the left</p>
                    </div>
                `;
                return;
            }

            canvas.innerHTML = formFields.map((field, index) => `
                <div class="canvas-field ${selectedFieldIndex === index ? 'selected' : ''}" 
                     data-index="${index}" onclick="selectField(${index})">
                    <div class="field-header">
                        <div class="field-label">
                            <i class="bi bi-grip-vertical"></i>
                            ${field.label}
                            ${field.is_required ? '<span class="field-required">*</span>' : ''}
                        </div>
                        <div class="field-actions">
                            <button class="field-action-btn" onclick="deleteField(${index}, event)" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    ${field.help_text ? `<div style="font-size: var(--text-xs); color: var(--gray-600); margin-bottom: var(--space-2);">${field.help_text}</div>` : ''}
                    <div class="field-preview">
                        ${renderFieldPreview(field)}
                    </div>
                </div>
            `).join('');
        }

        function renderFieldPreview(field) {
            switch(field.field_type) {
                case 'textarea':
                    return `<textarea placeholder="${field.placeholder}" disabled></textarea>`;
                case 'dropdown':
                    return `<select disabled>
                        <option>${field.placeholder || 'Select an option'}</option>
                        ${field.options.map(opt => `<option>${opt.label}</option>`).join('')}
                    </select>`;
                case 'radio':
                case 'checkbox':
                    return field.options.map(opt => `
                        <label style="display: block; margin-bottom: var(--space-1);">
                            <input type="${field.field_type}" disabled> ${opt.label}
                        </label>
                    `).join('');
                default:
                    return `<input type="${field.field_type}" placeholder="${field.placeholder}" disabled>`;
            }
        }

        function selectField(index) {
            selectedFieldIndex = index;
            renderCanvas();
            renderProperties(formFields[index]);
        }

        function renderProperties(field) {
            const panel = document.getElementById('propertiesPanel');
            const hasOptions = ['dropdown', 'radio', 'checkbox'].includes(field.field_type);
            
            panel.innerHTML = `
                <h3>Field Properties</h3>
                
                <div class="property-group">
                    <label>Field Label</label>
                    <input type="text" value="${field.label}" onchange="updateField('label', this.value)">
                </div>
                
                <div class="property-group">
                    <label>Field Key (Internal)</label>
                    <input type="text" value="${field.field_key}" onchange="updateField('field_key', this.value)">
                </div>
                
                <div class="property-group">
                    <label>Placeholder Text</label>
                    <input type="text" value="${field.placeholder}" onchange="updateField('placeholder', this.value)">
                </div>
                
                <div class="property-group">
                    <label>Help Text</label>
                    <textarea onchange="updateField('help_text', this.value)">${field.help_text}</textarea>
                </div>
                
                <div class="property-group">
                    <label>
                        <input type="checkbox" ${field.is_required ? 'checked' : ''} onchange="updateField('is_required', this.checked)">
                        Required Field
                    </label>
                </div>
                
                ${hasOptions ? `
                    <div class="property-group">
                        <label>Options</label>
                        <div id="optionsEditor">
                            ${field.options.map((opt, i) => `
                                <div style="display: flex; gap: var(--space-2); margin-bottom: var(--space-2);">
                                    <input type="text" value="${opt.label}" onchange="updateOption(${i}, 'label', this.value)" style="flex: 1;">
                                    <button class="field-action-btn" onclick="removeOption(${i})"><i class="bi bi-x"></i></button>
                                </div>
                            `).join('')}
                        </div>
                        <button class="btn btn-secondary" onclick="addOption()" style="width: 100%; margin-top: var(--space-2);">
                            <i class="bi bi-plus"></i> Add Option
                        </button>
                    </div>
                ` : ''}
            `;
        }

        function updateField(key, value) {
            if (selectedFieldIndex !== null) {
                formFields[selectedFieldIndex][key] = value;
                renderCanvas();
            }
        }

        function updateOption(index, key, value) {
            if (selectedFieldIndex !== null) {
                formFields[selectedFieldIndex].options[index][key] = value;
                formFields[selectedFieldIndex].options[index].value = value.toLowerCase().replace(/\s+/g, '_');
                renderCanvas();
            }
        }

        function addOption() {
            if (selectedFieldIndex !== null) {
                const optNum = formFields[selectedFieldIndex].options.length + 1;
                formFields[selectedFieldIndex].options.push({
                    value: `option${optNum}`,
                    label: `Option ${optNum}`
                });
                renderProperties(formFields[selectedFieldIndex]);
                renderCanvas();
            }
        }

        function removeOption(index) {
            if (selectedFieldIndex !== null && formFields[selectedFieldIndex].options.length > 1) {
                formFields[selectedFieldIndex].options.splice(index, 1);
                renderProperties(formFields[selectedFieldIndex]);
                renderCanvas();
            }
        }

        function deleteField(index, event) {
            event.stopPropagation();
            if (confirm('Delete this field?')) {
                formFields.splice(index, 1);
                selectedFieldIndex = null;
                renderCanvas();
                document.getElementById('propertiesPanel').innerHTML = `
                    <h3>Field Properties</h3>
                    <p style="color: var(--gray-500); font-size: var(--text-sm);">
                        Select a field to edit its properties
                    </p>
                `;
            }
        }

        function previewForm() {
            // TODO: Open modal with live form preview
            notyf.success('Preview feature coming soon!');
        }

        function saveForm() {
            const packageId = document.getElementById('packageSelect').value;
            const formName = document.getElementById('formName').value;

            if (!packageId || !formName) {
                notyf.error('Please select a package and enter a form name');
                return;
            }

            if (formFields.length === 0) {
                notyf.error('Add at least one field to the form');
                return;
            }

            // TODO: Send to API
            console.log('Saving form:', {
                package_id: packageId,
                name: formName,
                fields: formFields
            });

            notyf.success('Form saved! (API endpoint coming next)');
        }
    </script>
</body>
</html>
