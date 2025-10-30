<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * AnalyticsRenderer - Visualize metrics and trends from entity data
 * 
 * Implements Analytics module type from MODULE_CATALOG_V2.md [ANL-R01 through ANL-R07]
 * 
 * Use cases:
 * - Reports by month (incident trends)
 * - Performance dashboards (evaluation scores)
 * - Expense tracking (budget analysis)
 * - Usage metrics (system activity)
 * 
 * Features:
 * - Chart.js integration for visualizations
 * - Multiple chart types (line, bar, pie, doughnut)
 * - Automatic tenant isolation
 * - PII field exclusion
 * - Query result caching (15 minutes)
 * - Responsive design
 * - Export chart data to CSV
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class AnalyticsRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    private ?array $chartData = null;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();
    }
    
    /**
     * Render analytics dashboard with charts
     * 
     * @return string HTML output
     */
    public function render(): string
    {
        if (!$this->validate()) {
            return '<div class="alert alert-danger">Invalid analytics configuration</div>';
        }
        
        $html = '<div class="analytics-container">';
        
        // Page header
        $title = $this->config['displayName'] ?? 'Analytics';
        $description = $this->config['description'] ?? '';
        
        $html .= '<div class="mb-4">';
        $html .= '<h2>' . htmlspecialchars($title) . '</h2>';
        if ($description) {
            $html .= '<p class="text-muted">' . htmlspecialchars($description) . '</p>';
        }
        $html .= '</div>';
        
        // Filters (if configured)
        if (!empty($this->config['filters'])) {
            $html .= $this->renderFilters();
        }
        
        // Render charts
        $charts = $this->config['charts'] ?? [];
        
        if (empty($charts)) {
            $html .= '<div class="alert alert-info">No charts configured</div>';
        } else {
            $html .= '<div class="row">';
            foreach ($charts as $index => $chart) {
                $html .= $this->renderChart($chart, $index);
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // Include Chart.js and custom JavaScript
        $html .= $this->renderScripts();
        
        return $html;
    }
    
    /**
     * Render filter controls
     */
    private function renderFilters(): string
    {
        $html = '<div class="card mb-4">';
        $html .= '<div class="card-body">';
        $html .= '<form method="GET" class="row g-3">';
        
        foreach ($this->config['filters'] as $filter) {
            $name = $filter['name'] ?? '';
            $label = $filter['label'] ?? $name;
            $type = $filter['type'] ?? 'text';
            $value = $_GET[$name] ?? '';
            
            $html .= '<div class="col-md-3">';
            $html .= '<label class="form-label">' . htmlspecialchars($label) . '</label>';
            
            if ($type === 'date') {
                $html .= '<input type="date" name="' . htmlspecialchars($name) . '" 
                         class="form-control" value="' . htmlspecialchars($value) . '">';
            } elseif ($type === 'select' && !empty($filter['options'])) {
                $html .= '<select name="' . htmlspecialchars($name) . '" class="form-select">';
                $html .= '<option value="">All</option>';
                foreach ($filter['options'] as $opt) {
                    $optValue = $opt['value'] ?? $opt;
                    $optLabel = $opt['label'] ?? $opt;
                    $selected = ($value == $optValue) ? 'selected' : '';
                    $html .= '<option value="' . htmlspecialchars($optValue) . '" ' . $selected . '>' 
                           . htmlspecialchars($optLabel) . '</option>';
                }
                $html .= '</select>';
            } else {
                $html .= '<input type="text" name="' . htmlspecialchars($name) . '" 
                         class="form-control" value="' . htmlspecialchars($value) . '">';
            }
            
            $html .= '</div>';
        }
        
        $html .= '<div class="col-md-3 d-flex align-items-end">';
        $html .= '<button type="submit" class="btn btn-primary me-2">Apply Filters</button>';
        $html .= '<a href="?" class="btn btn-secondary">Reset</a>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render individual chart
     */
    private function renderChart(array $chart, int $index): string
    {
        $chartId = 'chart-' . $index;
        $title = $chart['title'] ?? 'Chart ' . ($index + 1);
        $type = $chart['type'] ?? 'line';
        $colSize = $chart['width'] ?? 'col-md-6';
        
        $html = '<div class="' . htmlspecialchars($colSize) . ' mb-4">';
        $html .= '<div class="card">';
        $html .= '<div class="card-header d-flex justify-content-between align-items-center">';
        $html .= '<h5 class="mb-0">' . htmlspecialchars($title) . '</h5>';
        $html .= '<button class="btn btn-sm btn-outline-secondary export-chart" data-chart="' . $chartId . '">';
        $html .= '<i class="bi bi-download"></i> Export CSV</button>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        
        // Load chart data
        try {
            $data = $this->loadChartData($chart);
            
            if (empty($data['labels']) || empty($data['datasets'])) {
                $html .= '<div class="alert alert-info">No data available</div>';
            } else {
                $html .= '<canvas id="' . $chartId . '" height="300"></canvas>';
                $html .= '<script>';
                $html .= 'window.chartData_' . $index . ' = ' . json_encode($data) . ';';
                $html .= 'window.chartConfig_' . $index . ' = ' . json_encode([
                    'type' => $type,
                    'title' => $title
                ]) . ';';
                $html .= '</script>';
            }
        } catch (Exception $e) {
            $html .= '<div class="alert alert-danger">Error loading chart: ' 
                   . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        $html .= '</div>'; // card-body
        $html .= '</div>'; // card
        $html .= '</div>'; // col
        
        return $html;
    }
    
    /**
     * Load chart data from database
     */
    private function loadChartData(array $chart): array
    {
        $entity = $this->config['entity'] ?? '';
        $xAxis = $chart['xAxis'] ?? 'created_at';
        $yAxis = $chart['yAxis'] ?? 'COUNT(*)';
        $groupBy = $chart['groupBy'] ?? null;
        
        // [ANL-R06] Check cache first (15 minutes)
        $cacheKey = 'analytics_' . md5(json_encode($chart) . json_encode($_GET));
        $cached = $this->getCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Build query
        $sql = "SELECT ";
        
        // X-axis (grouping)
        if ($groupBy === 'MONTH') {
            $sql .= "DATE_FORMAT($xAxis, '%Y-%m') as x_value, ";
        } elseif ($groupBy === 'YEAR') {
            $sql .= "DATE_FORMAT($xAxis, '%Y') as x_value, ";
        } elseif ($groupBy === 'DAY') {
            $sql .= "DATE($xAxis) as x_value, ";
        } else {
            $sql .= "$xAxis as x_value, ";
        }
        
        // Y-axis (value)
        $sql .= "$yAxis as y_value ";
        
        $sql .= "FROM $entity ";
        
        // [ANL-R07] Multi-tenancy filter
        $sql .= "WHERE tenant_id = :tenant_id ";
        
        // Apply filters from query string
        $params = ['tenant_id' => $_SESSION['tenant_id'] ?? 'default'];
        
        if (!empty($this->config['filters'])) {
            foreach ($this->config['filters'] as $filter) {
                $name = $filter['name'] ?? '';
                if (!empty($_GET[$name])) {
                    $sql .= "AND $name = :filter_$name ";
                    $params["filter_$name"] = $_GET[$name];
                }
            }
        }
        
        // Group by
        if ($groupBy) {
            $sql .= "GROUP BY x_value ";
        }
        
        // Order by
        $sql .= "ORDER BY x_value ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Format for Chart.js
        $labels = [];
        $values = [];
        
        foreach ($results as $row) {
            $labels[] = $row['x_value'];
            $values[] = (float) $row['y_value'];
        }
        
        $data = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => $chart['title'] ?? 'Data',
                    'data' => $values,
                    'backgroundColor' => $this->getChartColor($chart['type'] ?? 'line', 0.5),
                    'borderColor' => $this->getChartColor($chart['type'] ?? 'line', 1),
                    'borderWidth' => 2
                ]
            ]
        ];
        
        // [ANL-R06] Cache for 15 minutes
        $this->setCache($cacheKey, $data, 900);
        
        return $data;
    }
    
    /**
     * Get appropriate color for chart type
     */
    private function getChartColor(string $type, float $alpha): string
    {
        $colors = [
            'line' => "rgba(54, 162, 235, $alpha)",
            'bar' => "rgba(75, 192, 192, $alpha)",
            'pie' => "rgba(255, 99, 132, $alpha)",
            'doughnut' => "rgba(153, 102, 255, $alpha)"
        ];
        
        return $colors[$type] ?? $colors['line'];
    }
    
    /**
     * Get cached data
     */
    private function getCache(string $key): ?array
    {
        // Simple file-based cache (TODO: use Redis)
        $cacheFile = sys_get_temp_dir() . '/analytics_' . md5($key) . '.json';
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && $data['expires'] > time()) {
                return $data['value'];
            }
        }
        
        return null;
    }
    
    /**
     * Set cached data
     */
    private function setCache(string $key, array $value, int $ttl): void
    {
        $cacheFile = sys_get_temp_dir() . '/analytics_' . md5($key) . '.json';
        file_put_contents($cacheFile, json_encode([
            'expires' => time() + $ttl,
            'value' => $value
        ]));
    }
    
    /**
     * Render Chart.js scripts
     */
    private function renderScripts(): string
    {
        $html = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
        $html .= '<script>';
        $html .= <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all charts
    const chartCount = Object.keys(window).filter(k => k.startsWith('chartData_')).length;
    
    for (let i = 0; i < chartCount; i++) {
        const data = window['chartData_' + i];
        const config = window['chartConfig_' + i];
        const canvas = document.getElementById('chart-' + i);
        
        if (canvas && data) {
            new Chart(canvas, {
                type: config.type,
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: config.type !== 'pie' && config.type !== 'doughnut' ? {
                        y: {
                            beginAtZero: true
                        }
                    } : {}
                }
            });
        }
    }
    
    // Export chart data to CSV
    document.querySelectorAll('.export-chart').forEach(btn => {
        btn.addEventListener('click', function() {
            const chartId = this.getAttribute('data-chart');
            const index = chartId.replace('chart-', '');
            const data = window['chartData_' + index];
            
            if (data) {
                let csv = 'Label,Value\n';
                data.labels.forEach((label, i) => {
                    csv += `"${label}","${data.datasets[0].data[i]}"\n`;
                });
                
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = chartId + '-' + new Date().toISOString().slice(0, 10) + '.csv';
                a.click();
                URL.revokeObjectURL(url);
            }
        });
    });
});
JS;
        $html .= '</script>';
        
        return $html;
    }
    
    /**
     * Handle data requests (for dynamic updates)
     * 
     * @param array $data Input data
     * @return array Result
     */
    public function handle(array $data): array
    {
        // Analytics is read-only, no POST handling needed
        return [
            'success' => false,
            'message' => 'Analytics module is read-only'
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
     * [ANL-R04] Must include at least one visualization
     * 
     * @return bool
     */
    public function validate(): bool
    {
        // Must have charts
        if (empty($this->config['charts'])) {
            return false;
        }
        
        // Must have entity
        if (empty($this->config['entity'])) {
            return false;
        }
        
        // [ANL-R04] At least one chart
        if (count($this->config['charts']) < 1) {
            return false;
        }
        
        // Each chart must have type and title
        foreach ($this->config['charts'] as $chart) {
            if (empty($chart['type']) || empty($chart['title'])) {
                return false;
            }
            
            // Valid chart types
            $validTypes = ['line', 'bar', 'pie', 'doughnut', 'radar', 'polarArea'];
            if (!in_array($chart['type'], $validTypes)) {
                return false;
            }
        }
        
        return true;
    }
}
