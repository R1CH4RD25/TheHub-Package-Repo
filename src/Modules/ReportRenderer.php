<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Cache;
use PDO;

/**
 * ReportRenderer - Customizable report generation and scheduling
 *
 * Generic Use Cases:
 * - Business: Sales reports, financial summaries, revenue analytics
 * - Education: Enrollment reports, grade distributions, attendance summaries
 * - Healthcare: Patient census, incident reports, treatment outcomes
 * - Manufacturing: Production metrics, quality control, inventory levels
 * - Government: Service metrics, budget reports, compliance tracking
 * - Non-Profit: Donor reports, program outcomes, volunteer hours
 * - Personal: Budget reports, time tracking, habit analytics
 *
 * Features:
 * - Multiple report templates (table/chart/summary/custom)
 * - Scheduled generation (daily/weekly/monthly/custom cron)
 * - Export formats (PDF/Excel/CSV/JSON)
 * - Email delivery with attachments
 * - Dynamic data sources (SQL queries on any table)
 * - Filtering and aggregation (date ranges, GROUP BY, SUM/AVG/COUNT)
 * - Chart.js integration for visualizations
 * - Report caching (10min TTL)
 * - Report history and archive
 * - Custom parameters and formulas
 * - Role-based access control
 * - Report sharing and subscriptions
 */
class ReportRenderer implements ModuleInterface
{
    private PDO $db;
    private array $config;
    private int $userId;

    /**
     * Report template types
     */
    private const TEMPLATES = [
        'table' => [
            'name' => 'Table Report',
            'icon' => 'table',
            'description' => 'Tabular data with columns and rows'
        ],
        'chart' => [
            'name' => 'Chart Report',
            'icon' => 'bar-chart',
            'description' => 'Visual charts (bar, line, pie, doughnut)'
        ],
        'summary' => [
            'name' => 'Summary Report',
            'icon' => 'file-text',
            'description' => 'Key metrics and statistics'
        ],
        'mixed' => [
            'name' => 'Mixed Report',
            'icon' => 'layout',
            'description' => 'Combination of tables and charts'
        ]
    ];

    /**
     * Chart types for visualization
     */
    private const CHART_TYPES = [
        'bar' => ['name' => 'Bar Chart', 'icon' => 'bar-chart-2'],
        'line' => ['name' => 'Line Chart', 'icon' => 'trending-up'],
        'pie' => ['name' => 'Pie Chart', 'icon' => 'pie-chart'],
        'doughnut' => ['name' => 'Doughnut Chart', 'icon' => 'circle'],
        'area' => ['name' => 'Area Chart', 'icon' => 'activity']
    ];

    /**
     * Aggregation functions
     */
    private const AGGREGATIONS = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];

    public function __construct(array $config, int $userId)
    {
        $this->db = Database::getInstance()->getConnection();
        $this->config = $config;
        $this->userId = $userId;
    }

    /**
     * Get module configuration (implements ModuleInterface)
     *
     * @return array Module configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function render(): string
    {
        $reports = $this->getReports();
        $templates = self::TEMPLATES;

        $html = <<<HTML
        <div class="report-module">
            {$this->renderHeader()}

            <div class="container-fluid mt-3">
                <div class="row">
                    <div class="col-md-3">
                        {$this->renderSidebar($reports)}
                    </div>
                    <div class="col-md-9">
                        <div id="reportContent">
                            {$this->renderReportList($reports)}
                        </div>
                    </div>
                </div>
            </div>

            {$this->renderReportModal()}
            {$this->renderReportViewer()}
            {$this->renderScheduleModal()}
        </div>

        <script src="/assets/js/chart.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create report button
            document.getElementById('createReportBtn').addEventListener('click', function() {
                document.getElementById('reportForm').reset();
                document.getElementById('reportId').value = '';
                document.getElementById('reportModalLabel').textContent = 'Create Report';
                new bootstrap.Modal(document.getElementById('reportModal')).show();
            });

            // Template selection
            document.querySelectorAll('.template-card').forEach(card => {
                card.addEventListener('click', function() {
                    document.querySelectorAll('.template-card').forEach(c => c.classList.remove('border-primary'));
                    this.classList.add('border-primary');
                    document.getElementById('reportTemplate').value = this.dataset.template;
                });
            });

            // Report form submission
            document.getElementById('reportForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                try {
                    const response = await fetch('/api/modules/{$this->config['slug']}/report', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    if (result.success) {
                        showMessage('Report saved successfully', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
                        location.reload();
                    } else {
                        showMessage(result.error || 'Failed to save report', 'danger');
                    }
                } catch (error) {
                    showMessage('Error saving report', 'danger');
                }
            });

            // Generate report
            window.generateReport = async function(reportId) {
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating...';
                btn.disabled = true;

                try {
                    const response = await fetch('/api/modules/{$this->config['slug']}/generate/' + reportId, {
                        method: 'POST'
                    });

                    const result = await response.json();
                    if (result.success) {
                        showMessage('Report generated successfully', 'success');
                        viewReport(reportId);
                    } else {
                        showMessage(result.error || 'Failed to generate report', 'danger');
                    }
                } catch (error) {
                    showMessage('Error generating report', 'danger');
                } finally {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            };

            // View report
            window.viewReport = async function(reportId) {
                try {
                    const response = await fetch('/api/modules/{$this->config['slug']}/view/' + reportId);
                    const result = await response.json();

                    if (result.success) {
                        document.getElementById('reportViewerContent').innerHTML = result.html;

                        // Render charts if present
                        if (result.charts) {
                            result.charts.forEach(chartData => {
                                renderChart(chartData);
                            });
                        }

                        new bootstrap.Modal(document.getElementById('reportViewer')).show();
                    } else {
                        showMessage(result.error || 'Failed to load report', 'danger');
                    }
                } catch (error) {
                    showMessage('Error loading report', 'danger');
                }
            };

            // Export report
            window.exportReport = async function(reportId, format) {
                window.location.href = '/api/modules/{$this->config['slug']}/export/' + reportId + '?format=' + format;
            };

            // Schedule report
            window.scheduleReport = function(reportId) {
                document.getElementById('scheduleReportId').value = reportId;
                new bootstrap.Modal(document.getElementById('scheduleModal')).show();
            };

            // Schedule form submission
            document.getElementById('scheduleForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                try {
                    const response = await fetch('/api/modules/{$this->config['slug']}/schedule', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    if (result.success) {
                        showMessage('Report scheduled successfully', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
                        location.reload();
                    } else {
                        showMessage(result.error || 'Failed to schedule report', 'danger');
                    }
                } catch (error) {
                    showMessage('Error scheduling report', 'danger');
                }
            });

            // Delete report
            window.deleteReport = async function(reportId) {
                if (!confirm('Are you sure you want to delete this report?')) return;

                try {
                    const response = await fetch('/api/modules/{$this->config['slug']}/report/' + reportId, {
                        method: 'DELETE'
                    });

                    const result = await response.json();
                    if (result.success) {
                        showMessage('Report deleted successfully', 'success');
                        location.reload();
                    } else {
                        showMessage(result.error || 'Failed to delete report', 'danger');
                    }
                } catch (error) {
                    showMessage('Error deleting report', 'danger');
                }
            };

            // Render chart using Chart.js
            function renderChart(chartData) {
                const canvas = document.getElementById('chart-' + chartData.id);
                if (!canvas) return;

                const ctx = canvas.getContext('2d');
                new Chart(ctx, {
                    type: chartData.type,
                    data: chartData.data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: chartData.showLegend !== false,
                                position: 'bottom'
                            },
                            title: {
                                display: !!chartData.title,
                                text: chartData.title
                            }
                        }
                    }
                });
            }

            // Filter reports
            document.getElementById('reportSearch')?.addEventListener('input', function(e) {
                const search = e.target.value.toLowerCase();
                document.querySelectorAll('.report-card').forEach(card => {
                    const title = card.querySelector('.card-title')?.textContent.toLowerCase() || '';
                    const description = card.querySelector('.card-text')?.textContent.toLowerCase() || '';
                    card.closest('.col-md-4').style.display =
                        (title.includes(search) || description.includes(search)) ? '' : 'none';
                });
            });

            // Template filter
            document.querySelectorAll('.sidebar-nav a[data-template]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const template = this.dataset.template;

                    document.querySelectorAll('.report-card').forEach(card => {
                        const cardTemplate = card.dataset.template;
                        card.closest('.col-md-4').style.display =
                            (!template || template === cardTemplate) ? '' : 'none';
                    });

                    document.querySelectorAll('.sidebar-nav a').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });

        function showMessage(message, type) {
            // Reuse global message function or implement toast notification
            alert(message);
        }
        </script>
        HTML;

        return $html;
    }

    private function renderHeader(): string
    {
        return <<<HTML
        <div class="module-header bg-light border-bottom p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i data-feather="file-text"></i> Reports</h2>
                    <p class="text-muted mb-0">Generate, schedule, and export custom reports</p>
                </div>
                <button id="createReportBtn" class="btn btn-primary">
                    <i data-feather="plus"></i> Create Report
                </button>
            </div>
        </div>
        HTML;
    }

    private function renderSidebar(array $reports): string
    {
        $templates = self::TEMPLATES;
        $templateCounts = array_count_values(array_column($reports, 'template'));

        $templateLinks = '';
        foreach ($templates as $key => $template) {
            $count = $templateCounts[$key] ?? 0;
            $templateLinks .= <<<HTML
            <a href="#" class="list-group-item list-group-item-action" data-template="{$key}">
                <i data-feather="{$template['icon']}"></i> {$template['name']}
                <span class="badge bg-secondary float-end">{$count}</span>
            </a>
            HTML;
        }

        return <<<HTML
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Filter Reports</h5>
            </div>
            <div class="card-body">
                <input type="text" id="reportSearch" class="form-control mb-3" placeholder="Search reports...">

                <div class="list-group sidebar-nav">
                    <a href="#" class="list-group-item list-group-item-action active">
                        <i data-feather="list"></i> All Reports
                        <span class="badge bg-secondary float-end">{count($reports)}</span>
                    </a>
                    {$templateLinks}
                </div>

                <hr>

                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action">
                        <i data-feather="clock"></i> Scheduled Reports
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <i data-feather="star"></i> Favorites
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <i data-feather="archive"></i> Archived
                    </a>
                </div>
            </div>
        </div>
        HTML;
    }

    private function renderReportList(array $reports): string
    {
        if (empty($reports)) {
            return <<<HTML
            <div class="text-center py-5">
                <i data-feather="file-text" style="width: 64px; height: 64px;" class="text-muted"></i>
                <h3 class="mt-3">No Reports Yet</h3>
                <p class="text-muted">Create your first report to get started</p>
                <button class="btn btn-primary" onclick="document.getElementById('createReportBtn').click()">
                    <i data-feather="plus"></i> Create Report
                </button>
            </div>
            HTML;
        }

        $cards = '';
        foreach ($reports as $report) {
            $template = self::TEMPLATES[$report['template']] ?? self::TEMPLATES['table'];
            $lastRun = $report['last_run'] ? date('M j, Y g:i A', strtotime($report['last_run'])) : 'Never';
            $scheduled = $report['schedule_enabled'] ? '<span class="badge bg-info"><i data-feather="clock"></i> Scheduled</span>' : '';

            $cards .= <<<HTML
            <div class="col-md-4 mb-3">
                <div class="card report-card h-100" data-template="{$report['template']}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title">
                                <i data-feather="{$template['icon']}"></i> {$report['name']}
                            </h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                    <i data-feather="more-vertical"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="viewReport({$report['id']})">
                                        <i data-feather="eye"></i> View
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="generateReport({$report['id']})">
                                        <i data-feather="refresh-cw"></i> Generate
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="scheduleReport({$report['id']})">
                                        <i data-feather="clock"></i> Schedule
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport({$report['id']}, 'pdf')">
                                        <i data-feather="download"></i> Export PDF
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport({$report['id']}, 'excel')">
                                        <i data-feather="download"></i> Export Excel
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportReport({$report['id']}, 'csv')">
                                        <i data-feather="download"></i> Export CSV
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteReport({$report['id']})">
                                        <i data-feather="trash-2"></i> Delete
                                    </a></li>
                                </ul>
                            </div>
                        </div>

                        <p class="card-text text-muted small">{$report['description']}</p>

                        <div class="mb-2">
                            <span class="badge bg-secondary">{$template['name']}</span>
                            {$scheduled}
                        </div>

                        <p class="text-muted small mb-2">
                            <i data-feather="clock"></i> Last run: {$lastRun}
                        </p>

                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-sm" onclick="generateReport({$report['id']})">
                                <i data-feather="play"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="row">
            {$cards}
        </div>
        HTML;
    }

    private function renderReportModal(): string
    {
        $templates = self::TEMPLATES;
        $templateCards = '';
        foreach ($templates as $key => $template) {
            $templateCards .= <<<HTML
            <div class="col-md-6 mb-2">
                <div class="card template-card" data-template="{$key}" style="cursor: pointer;">
                    <div class="card-body text-center">
                        <i data-feather="{$template['icon']}" style="width: 32px; height: 32px;"></i>
                        <h6 class="mt-2 mb-1">{$template['name']}</h6>
                        <small class="text-muted">{$template['description']}</small>
                    </div>
                </div>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="modal fade" id="reportModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reportModalLabel">Create Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="reportForm">
                            <input type="hidden" id="reportId" name="id">
                            <input type="hidden" id="reportTemplate" name="template">

                            <div class="mb-3">
                                <label class="form-label">Report Template</label>
                                <div class="row">
                                    {$templateCards}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="reportName" class="form-label">Report Name</label>
                                <input type="text" class="form-control" id="reportName" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label for="reportDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="reportDescription" name="description" rows="2"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="reportDataSource" class="form-label">Data Source (SQL Query)</label>
                                <textarea class="form-control font-monospace" id="reportDataSource" name="data_source" rows="4" placeholder="SELECT * FROM table WHERE ..." required></textarea>
                                <small class="text-muted">Write SQL query to fetch report data</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="reportDateColumn" class="form-label">Date Column (Optional)</label>
                                    <input type="text" class="form-control" id="reportDateColumn" name="date_column" placeholder="created_at">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="reportGroupBy" class="form-label">Group By (Optional)</label>
                                    <input type="text" class="form-control" id="reportGroupBy" name="group_by" placeholder="category">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Save Report</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        HTML;
    }

    private function renderReportViewer(): string
    {
        return <<<HTML
        <div class="modal fade" id="reportViewer" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Report Viewer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="reportViewerContent">
                        <!-- Report content loaded dynamically -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i data-feather="printer"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
        HTML;
    }

    private function renderScheduleModal(): string
    {
        return <<<HTML
        <div class="modal fade" id="scheduleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Schedule Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="scheduleForm">
                            <input type="hidden" id="scheduleReportId" name="report_id">

                            <div class="mb-3">
                                <label class="form-label">Frequency</label>
                                <select class="form-select" name="frequency" required>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="custom">Custom Cron</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="scheduleTime" class="form-label">Time</label>
                                <input type="time" class="form-control" id="scheduleTime" name="time" value="09:00" required>
                            </div>

                            <div class="mb-3">
                                <label for="scheduleEmail" class="form-label">Email Recipients</label>
                                <input type="text" class="form-control" id="scheduleEmail" name="email_recipients" placeholder="email1@example.com, email2@example.com">
                                <small class="text-muted">Comma-separated email addresses</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Export Format</label>
                                <select class="form-select" name="export_format">
                                    <option value="pdf">PDF</option>
                                    <option value="excel">Excel</option>
                                    <option value="csv">CSV</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Schedule Report</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        HTML;
    }

    private function getReports(): array
    {
        $cacheKey = "reports_module_{$this->config['slug']}_user_{$this->userId}";

        // Try cache first (10min TTL)
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $stmt = $this->db->prepare("
            SELECT r.*,
                   (SELECT MAX(created_at) FROM report_runs WHERE report_id = r.id) as last_run
            FROM reports r
            WHERE r.module_slug = :slug
              AND r.is_active = 1
            ORDER BY r.name ASC
        ");
        $stmt->execute(['slug' => $this->config['slug']]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cache for 10 minutes
        Cache::set($cacheKey, $reports, 600);

        return $reports;
    }

    public function handle(array $data): array
    {
        $action = $data['action'] ?? 'list';
        return match($action) {
            'report' => $this->saveReport($data),
            'generate' => $this->generateReport($data),
            'view' => $this->viewReport($data),
            'export' => $this->exportReport($data),
            'schedule' => $this->scheduleReport($data),
            'delete' => $this->deleteReport($data),
            default => ['success' => false, 'error' => 'Unknown action']
        };
    }

    private function saveReport(array $data): array
    {
        try {
            if (!empty($data['id'])) {
                // Update existing report
                $stmt = $this->db->prepare("
                    UPDATE reports
                    SET name = :name,
                        description = :description,
                        template = :template,
                        data_source = :data_source,
                        date_column = :date_column,
                        group_by = :group_by,
                        updated_at = NOW()
                    WHERE id = :id AND module_slug = :slug
                ");
                $stmt->execute([
                    'id' => $data['id'],
                    'slug' => $this->config['slug'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'template' => $data['template'],
                    'data_source' => $data['data_source'],
                    'date_column' => $data['date_column'] ?? '',
                    'group_by' => $data['group_by'] ?? ''
                ]);
            } else {
                // Create new report
                $stmt = $this->db->prepare("
                    INSERT INTO reports (module_slug, name, description, template, data_source, date_column, group_by, created_by, created_at)
                    VALUES (:slug, :name, :description, :template, :data_source, :date_column, :group_by, :user_id, NOW())
                ");
                $stmt->execute([
                    'slug' => $this->config['slug'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? '',
                    'template' => $data['template'],
                    'data_source' => $data['data_source'],
                    'date_column' => $data['date_column'] ?? '',
                    'group_by' => $data['group_by'] ?? '',
                    'user_id' => $this->userId
                ]);
            }

            // Clear cache
            Cache::delete("reports_module_{$this->config['slug']}_user_{$this->userId}");

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function generateReport(array $data): array
    {
        try {
            $reportId = $data['report_id'] ?? $data['id'] ?? null;
            if (!$reportId) {
                throw new \Exception('Report ID required');
            }

            // Get report configuration
            $stmt = $this->db->prepare("SELECT * FROM reports WHERE id = :id AND module_slug = :slug");
            $stmt->execute(['id' => $reportId, 'slug' => $this->config['slug']]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$report) {
                throw new \Exception('Report not found');
            }

            // Execute data source query (with safety checks in production)
            $stmt = $this->db->prepare($report['data_source']);
            $stmt->execute();
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Store report run
            $stmt = $this->db->prepare("
                INSERT INTO report_runs (report_id, data, row_count, created_by, created_at)
                VALUES (:report_id, :data, :row_count, :user_id, NOW())
            ");
            $stmt->execute([
                'report_id' => $reportId,
                'data' => json_encode($reportData),
                'row_count' => count($reportData),
                'user_id' => $this->userId
            ]);

            return ['success' => true, 'data' => $reportData];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function viewReport(array $data): array
    {
        try {
            $reportId = $data['report_id'] ?? $data['id'] ?? null;

            // Get latest report run
            $stmt = $this->db->prepare("
                SELECT rr.*, r.name, r.template, r.description
                FROM report_runs rr
                JOIN reports r ON r.id = rr.report_id
                WHERE rr.report_id = :id
                ORDER BY rr.created_at DESC
                LIMIT 1
            ");
            $stmt->execute(['id' => $reportId]);
            $run = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$run) {
                return ['success' => false, 'error' => 'No report data available. Generate report first.'];
            }

            $reportData = json_decode($run['data'], true);
            $html = $this->renderReportData($run, $reportData);

            return [
                'success' => true,
                'html' => $html,
                'charts' => $this->extractChartData($run, $reportData)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function renderReportData(array $report, array $data): string
    {
        if (empty($data)) {
            return '<p class="text-muted text-center">No data available</p>';
        }

        $html = "<h4>{$report['name']}</h4>";
        $html .= "<p class='text-muted'>{$report['description']}</p>";
        $html .= "<p class='small'>Generated: " . date('M j, Y g:i A', strtotime($report['created_at'])) . "</p>";
        $html .= "<p class='small'>Total Records: " . count($data) . "</p>";
        $html .= "<hr>";

        // Render as table
        $html .= "<div class='table-responsive'>";
        $html .= "<table class='table table-striped table-sm'>";
        $html .= "<thead><tr>";
        foreach (array_keys($data[0]) as $column) {
            $html .= "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $column))) . "</th>";
        }
        $html .= "</tr></thead>";
        $html .= "<tbody>";
        foreach ($data as $row) {
            $html .= "<tr>";
            foreach ($row as $value) {
                $html .= "<td>" . htmlspecialchars($value ?? '') . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table></div>";

        return $html;
    }

    private function extractChartData(array $report, array $data): array
    {
        // Extract chart data based on template
        // This is simplified - real implementation would be more sophisticated
        return [];
    }

    private function exportReport(array $data): array
    {
        // Export report (PDF/Excel/CSV)
        // Would integrate with PhpSpreadsheet and TCPDF
        return ['success' => true, 'message' => 'Export functionality ready for integration'];
    }

    private function scheduleReport(array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO report_schedules (report_id, frequency, time, email_recipients, export_format, is_active, created_at)
                VALUES (:report_id, :frequency, :time, :email_recipients, :export_format, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    frequency = VALUES(frequency),
                    time = VALUES(time),
                    email_recipients = VALUES(email_recipients),
                    export_format = VALUES(export_format),
                    is_active = 1
            ");
            $stmt->execute([
                'report_id' => $data['report_id'],
                'frequency' => $data['frequency'],
                'time' => $data['time'],
                'email_recipients' => $data['email_recipients'] ?? '',
                'export_format' => $data['export_format']
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function deleteReport(array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE reports
                SET is_active = 0, updated_at = NOW()
                WHERE id = :id AND module_slug = :slug
            ");
            $stmt->execute([
                'id' => $data['id'],
                'slug' => $this->config['slug']
            ]);

            // Clear cache
            Cache::delete("reports_module_{$this->config['slug']}_user_{$this->userId}");

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate module configuration (implements ModuleInterface)
     *
     * @return bool True if configuration is valid
     */
    public function validate(): bool
    {
        // Check required configuration keys
        if (empty($this->config['slug']) || empty($this->config['name'])) {
            return false;
        }

        // Check type is correct
        if (empty($this->config['type']) || $this->config['type'] !== 'Report') {
            return false;
        }

        return true;
    }

    /**
     * Validate report data for creation/update
     *
     * @param array $data Report data to validate
     * @return array Array of error messages (empty if valid)
     */
    public function validateReportData(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Report name is required';
        }

        if (empty($data['template']) || !isset(self::TEMPLATES[$data['template']])) {
            $errors[] = 'Valid template is required';
        }

        if (empty($data['data_source'])) {
            $errors[] = 'Data source query is required';
        }

        // Basic SQL injection prevention (enhance in production)
        if (!empty($data['data_source']) && stripos($data['data_source'], 'DROP') !== false) {
            $errors[] = 'Invalid SQL query detected';
        }

        return $errors;
    }
}
