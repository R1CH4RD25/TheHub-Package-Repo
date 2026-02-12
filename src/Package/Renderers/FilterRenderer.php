<?php

namespace Hub\Package\Renderers;

use Hub\Package\Contracts\ComponentRendererInterface;

/**
 * Filter Renderer
 *
 * Renders filter/search bars for data tables from JSON definitions.
 * Supports search, select, date range, and custom filter types.
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §4 (directory_filters example)
 */
class FilterRenderer implements ComponentRendererInterface
{
    public function getType(): string
    {
        return 'filters';
    }

    public function getAssets(): array
    {
        return [
            'css' => ['/assets/css/package-components.css'],
            'js'  => ['/assets/js/package-table.js'], // shares with table
        ];
    }

    public function render(array $config, array $data, array $context): string
    {
        $componentId = $config['id'] ?? 'pkg-filters-' . uniqid();
        $fields = $config['fields'] ?? [];
        $targetTable = $config['target'] ?? ''; // table component ID to filter

        $currentParams = $context['queryParams'] ?? $_GET ?? [];

        $html = '<div class="pkg-filters" id="' . e($componentId) . '"';
        if ($targetTable) {
            $html .= ' data-target-table="' . e($targetTable) . '"';
        }
        $html .= '>';

        $html .= '<div class="pkg-filters-row">';

        foreach ($fields as $field) {
            $html .= $this->renderFilterField($field, $currentParams);
        }

        // Clear filters button
        $html .= '<div class="pkg-filter-field pkg-filter-actions">';
        $html .= '<button class="btn btn-sm btn-outline-secondary pkg-clear-filters" title="Clear all filters">';
        $html .= '<i class="bi bi-x-circle"></i> Clear</button>';
        $html .= '</div>';

        $html .= '</div>'; // filters-row
        $html .= '</div>'; // filters

        return $html;
    }

    private function renderFilterField(array $field, array $currentParams): string
    {
        $type = $field['type'] ?? 'text';
        $param = $field['param'] ?? $field['key'] ?? '';
        $label = $field['label'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $debounce = $field['debounce'] ?? 0;
        $value = $currentParams[$param] ?? '';

        $html = '<div class="pkg-filter-field">';

        if ($label) {
            $html .= '<label class="pkg-filter-label">' . e($label) . '</label>';
        }

        switch ($type) {
            case 'search':
                $html .= '<div class="pkg-search-input">';
                $html .= '<i class="bi bi-search pkg-search-icon"></i>';
                $html .= '<input type="search" class="form-control form-control-sm pkg-filter-input" ';
                $html .= 'name="' . e($param) . '" value="' . e($value) . '" ';
                $html .= 'placeholder="' . e($placeholder ?: 'Search...') . '"';
                if ($debounce) {
                    $html .= ' data-debounce="' . (int)$debounce . '"';
                }
                $html .= '>';
                $html .= '</div>';
                break;

            case 'select':
                $options = $field['options'] ?? [];
                $optionsQuery = $field['optionsQuery'] ?? '';
                $allowAll = $field['allowAll'] ?? true;

                $html .= '<select class="form-select form-select-sm pkg-filter-input" name="' . e($param) . '"';
                if ($optionsQuery) {
                    $html .= ' data-options-query="' . e($optionsQuery) . '"';
                }
                $html .= '>';

                if ($allowAll) {
                    $allLabel = $label ? "All {$label}s" : 'All';
                    $html .= '<option value="">' . e($allLabel) . '</option>';
                }

                foreach ($options as $opt) {
                    if (is_array($opt)) {
                        $optValue = $opt['value'] ?? $opt['id'] ?? '';
                        $optLabel = $opt['label'] ?? $opt['name'] ?? $optValue;
                    } else {
                        $optValue = $optLabel = $opt;
                    }
                    $selected = ((string)$optValue === (string)$value) ? ' selected' : '';
                    $html .= '<option value="' . e($optValue) . '"' . $selected . '>' . e($optLabel) . '</option>';
                }

                $html .= '</select>';
                break;

            case 'date':
                $html .= '<input type="date" class="form-control form-control-sm pkg-filter-input" ';
                $html .= 'name="' . e($param) . '" value="' . e($value) . '">';
                break;

            case 'date_range':
                $startParam = $field['startParam'] ?? $param . '_start';
                $endParam = $field['endParam'] ?? $param . '_end';
                $startValue = $currentParams[$startParam] ?? '';
                $endValue = $currentParams[$endParam] ?? '';

                $html .= '<div class="pkg-date-range">';
                $html .= '<input type="date" class="form-control form-control-sm pkg-filter-input" ';
                $html .= 'name="' . e($startParam) . '" value="' . e($startValue) . '" placeholder="From">';
                $html .= '<span class="pkg-date-separator">to</span>';
                $html .= '<input type="date" class="form-control form-control-sm pkg-filter-input" ';
                $html .= 'name="' . e($endParam) . '" value="' . e($endValue) . '" placeholder="To">';
                $html .= '</div>';
                break;

            case 'checkbox':
                $checked = $value ? ' checked' : '';
                $html .= '<div class="form-check">';
                $html .= '<input type="checkbox" class="form-check-input pkg-filter-input" ';
                $html .= 'name="' . e($param) . '" value="1"' . $checked . '>';
                $html .= '<label class="form-check-label">' . e($placeholder ?: $label) . '</label>';
                $html .= '</div>';
                break;

            default:
                $html .= '<input type="text" class="form-control form-control-sm pkg-filter-input" ';
                $html .= 'name="' . e($param) . '" value="' . e($value) . '" ';
                $html .= 'placeholder="' . e($placeholder) . '">';
                break;
        }

        $html .= '</div>';
        return $html;
    }
}
