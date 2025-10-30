<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * DashboardRenderer - Widget-based dashboard for overview metrics
 * 
 * Implements Dashboard module type from MODULE_CATALOG_V2.md [DSH-R01 through DSH-R08]
 * 
 * Use cases:
 * - Executive summary dashboards
 * - Real-time metrics overview
 * - Status at-a-glance views
 * - Key performance indicators (KPIs)
 * 
 * Features:
 * - Multiple widget types (stat, chart, table, list)
 * - Responsive grid layout
 * - Auto-refresh support
 * - Drill-down links
 * - Role-based widget visibility
 * - Multi-tenant isolation
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class DashboardRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();
    }
    
    /**
     * Render dashboard with widgets
     * 
     * @return string HTML output
     */
    public function render(): string
    {
        if (!$this->validate()) {
            return '<div class="alert alert-danger">Invalid dashboard configuration</div>';
        }
        
        $html = '<div class="dashboard-container">';
        
        // Page header
        $title = $this->config['displayName'] ?? 'Dashboard';
        $description = $this->config['description'] ?? '';
        
        $html .= '<div class="mb-4">';
        $html .= '<h2>' . htmlspecialchars($title) . '</h2>';
        if ($description) {
            $html .= '<p class="text-muted">' . htmlspecialchars($description) . '</p>';
        }
        $html .= '</div>';
        
        // Render widgets in grid
        $widgets = $this->config['widgets'] ?? [];
        $html .= '<div class="row g-4">';
        
        foreach ($widgets as $index => $widget) {
            // [DSH-R05] Check role-based visibility
            if (!$this->canViewWidget($widget)) {
                continue;
            }
            
            $html .= $this->renderWidget($widget, $index);
        }
        
        $html .= '</div>'; // row
        $html .= '</div>'; // dashboard-container
        
        // Auto-refresh if configured
        if (!empty($this->config['autoRefresh'])) {
            $interval = (int)$this->config['autoRefresh'];
            $html .= '<script>';
            $html .= "setTimeout(() => location.reload(), {$interval} * 1000);";
            $html .= '</script>';
        }
        
        return $html;
    }
    
    /**
     * Check if current user can view widget
     */
    private function canViewWidget(array $widget): bool
    {
        if (empty($widget['visibleToRoles'])) {
            return true; // No restriction
        }
        
        $userRole = Auth::getEffectiveRole();
        return in_array($userRole, $widget['visibleToRoles']);
    }
    
    /**
     * Render individual widget
     */
    private function renderWidget(array $widget, int $index): string
    {
        $type = $widget['type'] ?? 'stat';
        $colSize = $widget['width'] ?? 'col-md-3';
        
        $html = '<div class="' . htmlspecialchars($colSize) . '">';
        
        switch ($type) {
            case 'stat':
                $html .= $this->renderStatWidget($widget);
                break;
            case 'chart':
                $html .= $this->renderChartWidget($widget, $index);
                break;
            case 'table':
                $html .= $this->renderTableWidget($widget);
                break;
            case 'list':
                $html .= $this->renderListWidget($widget);
                break;
            default:
                $html .= '<div class="alert alert-warning">Unknown widget type: ' 
                       . htmlspecialchars($type) . '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render stat widget (single number metric)
     */
    private function renderStatWidget(array $widget): string
    {
        $title = $widget['title'] ?? 'Metric';
        $query = $widget['query'] ?? '';
        $icon = $widget['icon'] ?? 'bi-graph-up';
        $color = $widget['color'] ?? 'primary';
        $link = $widget['link'] ?? null;
        
        try {
            $value = $this->executeQuery($query);
            $formattedValue = $this->formatValue($value, $widget['format'] ?? null);
        } catch (Exception $e) {
            $formattedValue = 'Error';
        }
        
        $html = '<div class="card h-100">';
        $html .= '<div class="card-body">';
        $html .= '<div class="d-flex justify-content-between align-items-center mb-3">';
        $html .= '<h6 class="text-muted mb-0">' . htmlspecialchars($title) . '</h6>';
        $html .= '<i class="bi ' . htmlspecialchars($icon) . ' fs-3 text-' . htmlspecialchars($color) . '"></i>';
        $html .= '</div>';
        $html .= '<h2 class="mb-0">' . htmlspecialchars($formattedValue) . '</h2>';
        
        if ($link) {
            $html .= '<a href="' . htmlspecialchars($link) . '" class="text-decoration-none small">';
            $html .= 'View details <i class="bi bi-arrow-right"></i></a>';
        }
        
        $html .= '</div>'; // card-body
        $html .= '</div>'; // card
        
        return $html;
    }
    
    /**
     * Render chart widget (mini chart)
     */
    private function renderChartWidget(array $widget, int $index): string
    {
        $title = $widget['title'] ?? 'Chart';
        $chartId = 'dashboard-chart-' . $index;
        
        $html = '<div class="card h-100">';
        $html .= '<div class="card-header">';
        $html .= '<h6 class="mb-0">' . htmlspecialchars($title) . '</h6>';
        $html .= '</div>';
        $html .= '<div class="card-body p-2">';
        
        try {
            $data = $this->loadWidgetChartData($widget);
            $html .= '<canvas id="' . $chartId . '" height="150"></canvas>';
            $html .= '<script>';
            $html .= 'window.dashboardChart_' . $index . ' = ' . json_encode($data) . ';';
            $html .= 'window.dashboardChartConfig_' . $index . ' = ' . json_encode([
                'type' => $widget['chartType'] ?? 'line'
            ]) . ';';
            $html .= '</script>';
        } catch (Exception $e) {
            $html .= '<div class="alert alert-sm alert-danger">Error loading chart</div>';
        }
        
        $html .= '</div>'; // card-body
        $html .= '</div>'; // card
        
        return $html;
    }
    
    /**
     * Render table widget (mini table)
     */
    private function renderTableWidget(array $widget): string
    {
        $title = $widget['title'] ?? 'Table';
        $query = $widget['query'] ?? '';
        $columns = $widget['columns'] ?? [];
        $limit = $widget['limit'] ?? 5;
        
        $html = '<div class="card h-100">';
        $html .= '<div class="card-header">';
        $html .= '<h6 class="mb-0">' . htmlspecialchars($title) . '</h6>';
        $html .= '</div>';
        $html .= '<div class="card-body p-0">';
        
        try {
            $rows = $this->executeQuery($query, true, $limit);
            
            if (empty($rows)) {
                $html .= '<div class="p-3 text-muted text-center">No data</div>';
            } else {
                $html .= '<div class="table-responsive">';
                $html .= '<table class="table table-sm table-hover mb-0">';
                
                // Header
                if (!empty($columns)) {
                    $html .= '<thead><tr>';
                    foreach ($columns as $col) {
                        $html .= '<th>' . htmlspecialchars($col['label'] ?? $col) . '</th>';
                    }
                    $html .= '</tr></thead>';
                }
                
                // Body
                $html .= '<tbody>';
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    if (!empty($columns)) {
                        foreach ($columns as $col) {
                            $field = $col['field'] ?? $col;
                            $value = $row[$field] ?? '';
                            $html .= '<td>' . htmlspecialchars($value) . '</td>';
                        }
                    } else {
                        foreach ($row as $value) {
                            $html .= '<td>' . htmlspecialchars($value) . '</td>';
                        }
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
            }
        } catch (Exception $e) {
            $html .= '<div class="alert alert-sm alert-danger m-2">Error loading data</div>';
        }
        
        $html .= '</div>'; // card-body
        $html .= '</div>'; // card
        
        return $html;
    }
    
    /**
     * Render list widget (bullet list)
     */
    private function renderListWidget(array $widget): string
    {
        $title = $widget['title'] ?? 'List';
        $query = $widget['query'] ?? '';
        $limit = $widget['limit'] ?? 5;
        
        $html = '<div class="card h-100">';
        $html .= '<div class="card-header">';
        $html .= '<h6 class="mb-0">' . htmlspecialchars($title) . '</h6>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        
        try {
            $rows = $this->executeQuery($query, true, $limit);
            
            if (empty($rows)) {
                $html .= '<div class="text-muted text-center">No items</div>';
            } else {
                $html .= '<ul class="list-unstyled mb-0">';
                foreach ($rows as $row) {
                    // Use first column as display value
                    $value = reset($row);
                    $html .= '<li class="py-1"><i class="bi bi-dot"></i> ' 
                           . htmlspecialchars($value) . '</li>';
                }
                $html .= '</ul>';
            }
        } catch (Exception $e) {
            $html .= '<div class="alert alert-sm alert-danger">Error loading list</div>';
        }
        
        $html .= '</div>'; // card-body
        $html .= '</div>'; // card
        
        return $html;
    }
    
    /**
     * Execute query and return single value or rows
     */
    private function executeQuery(string $query, bool $fetchAll = false, int $limit = 100)
    {
        // Add tenant isolation
        $tenantId = $_SESSION['tenant_id'] ?? 'default';
        $query = str_replace('{tenant_id}', $tenantId, $query);
        
        // Add limit if fetching all
        if ($fetchAll && stripos($query, 'LIMIT') === false) {
            $query .= " LIMIT $limit";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        if ($fetchAll) {
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $row = $stmt->fetch(\PDO::FETCH_NUM);
            return $row[0] ?? 0;
        }
    }
    
    /**
     * Load chart data for widget
     */
    private function loadWidgetChartData(array $widget): array
    {
        $query = $widget['query'] ?? '';
        $rows = $this->executeQuery($query, true, 10);
        
        $labels = [];
        $values = [];
        
        foreach ($rows as $row) {
            $labels[] = reset($row);
            $values[] = (float)next($row);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => $widget['title'] ?? 'Data',
                'data' => $values,
                'backgroundColor' => 'rgba(75, 192, 192, 0.5)',
                'borderColor' => 'rgba(75, 192, 192, 1)',
                'borderWidth' => 2
            ]]
        ];
    }
    
    /**
     * Format value based on format type
     */
    private function formatValue($value, ?string $format): string
    {
        if ($format === 'currency') {
            return '$' . number_format((float)$value, 2);
        } elseif ($format === 'number') {
            return number_format((float)$value);
        } elseif ($format === 'percent') {
            return number_format((float)$value, 1) . '%';
        }
        
        return (string)$value;
    }
    
    /**
     * Handle requests (dashboards are read-only)
     * 
     * @param array $data Input data
     * @return array Result
     */
    public function handle(array $data): array
    {
        return [
            'success' => false,
            'message' => 'Dashboard module is read-only'
        ];
    }
    
    /**
     * Get module configuration
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Validate module configuration
     * 
     * @return bool
     */
    public function validate(): bool
    {
        // Must have widgets
        if (empty($this->config['widgets'])) {
            return false;
        }
        
        // Each widget must have type and title
        foreach ($this->config['widgets'] as $widget) {
            if (empty($widget['type']) || empty($widget['title'])) {
                return false;
            }
            
            // Valid widget types
            $validTypes = ['stat', 'chart', 'table', 'list'];
            if (!in_array($widget['type'], $validTypes)) {
                return false;
            }
            
            // Must have query
            if (empty($widget['query'])) {
                return false;
            }
        }
        
        return true;
    }
}
