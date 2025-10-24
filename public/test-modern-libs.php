<?php
require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Auth;
use Hub\Layout;
use Hub\SiteSettings;

// Quick test - doesn't require login
$user = ['name' => 'Test User', 'email' => 'test@example.com', 'picture' => ''];
$userRole = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php Layout::renderHead('Modern Libraries Test - The Hub', 'hub'); ?>
    <style>
        .test-container { max-width: 800px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .test-button { margin: 10px 5px; }
        .status { padding: 20px; margin: 20px 0; border-radius: 8px; background: #f8f9fa; }
        .status-item { padding: 8px 0; border-bottom: 1px solid #dee2e6; }
        .status-item:last-child { border-bottom: none; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
    </style>
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="test-container">
        <h1 class="mb-4">
            <i class="bi bi-check2-circle text-success"></i>
            Modern Libraries Test
        </h1>
        
        <div class="status">
            <h5>Library Status:</h5>
            <div class="status-item">
                <strong>Bootstrap:</strong> 
                <span id="bootstrap-status" class="error">Not Loaded</span>
            </div>
            <div class="status-item">
                <strong>Bootstrap Icons:</strong> 
                <i class="bi bi-star-fill text-warning"></i>
                <span id="icons-status" class="success">Loaded</span>
            </div>
            <div class="status-item">
                <strong>Alpine.js:</strong> 
                <span id="alpine-status" class="error">Not Loaded</span>
            </div>
            <div class="status-item">
                <strong>SweetAlert2:</strong> 
                <span id="swal-status" class="error">Not Loaded</span>
            </div>
            <div class="status-item">
                <strong>Notyf:</strong> 
                <span id="notyf-status" class="error">Not Loaded</span>
            </div>
            <div class="status-item">
                <strong>Axios:</strong> 
                <span id="axios-status" class="error">Not Loaded</span>
            </div>
            <div class="status-item">
                <strong>Chart.js:</strong> 
                <span id="chart-status" class="error">Not Loaded</span>
            </div>
            <div class="status-item">
                <strong>TheHub Global:</strong> 
                <span id="thehub-status" class="error">Not Loaded</span>
            </div>
        </div>

        <div class="mt-4">
            <h5>Test Actions:</h5>
            <button class="btn btn-success test-button" onclick="testNotify()">
                <i class="bi bi-check-circle"></i> Test Notification
            </button>
            <button class="btn btn-primary test-button" onclick="testConfirm()">
                <i class="bi bi-question-circle"></i> Test Confirm
            </button>
            <button class="btn btn-info test-button" onclick="testSwal()">
                <i class="bi bi-stars"></i> Test SweetAlert
            </button>
            <button class="btn btn-warning test-button" onclick="testTooltip()" data-bs-toggle="tooltip" title="This is a Bootstrap tooltip!">
                <i class="bi bi-info-circle"></i> Test Tooltip
            </button>
        </div>

        <div class="mt-4" x-data="{ count: 0 }">
            <h5>Alpine.js Test:</h5>
            <button class="btn btn-primary btn-sm" @click="count++">
                <i class="bi bi-plus"></i>
            </button>
            <span class="badge bg-dark fs-4 mx-2" x-text="count"></span>
            <button class="btn btn-danger btn-sm" @click="count--">
                <i class="bi bi-dash"></i>
            </button>
        </div>

        <div class="mt-4">
            <canvas id="testChart" height="100"></canvas>
        </div>
    </div>

    <script>
        // Check library status
        document.addEventListener('DOMContentLoaded', () => {
            // Bootstrap
            if (typeof bootstrap !== 'undefined') {
                document.getElementById('bootstrap-status').textContent = 'Loaded ✓';
                document.getElementById('bootstrap-status').className = 'success';
            }

            // Alpine
            if (typeof Alpine !== 'undefined') {
                document.getElementById('alpine-status').textContent = 'Loaded ✓';
                document.getElementById('alpine-status').className = 'success';
            }

            // SweetAlert2
            if (typeof Swal !== 'undefined') {
                document.getElementById('swal-status').textContent = 'Loaded ✓';
                document.getElementById('swal-status').className = 'success';
            }

            // Notyf
            if (typeof Notyf !== 'undefined') {
                document.getElementById('notyf-status').textContent = 'Loaded ✓';
                document.getElementById('notyf-status').className = 'success';
            }

            // Axios
            if (typeof axios !== 'undefined') {
                document.getElementById('axios-status').textContent = 'Loaded ✓';
                document.getElementById('axios-status').className = 'success';
            }

            // Chart.js
            if (typeof Chart !== 'undefined') {
                document.getElementById('chart-status').textContent = 'Loaded ✓';
                document.getElementById('chart-status').className = 'success';
                
                // Create test chart
                const ctx = document.getElementById('testChart');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Test Data',
                            data: [12, 19, 15, 25, 22, 30],
                            borderColor: 'rgba(102, 126, 234, 1)',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4
                        }]
                    }
                });
            }

            // TheHub
            if (typeof TheHub !== 'undefined') {
                document.getElementById('thehub-status').textContent = 'Loaded ✓';
                document.getElementById('thehub-status').className = 'success';
            }
        });

        function testNotify() {
            if (typeof TheHub !== 'undefined') {
                TheHub.notify('This is a success notification!', 'success');
            } else {
                alert('TheHub not loaded');
            }
        }

        async function testConfirm() {
            if (typeof TheHub !== 'undefined') {
                const confirmed = await TheHub.confirm(
                    'Test Confirmation',
                    'Do you want to proceed with this test?'
                );
                if (confirmed) {
                    TheHub.notify('You clicked Confirm!', 'success');
                } else {
                    TheHub.notify('You clicked Cancel', 'info');
                }
            } else {
                alert('TheHub not loaded');
            }
        }

        function testSwal() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'SweetAlert2 Test',
                    text: 'This is a beautiful modal dialog!',
                    icon: 'success',
                    confirmButtonText: 'Awesome!'
                });
            } else {
                alert('SweetAlert2 not loaded');
            }
        }

        function testTooltip() {
            alert('Hover over this button to see the Bootstrap tooltip!');
        }
    </script>
    
    <?php Layout::renderModernInit(); ?>
</body>
</html>
