<?php

namespace Hub\Package\Renderers;

use Hub\Package\Contracts\ComponentRendererInterface;

/**
 * Form Renderer
 *
 * Renders forms from JSON component definitions.
 * Features: field types, validation, CSRF, file uploads, conditional fields.
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §1A (form component type)
 */
class FormRenderer implements ComponentRendererInterface
{
    public function getType(): string
    {
        return 'form';
    }

    public function getAssets(): array
    {
        return [
            'css' => ['/assets/css/package-components.css'],
            'js'  => ['/assets/js/package-form.js'],
        ];
    }

    public function render(array $config, array $data, array $context): string
    {
        $componentId = $config['id'] ?? 'pkg-form-' . uniqid();
        $mutation = $config['mutation'] ?? '';
        $fields = $config['fields'] ?? [];
        $sections = $config['sections'] ?? [];
        $submitLabel = $config['submitLabel'] ?? 'Save';
        $cancelUrl = $config['cancelUrl'] ?? $config['cancelRoute'] ?? '';
        $layout = $config['layout'] ?? 'vertical'; // vertical | horizontal | inline

        $packageId = $context['packageId'] ?? '';
        $csrfToken = $context['csrfToken'] ?? '';
        $baseUrl = rtrim($context['baseUrl'] ?? '', '/');

        // Existing record data for edit mode
        $record = $data['data'] ?? $data['record'] ?? [];
        $isEdit = !empty($record);
        $errors = $data['errors'] ?? [];

        $html = '';

        // Form element
        $html .= '<form class="pkg-form pkg-form-' . e($layout) . '" id="' . e($componentId) . '" ';
        $html .= 'data-mutation="' . e($mutation) . '" ';
        $html .= 'data-package="' . e($packageId) . '" ';
        $html .= 'method="POST" novalidate>';

        // CSRF token
        $html .= '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">';

        // Edit mode: include record ID
        if ($isEdit && isset($record['id'])) {
            $html .= '<input type="hidden" name="id" value="' . e($record['id']) . '">';
        }

        // Render by sections if defined, otherwise flat field list
        if (!empty($sections)) {
            foreach ($sections as $idx => $section) {
                $html .= $this->renderSection($section, $record, $errors, $idx);
            }
        } else {
            foreach ($fields as $field) {
                $html .= $this->renderField($field, $record, $errors);
            }
        }

        // Form actions
        $html .= '<div class="pkg-form-actions">';
        $html .= '<button type="submit" class="btn btn-primary pkg-form-submit">';
        $html .= '<i class="bi bi-check-lg"></i> ' . e($submitLabel);
        $html .= '</button>';

        if ($cancelUrl) {
            $html .= ' <a href="' . e($baseUrl . $cancelUrl) . '" class="btn btn-outline-secondary">Cancel</a>';
        }
        $html .= '</div>';

        $html .= '</form>';

        // Form config for JS
        $html .= '<script>window.__pkgFormConfig = window.__pkgFormConfig || {}; ';
        $html .= 'window.__pkgFormConfig["' . e($componentId) . '"] = ' . json_encode([
            'mutation' => $mutation,
            'packageId' => $packageId,
            'csrfToken' => $csrfToken,
            'baseUrl' => $baseUrl,
            'isEdit' => $isEdit,
        ]) . ';</script>';

        return $html;
    }

    private function renderSection(array $section, array $record, array $errors, int $idx): string
    {
        $title = $section['title'] ?? '';
        $description = $section['description'] ?? '';
        $fields = $section['fields'] ?? [];
        $collapsible = $section['collapsible'] ?? false;
        $collapsed = $section['collapsed'] ?? false;
        $grid = $section['grid'] ?? null; // e.g. 2 for two-column layout

        $sectionId = 'pkg-form-section-' . $idx;

        $html = '<fieldset class="pkg-form-section' . ($collapsible ? ' pkg-collapsible' : '') . '" id="' . $sectionId . '">';

        if ($title) {
            $html .= '<legend class="pkg-form-section-title">';
            if ($collapsible) {
                $html .= '<button type="button" class="pkg-section-toggle" data-target="' . $sectionId . '-body"';
                $html .= ' aria-expanded="' . ($collapsed ? 'false' : 'true') . '">';
                $html .= e($title);
                $html .= ' <i class="bi bi-chevron-' . ($collapsed ? 'right' : 'down') . '"></i>';
                $html .= '</button>';
            } else {
                $html .= e($title);
            }
            $html .= '</legend>';
        }

        if ($description) {
            $html .= '<p class="pkg-form-section-desc">' . e($description) . '</p>';
        }

        $bodyStyle = ($collapsible && $collapsed) ? ' style="display:none;"' : '';
        $gridClass = $grid ? ' pkg-form-grid pkg-form-grid-' . (int)$grid : '';
        $html .= '<div class="pkg-form-section-body' . $gridClass . '" id="' . $sectionId . '-body"' . $bodyStyle . '>';

        foreach ($fields as $field) {
            $html .= $this->renderField($field, $record, $errors);
        }

        $html .= '</div>';
        $html .= '</fieldset>';

        return $html;
    }

    private function renderField(array $field, array $record, array $errors): string
    {
        $key = $field['key'] ?? $field['name'] ?? '';
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $key));
        $required = $field['required'] ?? false;
        $placeholder = $field['placeholder'] ?? '';
        $helpText = $field['helpText'] ?? $field['description'] ?? '';
        $hidden = $field['hidden'] ?? false;
        $readOnly = $field['readOnly'] ?? false;
        $value = $record[$key] ?? $field['default'] ?? '';
        $error = $errors[$key] ?? '';

        if ($hidden) {
            return '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
        }

        $fieldId = 'field-' . e($key);
        $spanClass = '';
        if (!empty($field['span'])) {
            $spanClass = ' pkg-form-span-' . (int)$field['span'];
        } elseif (!empty($field['fullWidth'])) {
            $spanClass = ' pkg-form-span-full';
        }

        // Checkboxes use inline layout: [checkbox] Label
        if ($type === 'checkbox') {
            $html = '<div class="pkg-form-group pkg-form-group-checkbox' . ($error ? ' has-error' : '') . $spanClass . '" data-field="' . e($key) . '">';
            $html .= $this->renderInput($field, $fieldId, $value, $readOnly, $placeholder);
            $html .= '<label for="' . $fieldId . '" class="pkg-form-label">';
            $html .= e($label);
            if ($required) {
                $html .= ' <span class="pkg-required">*</span>';
            }
            $html .= '</label>';
            if ($helpText) {
                $html .= '<small class="pkg-form-help">' . e($helpText) . '</small>';
            }
            if ($error) {
                $html .= '<div class="pkg-form-error"><i class="bi bi-exclamation-circle"></i> ' . e($error) . '</div>';
            }
            $html .= '</div>';
            return $html;
        }

        $html = '<div class="pkg-form-group' . ($error ? ' has-error' : '') . $spanClass . '" data-field="' . e($key) . '">';

        // Label
        $html .= '<label for="' . $fieldId . '" class="pkg-form-label">';
        $html .= e($label);
        if ($required) {
            $html .= ' <span class="pkg-required">*</span>';
        }
        $html .= '</label>';

        // Input
        $html .= $this->renderInput($field, $fieldId, $value, $readOnly, $placeholder);

        // Help text
        if ($helpText) {
            $html .= '<small class="pkg-form-help">' . e($helpText) . '</small>';
        }

        // Error
        if ($error) {
            $html .= '<div class="pkg-form-error"><i class="bi bi-exclamation-circle"></i> ' . e($error) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function renderInput(array $field, string $fieldId, $value, bool $readOnly, string $placeholder): string
    {
        $key = $field['key'] ?? $field['name'] ?? '';
        $type = $field['type'] ?? 'text';
        $required = $field['required'] ?? false;
        $validation = $field['validation'] ?? [];

        $attrs = ' id="' . $fieldId . '" name="' . e($key) . '"';
        if ($required) $attrs .= ' required';
        if ($readOnly) $attrs .= ' readonly';
        if ($placeholder) $attrs .= ' placeholder="' . e($placeholder) . '"';
        if (!empty($validation['min'])) $attrs .= ' min="' . (int)$validation['min'] . '"';
        if (!empty($validation['max'])) $attrs .= ' max="' . (int)$validation['max'] . '"';
        if (!empty($validation['maxLength'])) $attrs .= ' maxlength="' . (int)$validation['maxLength'] . '"';
        if (!empty($validation['pattern'])) $attrs .= ' pattern="' . e($validation['pattern']) . '"';

        switch ($type) {
            case 'textarea':
                $rows = $field['rows'] ?? 4;
                return '<textarea class="form-control pkg-input"' . $attrs . ' rows="' . (int)$rows . '">' . e($value) . '</textarea>';

            case 'select':
                $options = $field['options'] ?? [];
                $optionsQuery = $field['optionsQuery'] ?? '';
                $allowAll = $field['allowAll'] ?? false;
                $multiple = $field['multiple'] ?? false;

                $html = '<select class="form-select pkg-input"' . $attrs . ($multiple ? ' multiple' : '') . '>';
                if ($allowAll || !$required) {
                    $html .= '<option value="">— Select —</option>';
                }
                foreach ($options as $opt) {
                    if (is_array($opt)) {
                        $optVal = $opt['value'] ?? $opt['id'] ?? '';
                        $optLabel = $opt['label'] ?? $opt['name'] ?? $optVal;
                    } else {
                        $optVal = $optLabel = $opt;
                    }
                    $selected = ($optVal == $value) ? ' selected' : '';
                    $html .= '<option value="' . e($optVal) . '"' . $selected . '>' . e($optLabel) . '</option>';
                }
                $html .= '</select>';
                if ($optionsQuery) {
                    $html .= '<script>document.querySelector("#' . $fieldId . '").dataset.optionsQuery = ' . json_encode($optionsQuery) . ';</script>';
                }
                return $html;

            case 'checkbox':
                $checked = $value ? ' checked' : '';
                return '<input type="checkbox" class="form-check-input pkg-input"' . $attrs . $checked . ' value="1">';

            case 'radio':
                $options = $field['options'] ?? [];
                $html = '<div class="pkg-radio-group">';
                foreach ($options as $idx => $opt) {
                    if (is_array($opt)) {
                        $optVal = $opt['value'] ?? $opt['id'] ?? '';
                        $optLabel = $opt['label'] ?? $optVal;
                    } else {
                        $optVal = $optLabel = $opt;
                    }
                    $checked = ($optVal == $value) ? ' checked' : '';
                    $html .= '<div class="form-check">';
                    $html .= '<input type="radio" class="form-check-input pkg-input" name="' . e($key) . '" id="' . $fieldId . '-' . $idx . '" value="' . e($optVal) . '"' . $checked . '>';
                    $html .= '<label class="form-check-label" for="' . $fieldId . '-' . $idx . '">' . e($optLabel) . '</label>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                return $html;

            case 'date':
                return '<input type="date" class="form-control pkg-input"' . $attrs . ' value="' . e($value) . '">';

            case 'datetime':
                return '<input type="datetime-local" class="form-control pkg-input"' . $attrs . ' value="' . e($value) . '">';

            case 'number':
                $step = $field['step'] ?? 'any';
                return '<input type="number" class="form-control pkg-input"' . $attrs . ' value="' . e($value) . '" step="' . e($step) . '">';

            case 'email':
                return '<input type="email" class="form-control pkg-input"' . $attrs . ' value="' . e($value) . '">';

            case 'file':
                $accept = $field['accept'] ?? '';
                $acceptAttr = $accept ? ' accept="' . e($accept) . '"' : '';
                return '<input type="file" class="form-control pkg-input"' . $attrs . $acceptAttr . '>';

            case 'currency':
                return '<div class="input-group"><span class="input-group-text">$</span><input type="number" class="form-control pkg-input"' . $attrs . ' value="' . e($value) . '" step="0.01" min="0"></div>';

            default: // text, password, url, tel, etc.
                return '<input type="' . e($type) . '" class="form-control pkg-input"' . $attrs . ' value="' . e($value) . '">';
        }
    }
}
