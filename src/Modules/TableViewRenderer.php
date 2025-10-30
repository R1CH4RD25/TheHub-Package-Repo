<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\Module;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * TableViewRenderer - Render sortable, filterable data tables
 * 
 * Implements TableView module type from MODULE_CATALOG_V2.md
 * Features: sorting, filtering, pagination, export (CSV/XLSX/PDF),
 * action buttons, PII handling, and responsive design.
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class TableViewRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    private ?array $data = null;
    private int $totalRecords = 0;
    private int $page = 1;
    private int $perPage = 25;
    private ?string $sortColumn = null;
    private string $sortDirection = 'ASC';
    private array $filters = [];
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();
        
        // Parse query parameters
        $this->page = max(1, (int)($_GET['page'] ?? 1));
        $this->perPage = (int)($config['perPage'] ?? 25);
        $this->sortColumn = $_GET['sort'] ?? ($config['defaultSort'] ?? null);
        $this->sortDirection = strtoupper($_GET['dir'] ?? 'ASC');
        
        if (!in_array($this->sortDirection, ['ASC', 'DESC'])) {
            $this->sortDirection = 'ASC';
        }
        
        // Parse filters
        if (!empty($_GET['filter'])) {
            foreach ($_GET['filter'] as $col => $value) {
                if (!empty($value)) {
                    $this->filters[$col] = $value;
                }
            }
        }
    }
    
    /**
     * Render table HTML
     * 
     * @return string HTML output
     */
    public function render(): string
    {
        if (!$this->validate()) {
            return '<div class="alert alert-danger">Invalid table configuration</div>';
        }
        
        // Load data
        $this->loadData();
        
        $tableId = $this->config['id'] ?? 'module-table';
        $title = $this->config['title'] ?? '';
        
        $html = '<div class="table-view-container" id="' . htmlspecialchars($tableId) . '">';
        
        // Header with title and actions
        if ($title || !empty($this->config['actions'])) {
            $html .= '<div class="table-header d-flex justify-content-between align-items-center mb-3">';
            
            if ($title) {
                $html .= '<h3>' . htmlspecialchars($title) . '</h3>';
            }
            
            // Action buttons
            if (!empty($this->config['actions'])) {
                $html .= '<div class="table-actions">';
                foreach ($this->config['actions'] as $action) {
                    if ($this->canPerformAction($action)) {
                        $html .= $this->renderActionButton($action);
                    }
                }
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        // Filter form
        if (!empty($this->config['filterable'])) {
            $html .= $this->renderFilters();
        }
        
        // Export buttons
        if (!empty($this->config['exportFormats'])) {
            $html .= $this->renderExportButtons();
        }
        
        // Table
        $html .= '<div class="table-responsive">';
        $html .= $this->renderTable();
        $html .= '</div>';
        
        // Pagination
        if ($this->totalRecords > $this->perPage) {
            $html .= $this->renderPagination();
        }
        
        $html .= '</div>';
        
        // Add JavaScript for sorting/filtering
        $html .= $this->renderScript();
        
        return $html;
    }
    
    /**
     * Load data from database
     */
    private function loadData(): void
    {
        $source = $this->config['dataSource'] ?? [];
        $table = $source['table'] ?? '';
        $columns = $this->config['columns'] ?? [];
        
        if (!$table) {
            $this->data = [];
            return;
        }
        
        // Build SELECT clause
        $selectCols = [];
        foreach ($columns as $col) {
            $selectCols[] = $col['field'];
        }
        
        $sql = "SELECT " . implode(', ', $selectCols) . " FROM {$table}";
        $params = [];
        
        // WHERE clause
        $whereClauses = [];
        
        // Multi-tenancy
        $whereClauses[] = "tenant_id = ?";
        $params[] = $_SESSION['tenant_id'] ?? 'default';
        
        // Custom where from config
        if (!empty($source['where'])) {
            $whereClauses[] = "(" . $source['where'] . ")";
        }
        
        // Filters
        foreach ($this->filters as $col => $value) {
            $whereClauses[] = "{$col} LIKE ?";
            $params[] = "%{$value}%";
        }
        
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        // Count total (before pagination)
        $countSql = "SELECT COUNT(*) as total FROM {$table}";
        if (!empty($whereClauses)) {
            $countSql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $this->totalRecords = $countStmt->fetch()['total'] ?? 0;
        
        // ORDER BY
        if ($this->sortColumn) {
            $sql .= " ORDER BY {$this->sortColumn} {$this->sortDirection}";
        }
        
        // LIMIT for pagination
        $offset = ($this->page - 1) * $this->perPage;
        $sql .= " LIMIT {$this->perPage} OFFSET {$offset}";
        
        // Execute query
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $this->data = $stmt->fetchAll();
    }
    
    /**
     * Render table element
     * 
     * @return string Table HTML
     */
    private function renderTable(): string
    {
        if (empty($this->data)) {
            return $this->renderEmptyState();
        }
        
        $columns = $this->config['columns'] ?? [];
        
        $html = '<table class="table table-striped table-hover">';
        
        // Header
        $html .= '<thead>';
        $html .= '<tr>';
        
        foreach ($columns as $col) {
            $field = $col['field'];
            $label = $col['label'] ?? ucfirst($field);
            $sortable = $col['sortable'] ?? true;
            
            $html .= '<th>';
            
            if ($sortable) {
                $sortIcon = '';
                if ($this->sortColumn === $field) {
                    $sortIcon = $this->sortDirection === 'ASC' ? ' ▲' : ' ▼';
                }
                
                $newDir = ($this->sortColumn === $field && $this->sortDirection === 'ASC') ? 'DESC' : 'ASC';
                $sortUrl = $this->buildUrl(['sort' => $field, 'dir' => $newDir, 'page' => 1]);
                
                $html .= '<a href="' . htmlspecialchars($sortUrl) . '" class="text-decoration-none text-dark">';
                $html .= htmlspecialchars($label) . $sortIcon;
                $html .= '</a>';
            } else {
                $html .= htmlspecialchars($label);
            }
            
            $html .= '</th>';
        }
        
        // Row actions column
        if (!empty($this->config['rowActions'])) {
            $html .= '<th>Actions</th>';
        }
        
        $html .= '</tr>';
        $html .= '</thead>';
        
        // Body
        $html .= '<tbody>';
        
        foreach ($this->data as $row) {
            $html .= '<tr>';
            
            foreach ($columns as $col) {
                $field = $col['field'];
                $value = $row[$field] ?? '';
                
                // Handle PII masking
                if (!empty($col['pii']) && !$this->canViewPII()) {
                    $value = $this->maskPII($value, $col['pii']);
                }
                
                // Format value
                $value = $this->formatValue($value, $col);
                
                $html .= '<td>' . $value . '</td>';
            }
            
            // Row actions
            if (!empty($this->config['rowActions'])) {
                $html .= '<td>' . $this->renderRowActions($row) . '</td>';
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Render empty state
     * 
     * @return string HTML
     */
    private function renderEmptyState(): string
    {
        $message = $this->config['emptyMessage'] ?? 'No records found.';
        
        return '<div class="alert alert-info text-center py-5">' . 
               '<p class="mb-0">' . htmlspecialchars($message) . '</p>' .
               '</div>';
    }
    
    /**
     * Format cell value based on column config
     * 
     * @param mixed $value Raw value
     * @param array $col Column config
     * @return string Formatted value
     */
    private function formatValue($value, array $col): string
    {
        if ($value === null || $value === '') {
            return '<span class="text-muted">—</span>';
        }
        
        $format = $col['format'] ?? 'text';
        
        switch ($format) {
            case 'date':
                $date = date('m/d/Y', strtotime($value));
                return htmlspecialchars($date);
            
            case 'datetime':
                $date = date('m/d/Y g:i A', strtotime($value));
                return htmlspecialchars($date);
            
            case 'currency':
                return '$' . number_format((float)$value, 2);
            
            case 'number':
                return number_format((float)$value);
            
            case 'boolean':
                return $value ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>';
            
            case 'link':
                return '<a href="' . htmlspecialchars($value) . '" target="_blank">' . htmlspecialchars($value) . '</a>';
            
            case 'email':
                return '<a href="mailto:' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</a>';
            
            case 'badge':
                $badgeClass = $col['badgeClass'] ?? 'bg-primary';
                return '<span class="badge ' . htmlspecialchars($badgeClass) . '">' . htmlspecialchars($value) . '</span>';
            
            case 'text':
            default:
                $maxLength = $col['maxLength'] ?? null;
                if ($maxLength && strlen($value) > $maxLength) {
                    $value = substr($value, 0, $maxLength) . '...';
                }
                return htmlspecialchars($value);
        }
    }
    
    /**
     * Mask PII data
     * 
     * @param mixed $value Value to mask
     * @param string $piiType Type of PII
     * @return string Masked value
     */
    private function maskPII($value, string $piiType): string
    {
        switch ($piiType) {
            case 'ssn':
                return 'XXX-XX-' . substr($value, -4);
            
            case 'email':
                $parts = explode('@', $value);
                if (count($parts) === 2) {
                    $masked = substr($parts[0], 0, 2) . '***@' . $parts[1];
                    return $masked;
                }
                return '***';
            
            case 'phone':
                return '***-***-' . substr($value, -4);
            
            default:
                return '***';
        }
    }
    
    /**
     * Check if user can view PII
     * 
     * @return bool
     */
    private function canViewPII(): bool
    {
        $requiredRole = $this->config['piiViewRole'] ?? 'admin';
        return Auth::hasRole($requiredRole);
    }
    
    /**
     * Render row action buttons
     * 
     * @param array $row Row data
     * @return string HTML
     */
    private function renderRowActions(array $row): string
    {
        $html = '<div class="btn-group btn-group-sm">';
        
        foreach ($this->config['rowActions'] as $action) {
            if (!$this->canPerformAction($action)) {
                continue;
            }
            
            $label = $action['label'] ?? 'Action';
            $url = $this->buildActionUrl($action, $row);
            $class = $action['class'] ?? 'btn-secondary';
            $icon = $action['icon'] ?? '';
            
            $html .= '<a href="' . htmlspecialchars($url) . '" ';
            $html .= 'class="btn ' . htmlspecialchars($class) . '" ';
            
            if (!empty($action['confirm'])) {
                $confirmMsg = str_replace('{value}', $row[$action['confirmField'] ?? 'id'], $action['confirm']);
                $html .= 'onclick="return confirm(\'' . addslashes($confirmMsg) . '\')" ';
            }
            
            $html .= '>';
            
            if ($icon) {
                $html .= '<i class="' . htmlspecialchars($icon) . '"></i> ';
            }
            
            $html .= htmlspecialchars($label);
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Build action URL with row data
     * 
     * @param array $action Action config
     * @param array $row Row data
     * @return string URL
     */
    private function buildActionUrl(array $action, array $row): string
    {
        $url = $action['url'] ?? '#';
        
        // Replace placeholders with row values
        foreach ($row as $key => $value) {
            $url = str_replace('{' . $key . '}', urlencode($value), $url);
        }
        
        return $url;
    }
    
    /**
     * Check if user can perform action
     * 
     * @param array $action Action config
     * @return bool
     */
    private function canPerformAction(array $action): bool
    {
        if (empty($action['requiredRole'])) {
            return true;
        }
        
        return Auth::hasRole($action['requiredRole']);
    }
    
    /**
     * Render action button
     * 
     * @param array $action Action config
     * @return string HTML
     */
    private function renderActionButton(array $action): string
    {
        $label = $action['label'] ?? 'Action';
        $url = $action['url'] ?? '#';
        $class = $action['class'] ?? 'btn-primary';
        $icon = $action['icon'] ?? '';
        
        $html = '<a href="' . htmlspecialchars($url) . '" ';
        $html .= 'class="btn btn-sm ' . htmlspecialchars($class) . '">';
        
        if ($icon) {
            $html .= '<i class="' . htmlspecialchars($icon) . '"></i> ';
        }
        
        $html .= htmlspecialchars($label);
        $html .= '</a>';
        
        return $html;
    }
    
    /**
     * Render filter form
     * 
     * @return string HTML
     */
    private function renderFilters(): string
    {
        $columns = $this->config['columns'] ?? [];
        $filterableColumns = array_filter($columns, fn($col) => !empty($col['filterable']));
        
        if (empty($filterableColumns)) {
            return '';
        }
        
        $html = '<form method="GET" class="table-filters mb-3">';
        $html .= '<div class="row g-2">';
        
        foreach ($filterableColumns as $col) {
            $field = $col['field'];
            $label = $col['label'] ?? ucfirst($field);
            $value = $this->filters[$field] ?? '';
            
            $html .= '<div class="col-md-3">';
            $html .= '<input type="text" ';
            $html .= 'name="filter[' . htmlspecialchars($field) . ']" ';
            $html .= 'class="form-control form-control-sm" ';
            $html .= 'placeholder="Filter by ' . htmlspecialchars($label) . '" ';
            $html .= 'value="' . htmlspecialchars($value) . '">';
            $html .= '</div>';
        }
        
        // Preserve other query params
        foreach ($_GET as $key => $value) {
            if ($key !== 'filter' && $key !== 'page') {
                $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
            }
        }
        
        $html .= '<div class="col-md-3">';
        $html .= '<button type="submit" class="btn btn-sm btn-primary me-2">Filter</button>';
        $html .= '<a href="' . htmlspecialchars($this->buildUrl([])) . '" class="btn btn-sm btn-secondary">Clear</a>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</form>';
        
        return $html;
    }
    
    /**
     * Render export buttons
     * 
     * @return string HTML
     */
    private function renderExportButtons(): string
    {
        $formats = $this->config['exportFormats'] ?? [];
        
        if (empty($formats)) {
            return '';
        }
        
        $html = '<div class="export-buttons mb-3">';
        
        foreach ($formats as $format) {
            $label = strtoupper($format);
            $exportUrl = $this->buildUrl(['export' => $format]);
            
            $html .= '<a href="' . htmlspecialchars($exportUrl) . '" ';
            $html .= 'class="btn btn-sm btn-outline-secondary me-2">';
            $html .= '<i class="bi bi-download"></i> Export ' . htmlspecialchars($label);
            $html .= '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render pagination
     * 
     * @return string HTML
     */
    private function renderPagination(): string
    {
        $totalPages = ceil($this->totalRecords / $this->perPage);
        
        if ($totalPages <= 1) {
            return '';
        }
        
        $html = '<nav aria-label="Table pagination" class="mt-3">';
        $html .= '<ul class="pagination justify-content-center">';
        
        // Previous
        $prevDisabled = ($this->page <= 1) ? 'disabled' : '';
        $html .= '<li class="page-item ' . $prevDisabled . '">';
        if ($this->page > 1) {
            $prevUrl = $this->buildUrl(['page' => $this->page - 1]);
            $html .= '<a class="page-link" href="' . htmlspecialchars($prevUrl) . '">Previous</a>';
        } else {
            $html .= '<span class="page-link">Previous</span>';
        }
        $html .= '</li>';
        
        // Page numbers (show current, +/- 2 pages)
        $startPage = max(1, $this->page - 2);
        $endPage = min($totalPages, $this->page + 2);
        
        if ($startPage > 1) {
            $pageUrl = $this->buildUrl(['page' => 1]);
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl) . '">1</a></li>';
            if ($startPage > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            $active = ($i === $this->page) ? 'active' : '';
            $pageUrl = $this->buildUrl(['page' => $i]);
            
            $html .= '<li class="page-item ' . $active . '">';
            $html .= '<a class="page-link" href="' . htmlspecialchars($pageUrl) . '">' . $i . '</a>';
            $html .= '</li>';
        }
        
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $pageUrl = $this->buildUrl(['page' => $totalPages]);
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl) . '">' . $totalPages . '</a></li>';
        }
        
        // Next
        $nextDisabled = ($this->page >= $totalPages) ? 'disabled' : '';
        $html .= '<li class="page-item ' . $nextDisabled . '">';
        if ($this->page < $totalPages) {
            $nextUrl = $this->buildUrl(['page' => $this->page + 1]);
            $html .= '<a class="page-link" href="' . htmlspecialchars($nextUrl) . '">Next</a>';
        } else {
            $html .= '<span class="page-link">Next</span>';
        }
        $html .= '</li>';
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Build URL with query parameters
     * 
     * @param array $params Additional parameters
     * @return string URL
     */
    private function buildUrl(array $params = []): string
    {
        $baseUrl = $_SERVER['PHP_SELF'] ?? '';
        $queryParams = $_GET;
        
        // Merge with new params
        foreach ($params as $key => $value) {
            if ($value === null) {
                unset($queryParams[$key]);
            } else {
                $queryParams[$key] = $value;
            }
        }
        
        if (empty($queryParams)) {
            return $baseUrl;
        }
        
        return $baseUrl . '?' . http_build_query($queryParams);
    }
    
    /**
     * Render JavaScript for table interactions
     * 
     * @return string JavaScript code
     */
    private function renderScript(): string
    {
        $tableId = $this->config['id'] ?? 'module-table';
        
        $script = '<script>';
        $script .= '(function() {';
        $script .= '  const container = document.getElementById("' . addslashes($tableId) . '");';
        $script .= '  if (!container) return;';
        $script .= '  // Add any custom JavaScript here';
        $script .= '})();';
        $script .= '</script>';
        
        return $script;
    }
    
    /**
     * Handle table actions (export, etc.)
     * 
     * @param array $data Request data
     * @return array Result
     */
    public function handle(array $data): array
    {
        // Handle export
        if (!empty($data['export'])) {
            return $this->handleExport($data['export']);
        }
        
        return [
            'success' => false,
            'error' => 'Invalid action'
        ];
    }
    
    /**
     * Handle data export
     * 
     * @param string $format Export format (csv, xlsx, pdf)
     * @return array Result
     */
    private function handleExport(string $format): array
    {
        // Load all data (no pagination)
        $this->perPage = PHP_INT_MAX;
        $this->loadData();
        
        $filename = ($this->config['exportFilename'] ?? 'export') . '_' . date('Y-m-d');
        
        switch ($format) {
            case 'csv':
                return $this->exportCSV($filename);
            
            case 'xlsx':
                return $this->exportXLSX($filename);
            
            case 'pdf':
                return $this->exportPDF($filename);
            
            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported export format'
                ];
        }
    }
    
    /**
     * Export data as CSV
     * 
     * @param string $filename Base filename
     * @return array Result
     */
    private function exportCSV(string $filename): array
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header row
        $columns = $this->config['columns'] ?? [];
        $headers = array_map(fn($col) => $col['label'] ?? $col['field'], $columns);
        fputcsv($output, $headers);
        
        // Data rows
        foreach ($this->data as $row) {
            $rowData = [];
            foreach ($columns as $col) {
                $field = $col['field'];
                $value = $row[$field] ?? '';
                
                // Don't export PII if user can't view it
                if (!empty($col['pii']) && !$this->canViewPII()) {
                    $value = '[REDACTED]';
                }
                
                $rowData[] = $value;
            }
            fputcsv($output, $rowData);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data as XLSX
     * 
     * @param string $filename Base filename
     * @return array Result
     */
    private function exportXLSX(string $filename): array
    {
        // TODO: Implement using PhpSpreadsheet
        // Placeholder for now
        return $this->exportCSV($filename);
    }
    
    /**
     * Export data as PDF
     * 
     * @param string $filename Base filename
     * @return array Result
     */
    private function exportPDF(string $filename): array
    {
        // TODO: Implement using mPDF
        // Placeholder for now
        return $this->exportCSV($filename);
    }
    
    /**
     * Get module configuration
     * 
     * @return array Configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Validate module configuration
     * 
     * @return bool True if valid
     */
    public function validate(): bool
    {
        // Must have dataSource
        if (empty($this->config['dataSource']) || empty($this->config['dataSource']['table'])) {
            return false;
        }
        
        // Must have columns
        if (empty($this->config['columns']) || !is_array($this->config['columns'])) {
            return false;
        }
        
        // Validate each column
        foreach ($this->config['columns'] as $col) {
            if (empty($col['field'])) {
                return false;
            }
        }
        
        return true;
    }
}
