<?php

namespace Hub\Package\Renderers;

use Hub\Package\Contracts\ComponentRendererInterface;
use Hub\Package\IconMapper;
use Hub\Package\VisualConfig;

/**
 * Detail Renderer
 *
 * Renders single-record detail views from JSON component definitions.
 * Supports sectioned layouts, field masking, related data panels.
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §4 (student_directory_detail example)
 */
class DetailRenderer implements ComponentRendererInterface
{
    public function getType(): string
    {
        return 'detail';
    }

    public function getAssets(): array
    {
        return [
            'css' => ['/assets/css/package-components.css'],
            'js'  => ['/assets/js/package-detail.js'],
        ];
    }

    public function render(array $config, array $data, array $context): string
    {
        $componentId = $config['id'] ?? 'pkg-detail-' . uniqid();
        $sections = $config['sections'] ?? [];
        $actions = $config['actions'] ?? [];
        $queryName = $config['query'] ?? '';
        $backUrl = $config['backUrl'] ?? $config['backRoute'] ?? '';

        $record = $data['data'] ?? $data['record'] ?? $data;
        $packageId = $context['packageId'] ?? '';
        $baseUrl = rtrim($context['baseUrl'] ?? '', '/');
        $csrfToken = $context['csrfToken'] ?? '';

        if (empty($record) || (isset($record['data']) && empty($record['data']))) {
            return $this->renderNotFound($backUrl, $baseUrl);
        }

        $html = '';

        // Emit visual token overrides as a scoped <style> block
        if (!empty($config['visual'])) {
            $preset = $config['visual']['preset'] ?? 'comfortable';
            $tokens = $config['visual']['tokens'] ?? [];
            $resolved = VisualConfig::resolveConfig($preset, $tokens);
            if (!empty($resolved)) {
                $html .= VisualConfig::toStyleBlock($resolved, '', $componentId);
            }
        }

        // Header with back button and actions
        $html .= '<div class="pkg-detail-header">';
        if ($backUrl) {
            $html .= '<a href="' . e($baseUrl . $backUrl) . '" class="btn btn-sm btn-outline-secondary pkg-back-btn">';
            $html .= '<i class="bi bi-arrow-left"></i> Back</a>';
        }

        if (!empty($actions)) {
            $html .= '<div class="pkg-detail-actions">';
            foreach ($actions as $action) {
                $html .= $this->renderAction($action, $record, $context);
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        // Detail card
        $html .= '<div class="pkg-detail-card" id="' . e($componentId) . '" data-query="' . e($queryName) . '" data-package="' . e($packageId) . '">';

        if (!empty($sections)) {
            foreach ($sections as $section) {
                $html .= $this->renderSection($section, $record);
            }
        } else {
            // No sections defined — render all fields flat
            $html .= $this->renderFlatFields($record);
        }

        $html .= '</div>'; // end detail-card

        return $html;
    }

    private function renderSection(array $section, array $record): string
    {
        $title = $section['title'] ?? '';
        $fields = $section['fields'] ?? [];
        $columns = $section['columns'] ?? 2; // 1, 2, or 3 column layout

        $html = '<div class="pkg-detail-section">';

        if ($title) {
            $html .= '<h3 class="pkg-detail-section-title">' . e($title) . '</h3>';
        }

        $html .= '<div class="pkg-detail-grid pkg-detail-cols-' . (int)$columns . '">';

        foreach ($fields as $field) {
            $html .= $this->renderField($field, $record);
        }

        $html .= '</div>'; // grid
        $html .= '</div>'; // section

        return $html;
    }

    private function renderField(array $field, array $record): string
    {
        $key = $field['key'] ?? '';
        $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $key));
        $type = $field['type'] ?? 'text';
        $value = $record[$key] ?? '';
        $copyable = $field['copyable'] ?? false;

        $html = '<div class="pkg-detail-field">';
        $html .= '<dt class="pkg-detail-label">' . e($label) . '</dt>';
        $html .= '<dd class="pkg-detail-value">';

        switch ($type) {
            case 'badge':
                $color = $field['badgeColor'] ?? 'primary';
                $html .= '<span class="badge badge-' . e($color) . '">' . e($value) . '</span>';
                break;

            case 'date':
                $html .= $value ? e(date('M j, Y', strtotime($value))) : '<span class="text-muted">—</span>';
                break;

            case 'datetime':
                $html .= $value ? e(date('M j, Y g:ia', strtotime($value))) : '<span class="text-muted">—</span>';
                break;

            case 'email':
                $html .= '<a href="mailto:' . e($value) . '">' . e($value) . '</a>';
                break;

            case 'link':
                $html .= '<a href="' . e($value) . '" target="_blank">' . e($value) . '</a>';
                break;

            case 'boolean':
                $html .= $value
                    ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Yes</span>'
                    : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> No</span>';
                break;

            case 'currency':
                $html .= '$' . number_format((float)$value, 2);
                break;

            case 'masked':
                $actualValue = $value;
                $maskedDisplay = $actualValue ? str_repeat('•', min(strlen($actualValue), 8)) : '••••••';
                $html .= '<span class="pkg-masked-value" data-actual="' . e($actualValue) . '">' . e($maskedDisplay) . '</span>';
                $html .= ' <button type="button" class="btn btn-xs btn-outline-warning pkg-show-btn" title="Show/Hide">';
                $html .= '<i class="fas fa-eye"></i> Show</button>';
                break;

            case 'long_text':
                $html .= '<div class="pkg-detail-longtext">' . nl2br(e($value)) . '</div>';
                break;

            case 'list':
                if (is_array($value)) {
                    $html .= '<ul class="pkg-detail-list">';
                    foreach ($value as $item) {
                        $html .= '<li>' . e(is_array($item) ? json_encode($item) : $item) . '</li>';
                    }
                    $html .= '</ul>';
                } else {
                    $html .= e($value);
                }
                break;

            case 'image':
                $html .= $value ? '<img src="' . e($value) . '" class="pkg-detail-image" alt="' . e($label) . '">' : '<span class="text-muted">No image</span>';
                break;

            default:
                $displayValue = is_array($value) ? json_encode($value) : (string)$value;
                $html .= $displayValue !== '' ? e($displayValue) : '<span class="text-muted">—</span>';
                break;
        }

        if ($copyable && $value) {
            $html .= ' <button class="btn-copy pkg-copy-btn" data-value="' . e($value) . '" title="Copy"><i class="bi bi-clipboard"></i></button>';
        }

        $html .= '</dd>';
        $html .= '</div>';

        return $html;
    }

    private function renderFlatFields(array $record): string
    {
        $html = '<div class="pkg-detail-section">';
        $html .= '<div class="pkg-detail-grid pkg-detail-cols-2">';

        foreach ($record as $key => $value) {
            if (str_starts_with($key, '_')) continue; // Skip internal fields
            $html .= $this->renderField(['key' => $key, 'label' => ucfirst(str_replace('_', ' ', $key))], $record);
        }

        $html .= '</div></div>';
        return $html;
    }

    private function renderAction(array $action, array $record, array $context): string
    {
        $label = $action['label'] ?? '';
        $icon = $action['icon'] ?? '';
        $type = $action['type'] ?? 'route';
        $variant = $action['variant'] ?? 'primary';

        $classes = "btn btn-sm btn-{$variant}";

        // Map lucide icons
        if ($icon) {
            $icon = IconMapper::map($icon);
        }

        if ($type === 'route') {
            $to = $action['to'] ?? $action['route'] ?? '#';
            $to = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($record) {
                return $record[$m[1]] ?? $m[0];
            }, $to);
            $baseUrl = rtrim($context['baseUrl'] ?? '', '/');
            return '<a href="' . e($baseUrl . $to) . '" class="' . $classes . '">' .
                ($icon ? '<i class="' . e($icon) . '"></i> ' : '') . e($label) . '</a>';
        }

        if ($type === 'mutation') {
            $rowId = $record['id'] ?? $record['student_id'] ?? '';
            $mutation = $action['mutation'] ?? '';
            return '<button class="' . $classes . ' pkg-row-action" data-action="mutation" ' .
                'data-mutation="' . e($mutation) . '" data-row-id="' . e($rowId) . '" ' .
                'data-package="' . e($context['packageId'] ?? '') . '" ' .
                'data-csrf="' . e($context['csrfToken'] ?? '') . '">' .
                ($icon ? '<i class="' . e($icon) . '"></i> ' : '') . e($label) . '</button>';
        }

        return '';
    }

    private function renderNotFound(string $backUrl, string $baseUrl): string
    {
        $html = '<div class="pkg-empty-state" style="padding: 3rem; text-align: center;">';
        $html .= '<div class="pkg-empty-icon" style="font-size: 3rem; margin-bottom: 1rem;"><i class="bi bi-search"></i></div>';
        $html .= '<h3>Record Not Found</h3>';
        $html .= '<p class="text-muted">The requested record does not exist or you do not have access.</p>';
        if ($backUrl) {
            $html .= '<a href="' . e($baseUrl . $backUrl) . '" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Go Back</a>';
        }
        $html .= '</div>';
        return $html;
    }
}
