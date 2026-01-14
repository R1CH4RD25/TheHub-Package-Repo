<?php
/**
 * Form Builder Demo - No Auth Required
 * Test drag-drop form builder interface
 */

// Mock data for testing
$packages = [
    ['id' => 1, 'name' => 'Vehicle Maintenance'],
    ['id' => 2, 'name' => 'Room Reservations'],
    ['id' => 3, 'name' => 'IT Support Tickets']
];

// Mock existing forms
$existingForms = [
    1 => [ // Vehicle Maintenance package
        ['id' => 1, 'name' => 'Work Order Request', 'field_count' => 5],
        ['id' => 2, 'name' => 'Emergency Repair', 'field_count' => 3]
    ],
    2 => [ // Room Reservations
        ['id' => 3, 'name' => 'Room Booking Form', 'field_count' => 8]
    ]
];

$pageTitle = 'Form Builder Demo';
define('CSP_NONCE', bin2hex(random_bytes(16)));
define('CACHE_VERSION', '1.0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - The Hub</title>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Notyf -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
    
    <!-- SortableJS for drag-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <style nonce="<?php echo CSP_NONCE; ?>">
        :root {
            --space-1: 4px; --space-2: 8px; --space-3: 12px; --space-4: 16px;
            --space-6: 24px; --space-8: 32px; --space-16: 64px;
            --gray-50: #FFFFFF; --gray-100: #FAF9F8; --gray-200: #F3F2F1;
            --gray-300: #EDEBE9; --gray-400: #C8C6C4; --gray-500: #A19F9D;
            --gray-600: #8A8886; --gray-700: #605E5C; --gray-800: #323130; --gray-900: #1A1A1A;
            --ms-blue: #0078D4; --ms-blue-hover: #106EBE; --ms-blue-light: #E3F2FD;
            --error: #D13438; --radius-sm: 2px; --radius-base: 4px; 
            --radius-md: 6px; --radius-lg: 8px;
            --text-xs: 12px; --text-sm: 13px; --text-md: 15px;
            --font-medium: 500; --font-semibold: 600;
            --transition-fast: 150ms cubic-bezier(0.4, 0.0, 0.2, 1);
            --elevation-1: 0 1px 2px rgba(0, 0, 0, 0.05);
            --elevation-2: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--gray-100);
            padding: var(--space-4);
        }

        .demo-header {
            background: white;
            padding: var(--space-4);
            margin-bottom: var(--space-4);
            border-radius: var(--radius-lg);
            box-shadow: var(--elevation-1);
        }

        .demo-header h1 {
            font-size: 24px;
            color: var(--gray-900);
            margin-bottom: var(--space-2);
        }

        .demo-header p {
            color: var(--gray-600);
            font-size: var(--text-sm);
        }

        .form-builder-container {
            display: grid;
            grid-template-columns: 280px 1fr 380px;
            gap: var(--space-4);
            min-height: 600px;
        }

        .field-palette {
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            overflow-y: auto;
            max-height: 800px;
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

        .field-item:active { cursor: grabbing; }

        .field-item i {
            font-size: 18px;
            color: var(--ms-blue);
        }

        .field-item-label {
            font-size: var(--text-sm);
            color: var(--gray-800);
        }

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

        .field-required { color: var(--error); }

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

        .properties-panel {
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            overflow-y: auto;
            max-height: 800px;
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

        .property-group label.inline-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-weight: var(--font-medium);
        }

        .property-group label.inline-checkbox input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .property-group label.inline-checkbox span {
            white-space: nowrap;
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
<body>
    <div class="demo-header">
        <h1>🎨 Form Builder Demo</h1>
        <p>Test the drag-drop form builder interface. No authentication required for testing.</p>
    </div>

    <!-- Toolbar -->
    <div class="form-toolbar">
        <div class="toolbar-left">
            <div class="property-group" style="margin: 0; min-width: 250px;">
                <label for="packageSelect">Package:</label>
                <select id="packageSelect" onchange="loadPackageForms()" required>
                    <option value="">Select a package...</option>
                    <?php foreach ($packages as $pkg): ?>
                        <option value="<?= $pkg['id'] ?>"><?= htmlspecialchars($pkg['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="property-group" id="existingFormGroup" style="margin: 0; min-width: 200px; display: none;">
                <label for="existingFormSelect">Load Existing Form:</label>
                <select id="existingFormSelect" onchange="loadExistingForm()">
                    <option value="">-- Create New --</option>
                </select>
            </div>
            
            <div class="property-group" id="formNameGroup" style="margin: 0; min-width: 200px;">
                <label for="formName">Form Name:</label>
                <input type="text" id="formName" placeholder="e.g., Maintenance Request" required>
            </div>
        </div>
        <div>
            <button class="btn btn-secondary" onclick="resetForm()" id="resetBtn" style="display: none;">
                <i class="bi bi-x-circle"></i> Cancel
            </button>
            <button class="btn btn-secondary" onclick="viewJSON()">
                <i class="bi bi-code"></i> View JSON
            </button>
            <button class="btn btn-primary" onclick="saveForm()" id="saveBtn">
                <i class="bi bi-save"></i> Save (Demo)
            </button>
        </div>
    </div>

    <!-- Form Builder -->
    <div class="form-builder-container">
        <!-- Field Palette -->
        <div class="field-palette">
            <div class="palette-section">
                <h3>Basic Fields</h3>
                <div class="field-item" data-field-type="text" draggable="true">
                    <i class="bi bi-input-cursor-text"></i>
                    <span class="field-item-label">Text Input</span>
                </div>
                <div class="field-item" data-field-type="textarea" draggable="true">
                    <i class="bi bi-text-paragraph"></i>
                    <span class="field-item-label">Text Area</span>
                </div>
                <div class="field-item" data-field-type="number" draggable="true">
                    <i class="bi bi-123"></i>
                    <span class="field-item-label">Number</span>
                </div>
                <div class="field-item" data-field-type="email" draggable="true">
                    <i class="bi bi-envelope"></i>
                    <span class="field-item-label">Email</span>
                </div>
                <div class="field-item" data-field-type="tel" draggable="true">
                    <i class="bi bi-telephone"></i>
                    <span class="field-item-label">Phone</span>
                </div>
            </div>

            <div class="palette-section">
                <h3>Choice Fields</h3>
                <div class="field-item" data-field-type="dropdown" draggable="true">
                    <i class="bi bi-list-ul"></i>
                    <span class="field-item-label">Dropdown</span>
                </div>
                <div class="field-item" data-field-type="radio" draggable="true">
                    <i class="bi bi-ui-radios"></i>
                    <span class="field-item-label">Radio Buttons</span>
                </div>
                <div class="field-item" data-field-type="checkbox" draggable="true">
                    <i class="bi bi-check2-square"></i>
                    <span class="field-item-label">Checkboxes</span>
                </div>
            </div>

            <div class="palette-section">
                <h3>Date & Time</h3>
                <div class="field-item" data-field-type="date" draggable="true">
                    <i class="bi bi-calendar-date"></i>
                    <span class="field-item-label">Date</span>
                </div>
                <div class="field-item" data-field-type="datetime" draggable="true">
                    <i class="bi bi-calendar-event"></i>
                    <span class="field-item-label">Date & Time</span>
                </div>
                <div class="field-item" data-field-type="time" draggable="true">
                    <i class="bi bi-clock"></i>
                    <span class="field-item-label">Time</span>
                </div>
            </div>

            <div class="palette-section">
                <h3>Advanced</h3>
                <div class="field-item" data-field-type="file" draggable="true">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                    <span class="field-item-label">File Upload</span>
                </div>
                <div class="field-item" data-field-type="user_select" draggable="true">
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script nonce="<?php echo CSP_NONCE; ?>">
        const notyf = new Notyf({duration: 3000, position: {x: 'right', y: 'top'}});
        
        // Mock form database
        const mockForms = <?php echo json_encode($existingForms); ?>;
        const mockFormData = {
            1: { // Work Order Request
                id: 1,
                name: 'Work Order Request',
                fields: [
                    {id: 1, field_key: 'priority', field_type: 'dropdown', label: 'Priority Level', 
                     placeholder: '', help_text: 'Select urgency', is_required: true,
                     options: [{value: 'low', label: 'Low'}, {value: 'medium', label: 'Medium'}, {value: 'high', label: 'High'}]},
                    {id: 2, field_key: 'description', field_type: 'textarea', label: 'Issue Description', 
                     placeholder: 'Describe the problem...', help_text: '', is_required: true, options: []},
                    {id: 3, field_key: 'location', field_type: 'text', label: 'Location', 
                     placeholder: 'Building/Room', help_text: '', is_required: true, options: []},
                    {id: 4, field_key: 'preferred_date', field_type: 'date', label: 'Preferred Service Date', 
                     placeholder: '', help_text: '', is_required: false, options: []},
                    {id: 5, field_key: 'attachments', field_type: 'file', label: 'Photos/Documents', 
                     placeholder: '', help_text: 'Optional photos of issue', is_required: false, options: []}
                ]
            },
            2: { // Emergency Repair
                id: 2,
                name: 'Emergency Repair',
                fields: [
                    {id: 6, field_key: 'contact_phone', field_type: 'tel', label: 'Contact Phone', 
                     placeholder: '(555) 123-4567', help_text: '', is_required: true, options: []},
                    {id: 7, field_key: 'issue', field_type: 'textarea', label: 'Emergency Description', 
                     placeholder: 'Describe the emergency...', help_text: '', is_required: true, options: []},
                    {id: 8, field_key: 'notify', field_type: 'checkbox', label: 'Notify', 
                     placeholder: '', help_text: 'Who to alert', is_required: false,
                     options: [{value: 'facilities', label: 'Facilities Manager'}, {value: 'principal', label: 'Principal'}]}
                ]
            },
            3: { // Room Booking Form
                id: 3,
                name: 'Room Booking Form',
                fields: [
                    {id: 9, field_key: 'room', field_type: 'dropdown', label: 'Room Selection', 
                     placeholder: '', help_text: '', is_required: true,
                     options: [{value: 'conf_a', label: 'Conference Room A'}, {value: 'conf_b', label: 'Conference Room B'}]},
                    {id: 10, field_key: 'start_date', field_type: 'datetime', label: 'Start Date/Time', 
                     placeholder: '', help_text: '', is_required: true, options: []},
                    {id: 11, field_key: 'end_date', field_type: 'datetime', label: 'End Date/Time', 
                     placeholder: '', help_text: '', is_required: true, options: []},
                    {id: 12, field_key: 'purpose', field_type: 'text', label: 'Purpose', 
                     placeholder: 'Meeting purpose...', help_text: '', is_required: true, options: []},
                    {id: 13, field_key: 'attendees', field_type: 'number', label: 'Number of Attendees', 
                     placeholder: '10', help_text: '', is_required: true, options: []},
                    {id: 14, field_key: 'setup', field_type: 'radio', label: 'Room Setup', 
                     placeholder: '', help_text: '', is_required: true,
                     options: [{value: 'theater', label: 'Theater'}, {value: 'classroom', label: 'Classroom'}, {value: 'uShape', label: 'U-Shape'}]},
                    {id: 15, field_key: 'av_needs', field_type: 'checkbox', label: 'A/V Equipment', 
                     placeholder: '', help_text: '', is_required: false,
                     options: [{value: 'projector', label: 'Projector'}, {value: 'mic', label: 'Microphone'}, {value: 'webcam', label: 'Webcam'}]},
                    {id: 16, field_key: 'notes', field_type: 'textarea', label: 'Special Instructions', 
                     placeholder: 'Any special requests...', help_text: '', is_required: false, options: []}
                ]
            }
        };
        
        let formFields = [];
        let selectedFieldIndex = null;
        let fieldCounter = 0;
        let editingFormId = null;
        let currentPackageId = null;

        const palette = document.querySelector('.field-palette');
        const canvas = document.getElementById('formCanvas');

        // Load package forms when package selected
        function loadPackageForms() {
            const packageId = document.getElementById('packageSelect').value;
            currentPackageId = packageId;
            const existingFormGroup = document.getElementById('existingFormGroup');
            const existingFormSelect = document.getElementById('existingFormSelect');
            
            if (packageId && mockForms[packageId]) {
                // Show existing forms dropdown
                existingFormGroup.style.display = 'block';
                existingFormSelect.innerHTML = '<option value="">-- Create New Form --</option>';
                mockForms[packageId].forEach(form => {
                    existingFormSelect.innerHTML += `<option value="${form.id}">${form.name} (${form.field_count} fields)</option>`;
                });
            } else {
                existingFormGroup.style.display = 'none';
                existingFormSelect.innerHTML = '<option value="">-- Create New --</option>';
            }
            
            // Reset form
            resetForm();
        }

        function loadExistingForm() {
            const formId = document.getElementById('existingFormSelect').value;
            
            if (!formId) {
                resetForm();
                return;
            }
            
            const formData = mockFormData[formId];
            if (formData) {
                editingFormId = formId;
                document.getElementById('formName').value = formData.name;
                
                // Load fields
                formFields = formData.fields.map(field => ({...field}));
                fieldCounter = Math.max(...formFields.map(f => f.id || 0), 0);
                
                renderCanvas();
                document.getElementById('resetBtn').style.display = 'inline-block';
                document.getElementById('saveBtn').innerHTML = '<i class="bi bi-save"></i> Update Form';
                
                notyf.success(`Loaded: ${formData.name}`);
            }
        }

        function resetForm() {
            editingFormId = null;
            formFields = [];
            selectedFieldIndex = null;
            document.getElementById('formName').value = '';
            document.getElementById('existingFormSelect').value = '';
            document.getElementById('resetBtn').style.display = 'none';
            document.getElementById('saveBtn').innerHTML = '<i class="bi bi-save"></i> Save (Demo)';
            renderCanvas();
            document.getElementById('propertiesPanel').innerHTML = `
                <h3>Field Properties</h3>
                <p style="color: var(--gray-500); font-size: var(--text-sm);">
                    Select a field to edit its properties
                </p>
            `;
        }

        // Make canvas sortable
        const sortable = new Sortable(canvas, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
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
                options: ['dropdown', 'radio', 'checkbox'].includes(fieldType) ? 
                    [{value: 'option1', label: 'Option 1'}] : []
            };
            
            formFields.push(field);
            renderCanvas();
            selectField(formFields.length - 1);
            notyf.success('Field added!');
        }

        function getFieldLabel(fieldType) {
            const labels = {
                text: 'Text Input', textarea: 'Text Area', number: 'Number',
                email: 'Email Address', tel: 'Phone Number', dropdown: 'Dropdown',
                radio: 'Radio Selection', checkbox: 'Checkboxes', date: 'Date',
                datetime: 'Date & Time', time: 'Time', file: 'File Upload',
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
                    <label class="inline-checkbox">
                        <input type="checkbox" ${field.is_required ? 'checked' : ''} onchange="updateField('is_required', this.checked)">
                        <span>Required Field</span>
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
                notyf.success('Field deleted');
            }
        }

        function viewJSON() {
            console.log('Form Structure:', {
                package_id: document.getElementById('packageSelect').value,
                name: document.getElementById('formName').value,
                fields: formFields
            });
            alert('Check browser console (F12) for JSON output!');
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

            const saveData = {
                mode: editingFormId ? 'update' : 'create',
                form_id: editingFormId,
                package_id: packageId,
                name: formName,
                fields: formFields
            };

            console.log(editingFormId ? '📝 Updating form:' : '✨ Creating new form:', saveData);

            notyf.success(editingFormId ? 
                '✅ Form updated! (check console)' : 
                '✅ New form created! (check console)'
            );
        }
    </script>
</body>
</html>
