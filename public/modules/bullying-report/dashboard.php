<?php
/**
 * Bullying Reports Dashboard
 * RESTRICTED: Only counselor, principal, admin, super_admin can access
 */

require_once __DIR__ . '/../../../src/bootstrap.php';

use Hub\Auth;

// Require login and specific roles
Auth::requireLogin();
Auth::requireRole(['counselor', 'principal', 'admin', 'super_admin']);

$currentUser = Auth::getCurrentUser();
$userRole = Auth::getUserRole();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bullying Reports Dashboard - Woodson ISD</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .dashboard-header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #2196f3;
        }
        
        .stat-card.urgent {
            border-left-color: #d32f2f;
            background: #ffebee;
        }
        
        .stat-card.high {
            border-left-color: #ff9800;
            background: #fff3e0;
        }
        
        .stat-card h4 {
            margin: 0 0 5px 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .filters-bar {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 600;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .reports-table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .reports-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reports-table th {
            background: #2196f3;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .reports-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .reports-table tr:hover {
            background: #f5f5f5;
            cursor: pointer;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-submitted {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-under_review {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .status-investigating {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .status-resolved {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .status-closed {
            background: #f5f5f5;
            color: #757575;
        }
        
        .priority-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .priority-urgent {
            background: #d32f2f;
            color: white;
        }
        
        .priority-high {
            background: #ff9800;
            color: white;
        }
        
        .priority-medium {
            background: #ffc107;
            color: #333;
        }
        
        .priority-low {
            background: #4caf50;
            color: white;
        }
        
        .incident-type-icon {
            display: inline-block;
            width: 24px;
            text-align: center;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            max-width: 900px;
            margin: 40px auto;
            border-radius: 8px;
            padding: 30px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .modal-close {
            font-size: 2rem;
            cursor: pointer;
            color: #999;
        }
        
        .report-detail-section {
            margin-bottom: 25px;
        }
        
        .report-detail-section h3 {
            color: #2196f3;
            margin-bottom: 10px;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .detail-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .detail-item label {
            font-weight: 600;
            color: #666;
            font-size: 0.85rem;
            display: block;
            margin-bottom: 5px;
        }
        
        .detail-item .value {
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #2196f3;
            color: white;
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-danger {
            background: #d32f2f;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="dashboard-header">
            <h1>🛡️ Bullying Reports Dashboard</h1>
            <p>Confidential incident reports - <?php echo e($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?> (<?php echo Auth::formatRole($userRole); ?>)</p>
            
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card">
                    <h4>Total Reports</h4>
                    <div class="number" id="statTotal">0</div>
                </div>
                <div class="stat-card">
                    <h4>Pending Review</h4>
                    <div class="number" id="statPending">0</div>
                </div>
                <div class="stat-card urgent">
                    <h4>Urgent Priority</h4>
                    <div class="number" id="statUrgent">0</div>
                </div>
                <div class="stat-card high">
                    <h4>High Priority</h4>
                    <div class="number" id="statHigh">0</div>
                </div>
            </div>
        </header>
        
        <div class="filters-bar">
            <div class="filter-group">
                <label>Status</label>
                <select id="filterStatus">
                    <option value="">All Statuses</option>
                    <option value="submitted">Submitted</option>
                    <option value="under_review">Under Review</option>
                    <option value="investigating">Investigating</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Priority</label>
                <select id="filterPriority">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Type</label>
                <select id="filterType">
                    <option value="">All Types</option>
                    <option value="physical">Physical</option>
                    <option value="verbal">Verbal</option>
                    <option value="cyber">Cyberbullying</option>
                    <option value="social">Social</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" id="filterDateFrom">
            </div>
            
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" id="filterDateTo" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <button class="btn btn-primary" onclick="loadReports()" style="align-self: flex-end;">🔍 Apply Filters</button>
            <button class="btn" onclick="resetFilters()" style="align-self: flex-end;">Clear</button>
        </div>
        
        <div class="reports-table-container">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="reportsTableBody">
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            Loading reports...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Report Detail Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Report Details #<span id="modalReportId"></span></h2>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody">
                <!-- Report details will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        window.csrfToken = '<?php echo generateCsrfToken(); ?>';
        
        async function loadReports() {
            const filters = {
                status: document.getElementById('filterStatus').value,
                priority: document.getElementById('filterPriority').value,
                type: document.getElementById('filterType').value,
                date_from: document.getElementById('filterDateFrom').value,
                date_to: document.getElementById('filterDateTo').value
            };
            
            try {
                const params = new URLSearchParams(filters);
                const response = await fetch(`/api/bullying-reports.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    updateStats(data.stats);
                    renderReportsTable(data.reports);
                } else {
                    alert('Error loading reports: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Network error loading reports');
            }
        }
        
        function updateStats(stats) {
            document.getElementById('statTotal').textContent = stats.total || 0;
            document.getElementById('statPending').textContent = stats.pending || 0;
            document.getElementById('statUrgent').textContent = stats.urgent || 0;
            document.getElementById('statHigh').textContent = stats.high || 0;
        }
        
        function renderReportsTable(reports) {
            const tbody = document.getElementById('reportsTableBody');
            
            if (reports.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px;">No reports found</td></tr>';
                return;
            }
            
            tbody.innerHTML = reports.map(report => `
                <tr onclick="viewReport(${report.id})">
                    <td>#${report.id}</td>
                    <td>${formatDate(report.incident_date)}</td>
                    <td>
                        <span class="incident-type-icon">${getTypeIcon(report.incident_type)}</span>
                        ${report.incident_type}
                    </td>
                    <td>${escapeHtml(report.incident_location)}</td>
                    <td><span class="priority-badge priority-${report.priority}">${report.priority.toUpperCase()}</span></td>
                    <td><span class="status-badge status-${report.status}">${formatStatus(report.status)}</span></td>
                    <td>${report.assigned_to_name || '<em>Unassigned</em>'}</td>
                    <td><button class="btn btn-primary btn-sm">View</button></td>
                </tr>
            `).join('');
        }
        
        function getTypeIcon(type) {
            const icons = {
                'physical': '👊',
                'verbal': '💬',
                'cyber': '💻',
                'social': '👥',
                'other': '❓'
            };
            return icons[type] || '📝';
        }
        
        function formatStatus(status) {
            return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        }
        
        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString();
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        async function viewReport(reportId) {
            // Load full report details
            try {
                const response = await fetch(`/api/bullying-reports.php?id=${reportId}`);
                const data = await response.json();
                
                if (data.success) {
                    showReportModal(data.report);
                }
            } catch (error) {
                console.error('Error loading report:', error);
            }
        }
        
        function showReportModal(report) {
            document.getElementById('modalReportId').textContent = report.id;
            // Populate modal with report details (implement based on needs)
            document.getElementById('reportModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('reportModal').style.display = 'none';
        }
        
        function resetFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterPriority').value = '';
            document.getElementById('filterType').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '<?php echo date('Y-m-d'); ?>';
            loadReports();
        }
        
        // Load reports on page load
        loadReports();
    </script>
</body>
</html>
