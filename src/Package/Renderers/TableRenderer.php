<?php

namespace Hub\Package\Renderers;

use Hub\Package\Contracts\ComponentRendererInterface;
use Hub\Package\IconMapper;
use Hub\Package\VisualConfig;

/**
 * Table Renderer
 *
 * Renders data tables from JSON component definitions.
 * Features: pagination, sorting, search, row actions, bulk actions, export.
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §4 (Student Directory table example)
 */
class TableRenderer implements ComponentRendererInterface
{
    public function getType(): string
    {
        return 'table';
    }

    public function getAssets(): array
    {
        return [
            'css' => ['/assets/css/package-components.css'],
            'js'  => ['/assets/js/package-table.js'],
        ];
    }

    public function render(array $config, array $data, array $context): string
    {
        $componentId = $config['id'] ?? 'pkg-table-' . uniqid();
        $columns = $config['columns'] ?? [];
        $rowActions = $config['actions'] ?? $config['rowActions'] ?? [];
        $bulkActions = $config['bulkActions'] ?? [];
        $pagination = $config['pagination'] ?? ['perPage' => 50];
        $queryName = $config['dataQuery'] ?? $config['query'] ?? '';

        $rows = $data['data'] ?? [];
        $paginationData = $data['meta'] ?? $data['pagination'] ?? [];
        $total = $paginationData['total'] ?? count($rows);
        $page = $paginationData['page'] ?? 1;
        $perPage = $paginationData['perPage'] ?? $pagination['perPage'] ?? $pagination['pageSize'] ?? 50;
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        $packageId = $context['packageId'] ?? '';
        $csrfToken = $context['csrfToken'] ?? '';
        $baseUrl = rtrim($context['baseUrl'] ?? '', '/');

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

        // Table toolbar
        $html .= '<div class="pkg-table-toolbar" id="' . e($componentId) . '-toolbar">';

        // Result count
        $html .= '<div class="pkg-table-count">';
        $html .= '<span class="count-label">Showing <strong>' . count($rows) . '</strong> of <strong>' . (int)$total . '</strong> records</span>';
        $html .= '</div>';

        // Top pagination controls (right side of toolbar)
        if ($totalPages > 1) {
            $html .= $this->renderCompactPagination($page, $totalPages, $perPage, $pagination, $componentId);
        }

        // Bulk actions (hidden on mobile)
        if (!empty($bulkActions)) {
            $html .= '<div class="pkg-bulk-actions pkg-hide-mobile" style="display:none;" id="' . e($componentId) . '-bulk">';
            $html .= '<span class="bulk-count">0 selected</span>';
            foreach ($bulkActions as $action) {
                $accessRoles = $action['access'] ?? [];
                $userRole = $context['user']['role'] ?? '';
                if (!empty($accessRoles) && !in_array($userRole, $accessRoles)) {
                    continue; // Skip actions user can't access
                }
                $html .= $this->renderBulkAction($action, $componentId, $csrfToken);
            }
            $html .= '</div>';
        }

        $html .= '</div>'; // end toolbar

        // Table
        $html .= '<div class="pkg-table-container">';
        $html .= '<table class="pkg-table" id="' . e($componentId) . '" ';
        $html .= 'data-query="' . e($queryName) . '" ';
        $html .= 'data-package="' . e($packageId) . '" ';
        $html .= 'data-page="' . (int)$page . '" ';
        $html .= 'data-per-page="' . (int)$perPage . '" ';
        $html .= 'data-total="' . (int)$total . '">';

        // Header
        $html .= '<thead><tr>';

        // Checkbox column for bulk actions (hidden on mobile)
        if (!empty($bulkActions)) {
            $html .= '<th class="pkg-col-checkbox pkg-hide-mobile"><input type="checkbox" class="pkg-select-all" data-table="' . e($componentId) . '"></th>';
        }

        foreach ($columns as $col) {
            $html .= $this->renderColumnHeader($col);
        }

        // Actions column
        if (!empty($rowActions)) {
            $html .= '<th class="pkg-col-actions">Actions</th>';
        }

        $html .= '</tr></thead>';

        // Body
        $html .= '<tbody>';
        if (empty($rows)) {
            $colCount = count($columns) + (!empty($bulkActions) ? 1 : 0) + (!empty($rowActions) ? 1 : 0);
            $html .= '<tr><td colspan="' . $colCount . '" class="pkg-empty-state">';
            $html .= '<div class="pkg-empty-icon"><i class="bi bi-inbox"></i></div>';
            $html .= '<div class="pkg-empty-text">No records found</div>';
            $html .= '</td></tr>';
        } else {
            foreach ($rows as $row) {
                $html .= $this->renderRow($row, $columns, $rowActions, $bulkActions, $componentId, $context);
            }
        }
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>'; // end table-container

        // Pagination
        if ($totalPages > 1) {
            $html .= $this->renderPagination($page, $totalPages, $perPage, $pagination, $componentId);
        }

        // Data attributes for JS
        $html .= '<script>window.__pkgTableConfig = window.__pkgTableConfig || {}; ';
        $html .= 'window.__pkgTableConfig["' . e($componentId) . '"] = ' . json_encode([
            'query' => $queryName,
            'packageId' => $packageId,
            'columns' => array_map(fn($c) => ['key' => $c['key'], 'sortable' => $c['sortable'] ?? false], $columns),
            'defaultSort' => $config['defaultSort'] ?? null,
            'pagination' => $pagination,
            'csrfToken' => $csrfToken,
            'baseUrl' => $baseUrl,
        ]) . ';</script>';

        return $html;
    }

    private function renderColumnHeader(array $col): string
    {
        $key = $col['key'] ?? '';
        $label = $col['label'] ?? $key;
        $sortable = $col['sortable'] ?? false;
        $width = $col['width'] ?? '';
        $responsive = $col['responsive'] ?? '';

        $classes = ['pkg-col'];
        $attrs = '';

        if ($sortable) {
            $classes[] = 'pkg-col-sortable';
            $attrs .= ' data-sort-key="' . e($key) . '" role="button" tabindex="0"';
        }

        // Responsive visibility classes
        if ($responsive === 'hide-mobile') {
            $classes[] = 'pkg-hide-mobile';
        } elseif ($responsive === 'hide-tablet') {
            $classes[] = 'pkg-hide-tablet';
        }

        if ($width) {
            $attrs .= ' style="width:' . e($width) . '"';
        }

        return '<th class="' . implode(' ', $classes) . '"' . $attrs . '>' . e($label) . ($sortable ? ' <i class="bi bi-arrow-down-up pkg-sort-icon"></i>' : '') . '</th>';
    }

    private function renderRow(array $row, array $columns, array $rowActions, array $bulkActions, string $componentId, array $context): string
    {
        $rowId = $row['id'] ?? $row['student_id'] ?? uniqid();
        $html = '<tr data-row-id="' . e($rowId) . '">';

        // Checkbox (hidden on mobile)
        if (!empty($bulkActions)) {
            $html .= '<td class="pkg-col-checkbox pkg-hide-mobile"><input type="checkbox" class="pkg-row-select" value="' . e($rowId) . '" data-table="' . e($componentId) . '"></td>';
        }

        // Data cells
        foreach ($columns as $col) {
            $html .= $this->renderCell($row, $col);
        }

        // Row actions
        if (!empty($rowActions)) {
            $html .= '<td class="pkg-col-actions">';
            $html .= '<div class="pkg-action-group">';
            foreach ($rowActions as $action) {
                $html .= $this->renderRowAction($action, $row, $context);
            }
            $html .= '</div>';
            $html .= '</td>';
        }

        $html .= '</tr>';
        return $html;
    }

    private function renderCell(array $row, array $col): string
    {
        $key = $col['key'] ?? '';
        $style = $col['style'] ?? 'text';
        $value = $row[$key] ?? $col['value'] ?? '';
        $copyable = $col['copyable'] ?? false;
        $responsive = $col['responsive'] ?? '';

        $cellClasses = ['pkg-cell', 'pkg-cell-' . e($style)];
        if ($responsive === 'hide-mobile') {
            $cellClasses[] = 'pkg-hide-mobile';
        } elseif ($responsive === 'hide-tablet') {
            $cellClasses[] = 'pkg-hide-tablet';
        }

        $html = '<td class="' . implode(' ', $cellClasses) . '">';

        switch ($style) {
            case 'badge':
                $color = $col['badgeColor'] ?? $this->inferBadgeColor($key, $value);
                $html .= '<span class="badge pkg-grade-badge pkg-badge-' . e($color) . '">' . e($value) . '</span>';
                break;

            case 'masked':
                $actualValue = $row[$key] ?? '';
                $maskedDisplay = $actualValue ? str_repeat('•', min(strlen($actualValue), 8)) : '••••••';
                $html .= '<span class="pkg-masked-value" data-actual="' . e($actualValue) . '">' . e($maskedDisplay) . '</span>';
                $html .= ' <button type="button" class="btn btn-xs btn-outline-warning pkg-show-btn" title="Show/Hide">';
                $html .= '<i class="fas fa-eye"></i> Show</button>';
                if ($copyable && $actualValue) {
                    $html .= ' <button class="btn-copy pkg-copy-btn" data-value="' . e($actualValue) . '" title="Copy"><i class="bi bi-clipboard"></i></button>';
                }
                break;

            case 'link':
                $html .= '<a href="' . e($value) . '" class="pkg-link">' . e($value) . '</a>';
                if ($copyable) {
                    $html .= ' <button class="btn-copy pkg-copy-btn" data-value="' . e($value) . '" title="Copy"><i class="bi bi-clipboard"></i></button>';
                }
                break;

            case 'date':
                $formatted = $value ? date('M j, Y', strtotime($value)) : '—';
                $html .= e($formatted);
                break;

            case 'datetime':
                $formatted = $value ? date('M j, Y g:ia', strtotime($value)) : '—';
                $html .= e($formatted);
                break;

            case 'currency':
                $html .= '$' . number_format((float)$value, 2);
                break;

            case 'boolean':
                $icon = $value ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger';
                $html .= '<i class="bi ' . $icon . '"></i>';
                break;

            default:
                $displayValue = is_array($value) ? json_encode($value) : (string)$value;
                $html .= e($displayValue);
                if ($copyable) {
                    $html .= ' <button class="btn-copy pkg-copy-btn" data-value="' . e($displayValue) . '" title="Copy"><i class="bi bi-clipboard"></i></button>';
                }
                break;
        }

        $html .= '</td>';
        return $html;
    }

    /**
     * Infer badge color based on field key and value
     */
    private function inferBadgeColor(string $key, string $value): string
    {
        // Grade-specific colors
        if ($key === 'grade') {
            $grade = strtoupper($value);
            return match ($grade) {
                'PK'  => 'olive',
                'KG'  => 'gold',
                '1', '2', '3' => 'teal',
                '4', '5' => 'blue',
                '6', '7', '8' => 'purple',
                '9', '10' => 'orange',
                '11', '12' => 'red',
                default => 'secondary',
            };
        }

        // School/OU colors
        if ($key === 'ou_for_google' || $key === 'school') {
            return 'info';
        }

        return 'primary';
    }

    private function renderRowAction(array $action, array $row, array $context): string
    {
        $id = $action['id'] ?? '';
        $label = $action['label'] ?? $id;
        $icon = $action['icon'] ?? '';
        $type = $action['type'] ?? 'route';
        $variant = $action['variant'] ?? $action['style'] ?? 'warning';

        // Map lucide icons to FontAwesome
        $icon = IconMapper::map($icon);

        $rowId = $row['id'] ?? $row['student_id'] ?? '';

        $classes = "btn btn-sm btn-{$variant} pkg-row-action";

        if ($type === 'route') {
            // Navigation action
            $to = $action['to'] ?? $action['route'] ?? '#';
            // Replace route params, e.g., {student_id} → actual value
            $to = preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($row) {
                return $row[$matches[1]] ?? $matches[0];
            }, $to);
            $baseUrl = rtrim($context['baseUrl'] ?? '', '/');
            $href = $baseUrl . $to;
            return '<a href="' . e($href) . '" class="' . $classes . '">' . ($icon ? '<i class="' . e($icon) . '"></i> ' : '') . e($label) . '</a>';
        }

        if ($type === 'mutation') {
            // Mutation action (modal, confirmation, etc.)
            $mutation = $action['mutation'] ?? '';
            $requires = $action['requires'] ?? [];
            $cooldown = $action['cooldownSeconds'] ?? 0;
            $confirmMessage = $requires['confirmMessage'] ?? '';

            $dataAttrs = ' data-action="mutation"';
            $dataAttrs .= ' data-mutation="' . e($mutation) . '"';
            $dataAttrs .= ' data-row-id="' . e($rowId) . '"';
            $dataAttrs .= ' data-package="' . e($context['packageId'] ?? '') . '"';
            $dataAttrs .= ' data-csrf="' . e($context['csrfToken'] ?? '') . '"';

            if ($cooldown) {
                $dataAttrs .= ' data-cooldown="' . (int)$cooldown . '"';
            }
            if (!empty($requires['reason'])) {
                $dataAttrs .= ' data-requires-reason="true"';
                if (!empty($requires['reasonOptions'])) {
                    $dataAttrs .= ' data-reason-options="' . e(json_encode($requires['reasonOptions'])) . '"';
                }
            }
            if (!empty($requires['reconfirm'])) {
                $dataAttrs .= ' data-requires-confirm="true"';
                if ($confirmMessage) {
                    $dataAttrs .= ' data-confirm-message="' . e($confirmMessage) . '"';
                }
            }

            return '<button class="' . $classes . '"' . $dataAttrs . '>' . ($icon ? '<i class="' . e($icon) . '"></i> ' : '') . e($label) . '</button>';
        }

        return '';
    }

    private function renderBulkAction(array $action, string $componentId, string $csrfToken): string
    {
        $label = $action['label'] ?? '';
        $icon = IconMapper::map($action['icon'] ?? '');
        $mutation = $action['mutation'] ?? '';
        $variant = $action['variant'] ?? 'secondary';

        return '<button class="btn btn-sm btn-outline-' . e($variant) . ' pkg-bulk-action" ' .
            'data-action="bulk-mutation" data-mutation="' . e($mutation) . '" ' .
            'data-table="' . e($componentId) . '" data-csrf="' . e($csrfToken) . '">' .
            ($icon ? '<i class="' . e($icon) . '"></i> ' : '') . e($label) .
            '</button>';
    }

    private function renderPagination(int $page, int $totalPages, int $perPage, array $pagination, string $componentId): string
    {
        $html = '<div class="pkg-pagination" data-table="' . e($componentId) . '">';
        $html .= '<div class="pkg-page-info">Page ' . $page . ' of ' . $totalPages . '</div>';
        $html .= '<div class="pkg-page-nav">';

        // Previous
        $html .= '<button class="btn btn-sm btn-outline-secondary pkg-page-btn" data-page="' . max(1, $page - 1) . '"' . ($page <= 1 ? ' disabled' : '') . '>';
        $html .= '<i class="bi bi-chevron-left"></i> Prev</button>';

        // Page numbers (show max 7 around current)
        $start = max(1, $page - 3);
        $end = min($totalPages, $page + 3);

        if ($start > 1) {
            $html .= '<button class="btn btn-sm btn-outline-secondary pkg-page-btn" data-page="1">1</button>';
            if ($start > 2) {
                $html .= '<span class="pkg-page-ellipsis">...</span>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $page ? ' btn-primary' : ' btn-outline-secondary';
            $html .= '<button class="btn btn-sm pkg-page-btn' . $active . '" data-page="' . $i . '">' . $i . '</button>';
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<span class="pkg-page-ellipsis">...</span>';
            }
            $html .= '<button class="btn btn-sm btn-outline-secondary pkg-page-btn" data-page="' . $totalPages . '">' . $totalPages . '</button>';
        }

        // Next
        $html .= '<button class="btn btn-sm btn-outline-secondary pkg-page-btn" data-page="' . min($totalPages, $page + 1) . '"' . ($page >= $totalPages ? ' disabled' : '') . '>';
        $html .= 'Next <i class="bi bi-chevron-right"></i></button>';

        $html .= '</div>'; // page-nav

        // Per-page selector
        $pageSizeOptions = $pagination['pageSizeOptions'] ?? [25, 50, 100];
        $html .= '<div class="pkg-page-size">';
        $html .= '<select class="form-select form-select-sm pkg-page-size-select" data-table="' . e($componentId) . '">';
        foreach ($pageSizeOptions as $size) {
            $selected = $size == $perPage ? ' selected' : '';
            $html .= '<option value="' . (int)$size . '"' . $selected . '>' . $size . ' per page</option>';
        }
        $html .= '</select>';
        $html .= '</div>';

        $html .= '</div>'; // pagination
        return $html;
    }

    /**
     * Render compact pagination for the toolbar (prev/next + page size, no page numbers)
     */
    private function renderCompactPagination(int $page, int $totalPages, int $perPage, array $pagination, string $componentId): string
    {
        $html = '<div class="pkg-pagination pkg-pagination-compact" data-table="' . e($componentId) . '">';

        // Per-page selector
        $pageSizeOptions = $pagination['pageSizeOptions'] ?? [25, 50, 100];
        $html .= '<div class="pkg-page-size">';
        $html .= '<select class="form-select form-select-sm pkg-page-size-select" data-table="' . e($componentId) . '">';
        foreach ($pageSizeOptions as $size) {
            $selected = $size == $perPage ? ' selected' : '';
            $html .= '<option value="' . (int)$size . '"' . $selected . '>' . $size . ' per page</option>';
        }
        $html .= '</select>';
        $html .= '</div>';

        // Page info + nav
        $html .= '<div class="pkg-page-nav">';
        $html .= '<span class="pkg-page-info">Page ' . $page . ' of ' . $totalPages . '</span>';

        $html .= '<button class="btn btn-sm btn-outline-secondary pkg-page-btn" data-page="' . max(1, $page - 1) . '"' . ($page <= 1 ? ' disabled' : '') . '>';
        $html .= '<i class="bi bi-chevron-left"></i></button>';

        $html .= '<button class="btn btn-sm btn-outline-secondary pkg-page-btn" data-page="' . min($totalPages, $page + 1) . '"' . ($page >= $totalPages ? ' disabled' : '') . '>';
        $html .= '<i class="bi bi-chevron-right"></i></button>';

        $html .= '</div>'; // page-nav
        $html .= '</div>'; // pagination-compact
        return $html;
    }
}
