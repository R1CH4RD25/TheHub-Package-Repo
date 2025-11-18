<?php

namespace Hub;

/**
 * Dynamic Form Renderer
 *
 * Generates HTML forms dynamically from section_field_definitions.
 * Supports all field types: text, textarea, date, time, select, multi_select,
 * radio, checkbox, email, user_select, file upload.
 *
 * PWA-ready with proper validation, accessibility, and mobile responsiveness.
 */
class DynamicFormRenderer
{
    private Database $db;
    private array $section;
    private array $fields = [];
    private array $data = [];
    private array $errors = [];

    public function __construct(array $section, array $data = [], array $errors = [])
    {
        $this->db = Database::getInstance();
        $this->section = $section;
        $this->data = $data;
        $this->errors = $errors;
        $this->loadFields();
    }

    /**
     * Load field definitions from database
     */
    private function loadFields(): void
    {
        $this->fields = $this->db->fetchAll(
            "SELECT * FROM section_field_definitions
             WHERE section_id = ?
             ORDER BY sort_order ASC, id ASC",
            [$this->section['id']]
        );
    }

    /**
     * Render complete form
     */
    public function render(): string
    {
        if (empty($this->fields)) {
            return '<div class="alert alert-warning">No fields defined for this section.</div>';
        }

        $html = '<form id="dynamicSectionForm" class="dynamic-form" method="POST" enctype="multipart/form-data">';
        $html .= $this->renderCsrfToken();
        $html .= '<input type="hidden" name="section_id" value="' . (int)$this->section['id'] . '">';

        foreach ($this->fields as $field) {
            // Skip admin-only fields for regular users
            if ($this->isAdminField($field) && !$this->isStaffUser()) {
                continue;
            }

            $html .= $this->renderField($field);
        }

        $html .= $this->renderSubmitButton();
        $html .= '</form>';
        $html .= $this->renderJavaScript();

        return $html;
    }

    /**
     * Render individual field
     */
    private function renderField(array $field): string
    {
        $type = $field['field_type'];
        $name = $field['field_name'];
        $label = $field['field_label'];
        $required = (bool)$field['is_required'];
        $value = $this->data[$name] ?? ($field['default_value'] ?? '');
        $error = $this->errors[$name] ?? null;

        $html = '<div class="form-group mb-4 field-type-' . e($type) . '">';

        // Label
        $html .= '<label for="' . e($name) . '" class="form-label fw-semibold">';
        $html .= e($label);
        if ($required) {
            $html .= ' <span class="text-danger">*</span>';
        }
        $html .= '</label>';

        // Help text
        if (!empty($field['help_text'])) {
            $html .= '<div class="form-text text-muted mb-2">' . e($field['help_text']) . '</div>';
        }

        // Field input
        $html .= $this->renderFieldInput($field, $value);

        // Validation error
        if ($error) {
            $html .= '<div class="invalid-feedback d-block">' . e($error) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render field input based on type
     */
    private function renderFieldInput(array $field, $value): string
    {
        $type = $field['field_type'];
        $name = $field['field_name'];
        $required = (bool)$field['is_required'];
        $config = $field['field_config'] ? json_decode($field['field_config'], true) : [];
        $validation = $field['validation_rules'] ? json_decode($field['validation_rules'], true) : [];

        $attributes = [
            'id' => $name,
            'name' => $name,
            'class' => 'form-control',
        ];

        if ($required) {
            $attributes['required'] = 'required';
        }

        switch ($type) {
            case 'text':
                $attributes['type'] = 'text';
                $attributes['value'] = $value;
                if (!empty($field['placeholder'])) {
                    $attributes['placeholder'] = $field['placeholder'];
                }
                if (isset($validation['min_length'])) {
                    $attributes['minlength'] = $validation['min_length'];
                }
                if (isset($validation['max_length'])) {
                    $attributes['maxlength'] = $validation['max_length'];
                }
                return '<input ' . $this->buildAttributes($attributes) . '>';

            case 'email':
                $attributes['type'] = 'email';
                $attributes['value'] = $value;
                if (!empty($field['placeholder'])) {
                    $attributes['placeholder'] = $field['placeholder'];
                }
                return '<input ' . $this->buildAttributes($attributes) . '>';

            case 'date':
                $attributes['type'] = 'date';
                $attributes['value'] = $value;
                if (isset($validation['min_date'])) {
                    if ($validation['min_date'] === 'today') {
                        $attributes['min'] = date('Y-m-d');
                    } else {
                        $attributes['min'] = $validation['min_date'];
                    }
                }
                if (isset($validation['max_date'])) {
                    if ($validation['max_date'] === 'today') {
                        $attributes['max'] = date('Y-m-d');
                    } else {
                        $attributes['max'] = $validation['max_date'];
                    }
                }
                return '<input ' . $this->buildAttributes($attributes) . '>';

            case 'time':
                $attributes['type'] = 'time';
                $attributes['value'] = $value;
                return '<input ' . $this->buildAttributes($attributes) . '>';

            case 'textarea':
                unset($attributes['type']);
                $attributes['rows'] = $config['rows'] ?? 4;
                if (!empty($field['placeholder'])) {
                    $attributes['placeholder'] = $field['placeholder'];
                }
                if (isset($validation['min_length'])) {
                    $attributes['minlength'] = $validation['min_length'];
                }
                if (isset($validation['max_length'])) {
                    $attributes['maxlength'] = $validation['max_length'];
                }
                return '<textarea ' . $this->buildAttributes($attributes) . '>' . e($value) . '</textarea>';

            case 'select':
                unset($attributes['type'], $attributes['class']);
                $attributes['class'] = 'form-select';
                $html = '<select ' . $this->buildAttributes($attributes) . '>';
                $html .= '<option value="">-- Select --</option>';

                $choices = $config['choices'] ?? [];
                foreach ($choices as $choice) {
                    $optValue = $choice['value'] ?? $choice;
                    $optLabel = $choice['label'] ?? $choice;
                    $selected = ($value == $optValue) ? ' selected' : '';
                    $html .= '<option value="' . e($optValue) . '"' . $selected . '>' . e($optLabel) . '</option>';
                }

                $html .= '</select>';
                return $html;

            case 'multi_select':
                unset($attributes['type']);
                $attributes['name'] = $name . '[]';
                $attributes['multiple'] = 'multiple';
                $attributes['class'] = 'form-select';
                $attributes['size'] = min(count($config['choices'] ?? []), 8);

                $selectedValues = is_array($value) ? $value : ($value ? explode(',', $value) : []);

                $html = '<select ' . $this->buildAttributes($attributes) . '>';

                $choices = $config['choices'] ?? [];
                foreach ($choices as $choice) {
                    $optValue = $choice['value'] ?? $choice;
                    $optLabel = $choice['label'] ?? $choice;
                    $selected = in_array($optValue, $selectedValues) ? ' selected' : '';
                    $html .= '<option value="' . e($optValue) . '"' . $selected . '>' . e($optLabel) . '</option>';
                }

                $html .= '</select>';
                $html .= '<div class="form-text">Hold Ctrl/Cmd to select multiple</div>';
                return $html;

            case 'radio':
                $html = '<div class="radio-group">';
                $choices = $config['choices'] ?? [];

                foreach ($choices as $i => $choice) {
                    $optValue = $choice['value'] ?? $choice;
                    $optLabel = $choice['label'] ?? $choice;
                    $checked = ($value == $optValue) ? ' checked' : '';
                    $radioId = $name . '_' . $i;

                    $html .= '<div class="form-check">';
                    $html .= '<input class="form-check-input" type="radio" name="' . e($name) . '" ';
                    $html .= 'id="' . $radioId . '" value="' . e($optValue) . '"' . $checked;
                    if ($required && $i === 0) {
                        $html .= ' required';
                    }
                    $html .= '>';
                    $html .= '<label class="form-check-label" for="' . $radioId . '">' . e($optLabel) . '</label>';
                    $html .= '</div>';
                }

                $html .= '</div>';
                return $html;

            case 'checkbox':
                $html = '<div class="form-check">';
                $checked = $value ? ' checked' : '';
                $html .= '<input class="form-check-input" type="checkbox" name="' . e($name) . '" ';
                $html .= 'id="' . e($name) . '" value="1"' . $checked . '>';
                $html .= '<label class="form-check-label" for="' . e($name) . '">';
                $html .= e($field['checkbox_label'] ?? 'Yes');
                $html .= '</label>';
                $html .= '</div>';
                return $html;

            case 'user_select':
                unset($attributes['type'], $attributes['class']);
                $attributes['class'] = 'form-select';

                $html = '<select ' . $this->buildAttributes($attributes) . '>';
                $html .= '<option value="">-- Select User --</option>';

                // Get staff users
                $users = $this->db->fetchAll(
                    "SELECT id, name, email FROM users
                     WHERE role IN ('super_admin', 'admin', 'principal', 'counselor', 'staff')
                     AND is_active = 1
                     ORDER BY name"
                );

                foreach ($users as $user) {
                    $selected = ($value == $user['id']) ? ' selected' : '';
                    $html .= '<option value="' . $user['id'] . '"' . $selected . '>';
                    $html .= e($user['name']) . ' (' . e($user['email']) . ')';
                    $html .= '</option>';
                }

                $html .= '</select>';
                return $html;

            case 'file':
                $attributes['type'] = 'file';
                unset($attributes['value']);
                if (!empty($config['accept'])) {
                    $attributes['accept'] = $config['accept'];
                }
                if (!empty($config['multiple'])) {
                    $attributes['multiple'] = 'multiple';
                    $attributes['name'] = $name . '[]';
                }
                return '<input ' . $this->buildAttributes($attributes) . '>';

            default:
                return '<p class="text-muted">Unsupported field type: ' . e($type) . '</p>';
        }
    }

    /**
     * Build HTML attributes string
     */
    private function buildAttributes(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $val) {
            if (is_bool($val)) {
                if ($val) {
                    $parts[] = e($key);
                }
            } else {
                $parts[] = e($key) . '="' . e($val) . '"';
            }
        }
        return implode(' ', $parts);
    }

    /**
     * Render CSRF token
     */
    private function renderCsrfToken(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token'] ?? '') . '">';
    }

    /**
     * Render submit button
     */
    private function renderSubmitButton(): string
    {
        $html = '<div class="form-actions mt-4 pt-3 border-top">';
        $html .= '<button type="submit" class="btn btn-primary btn-lg me-2">';
        $html .= '<i class="bi bi-send-check"></i> Submit Report';
        $html .= '</button>';
        $html .= '<a href="list.php" class="btn btn-outline-secondary">';
        $html .= '<i class="bi bi-list-ul"></i> View All Reports';
        $html .= '</a>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Render client-side JavaScript for form enhancement
     */
    private function renderJavaScript(): string
    {
        return <<<'JS'
<script>
(function() {
    const form = document.getElementById('dynamicSectionForm');
    if (!form) return;

    // AJAX form submission (PWA-style)
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';

        try {
            const formData = new FormData(form);
            const response = await fetch('/api/section-submissions.php?action=submit', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                // Success notification
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message || 'Your submission has been recorded.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        if (result.redirect) {
                            window.location.href = result.redirect;
                        } else {
                            form.reset();
                        }
                    });
                } else {
                    alert(result.message || 'Submission successful!');
                    form.reset();
                }
            } else {
                // Error notification
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.error || 'Something went wrong.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Error: ' + (result.error || 'Something went wrong.'));
                }

                // Display field errors
                if (result.errors) {
                    Object.keys(result.errors).forEach(fieldName => {
                        const field = form.querySelector('[name="' + fieldName + '"]');
                        if (field) {
                            field.classList.add('is-invalid');
                            const feedback = field.parentElement.querySelector('.invalid-feedback');
                            if (feedback) {
                                feedback.textContent = result.errors[fieldName];
                                feedback.classList.add('d-block');
                            }
                        }
                    });
                }
            }
        } catch (error) {
            console.error('Form submission error:', error);
            alert('Network error. Please check your connection and try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    // Clear validation on input change
    form.querySelectorAll('.form-control, .form-select, .form-check-input').forEach(input => {
        input.addEventListener('change', function() {
            this.classList.remove('is-invalid');
            const feedback = this.parentElement.querySelector('.invalid-feedback');
            if (feedback) {
                feedback.classList.remove('d-block');
            }
        });
    });

    // Auto-resize textareas
    form.querySelectorAll('textarea').forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });
})();
</script>
JS;
    }

    /**
     * Check if field is admin-only
     */
    private function isAdminField(array $field): bool
    {
        $adminFields = ['status', 'assigned_to', 'staff_notes', 'resolution_date'];
        return in_array($field['field_name'], $adminFields);
    }

    /**
     * Check if current user is staff
     */
    private function isStaffUser(): bool
    {
        $user = Auth::getUser();
        if (!$user) return false;

        $role = Auth::getEffectiveRole();
        return in_array($role, ['super_admin', 'admin', 'principal', 'counselor', 'staff']);
    }
}
