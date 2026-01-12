<?php $__env->startSection('content'); ?>
<div class="export-container">
    <div class="card">
        <div class="card-header">
            <h2>Export Data</h2>
            <p class="text-muted">Export system data in various formats</p>
        </div>
        
        <div class="card-body">
            <div class="export-options">
                <h3>Available Exports</h3>
                
                <div class="export-option">
                    <h4>Users</h4>
                    <p>Export all users with their roles and access levels</p>
                    <button class="btn btn-primary" data-export="users">Export Users</button>
                </div>
                
                <div class="export-option">
                    <h4>Sections</h4>
                    <p>Export all sections with configuration and permissions</p>
                    <button class="btn btn-primary" data-export="sections">Export Sections</button>
                </div>
                
                <div class="export-option">
                    <h4>Packages</h4>
                    <p>Export all packages with metadata and assignments</p>
                    <button class="btn btn-primary" data-export="packages">Export Packages</button>
                </div>
                
                <div class="export-option">
                    <h4>Activity Logs</h4>
                    <p>Export audit trail and activity logs</p>
                    <button class="btn btn-primary" data-export="logs">Export Logs</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-export]').forEach(btn => {
        btn.addEventListener('click', async function() {
            const exportType = this.dataset.export;
            
            try {
                const response = await fetch('/admin/export', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ type: exportType }),
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert('Export started: ' + data.message);
                } else {
                    alert('Export failed: ' + data.message);
                }
            } catch (error) {
                console.error('Export error:', error);
                alert('Export failed. See console for details.');
            }
        });
    });
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/woodson/thehub/resources/views/admin/export.blade.php ENDPATH**/ ?>