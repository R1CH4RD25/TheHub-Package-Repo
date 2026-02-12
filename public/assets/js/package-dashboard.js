/**
 * Package Dashboard Component JS (Layer 3)
 *
 * Handles Chart.js initialization from server-rendered config,
 * auto-refresh for dashboard cards, and export dropdown.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // ========================================
    // CHART.JS INITIALIZATION
    // ========================================
    var charts = window.__pkgCharts || {};
    Object.keys(charts).forEach(function (chartId) {
        var canvas = document.getElementById(chartId);
        if (!canvas) return;

        var config = charts[chartId];
        if (typeof Chart === 'undefined') {
            console.warn('[PackageDashboard] Chart.js not loaded. Add <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> to your page.');
            // Show fallback message
            var wrapper = canvas.parentElement;
            if (wrapper) {
                wrapper.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9ca3af;font-size:0.85rem;">' +
                    '<i class="bi bi-bar-chart" style="font-size:2rem;margin-right:0.5rem;"></i> Chart.js required for charts</div>';
            }
            return;
        }

        // Default color palette
        var defaultColors = [
            '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed',
            '#0891b2', '#ea580c', '#4f46e5', '#16a34a', '#e11d48'
        ];

        // Auto-assign colors if not set
        if (config.data && config.data.datasets) {
            config.data.datasets.forEach(function (dataset, idx) {
                if (!dataset.backgroundColor) {
                    if (config.type === 'pie' || config.type === 'doughnut') {
                        dataset.backgroundColor = defaultColors;
                    } else {
                        dataset.backgroundColor = defaultColors[idx % defaultColors.length] + '33'; // 20% opacity
                        dataset.borderColor = defaultColors[idx % defaultColors.length];
                        dataset.borderWidth = dataset.borderWidth || 2;
                    }
                }
            });
        }

        try {
            new Chart(canvas.getContext('2d'), config);
        } catch (err) {
            console.error('[PackageDashboard] Chart init error for', chartId, err);
        }
    });

    // ========================================
    // EXPORT DROPDOWN
    // ========================================
    document.querySelectorAll('.pkg-export-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var dropdown = this.closest('.pkg-export-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('open');
            }
        });
    });

    // Close export dropdown on outside click
    document.addEventListener('click', function () {
        document.querySelectorAll('.pkg-export-dropdown.open').forEach(function (dd) {
            dd.classList.remove('open');
        });
    });

    // Export option handler
    document.querySelectorAll('.pkg-export-option').forEach(function (option) {
        option.addEventListener('click', function (e) {
            e.preventDefault();
            var format = this.dataset.format;
            var packageId = this.dataset.package;
            var queryName = this.dataset.query;
            var csrfToken = this.dataset.csrf;

            if (!format || !packageId || !queryName) return;

            // Collect current filter params from URL
            var params = {};
            var searchParams = new URLSearchParams(window.location.search);
            searchParams.forEach(function (value, key) {
                params[key] = value;
            });

            // Close dropdown
            var dropdown = this.closest('.pkg-export-dropdown');
            if (dropdown) dropdown.classList.remove('open');

            // Show loading toast
            showExportToast('Preparing ' + format.toUpperCase() + ' export...');

            fetch('/api/package-export.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    packageId: packageId,
                    queryName: queryName,
                    format: format,
                    params: params,
                    csrfToken: csrfToken,
                }),
            })
            .then(function (response) {
                if (!response.ok) {
                    return response.json().then(function (data) {
                        throw new Error(data.error || 'Export failed');
                    });
                }

                // Extract filename from Content-Disposition header
                var disposition = response.headers.get('Content-Disposition');
                var filename = 'export.' + format;
                if (disposition) {
                    var match = disposition.match(/filename="?([^"]+)"?/);
                    if (match) filename = match[1];
                }

                return response.blob().then(function (blob) {
                    return { blob: blob, filename: filename };
                });
            })
            .then(function (result) {
                // Trigger download
                var url = URL.createObjectURL(result.blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = result.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                showExportToast('Export complete!', 'success');
            })
            .catch(function (err) {
                showExportToast(err.message || 'Export failed', 'error');
            });
        });
    });

    function showExportToast(message, type) {
        if (typeof window.PackageForm !== 'undefined' && window.PackageForm.showToast) {
            window.PackageForm.showToast(message, type || 'info');
        } else if (typeof window.showMessage === 'function') {
            window.showMessage(message, type || 'info');
        } else {
            console.log('[Export]', message);
        }
    }

    // ========================================
    // AUTO-REFRESH (optional)
    // ========================================
    document.querySelectorAll('.pkg-dashboard[data-refresh]').forEach(function (dashboard) {
        var interval = parseInt(dashboard.dataset.refresh, 10);
        if (!interval || interval < 10) return; // Min 10 seconds

        setInterval(function () {
            var queryName = dashboard.dataset.query;
            var packageId = dashboard.dataset.package;
            if (!queryName || !packageId) return;

            fetch('/api/package.php?action=query&package=' + encodeURIComponent(packageId) +
                  '&query=' + encodeURIComponent(queryName), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.data) {
                    // Update card values
                    dashboard.querySelectorAll('.pkg-card-value[data-key]').forEach(function (el) {
                        var key = el.dataset.key;
                        if (data.data[key] !== undefined) {
                            el.textContent = data.data[key];
                        }
                    });
                }
            })
            .catch(function () {
                // Silently fail on auto-refresh
            });
        }, interval * 1000);
    });
});
