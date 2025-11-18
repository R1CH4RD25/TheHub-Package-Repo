<?php
/**
 * Bullying Report Section
 * Placeholder/Demo content for SPA navigation testing
 */

// This file is included by /modules/sections/index.php
// The $user and $section variables are available
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="<?php echo e($section['icon'] ?? 'bi-shield-exclamation'); ?>"></i>
                        <?php echo e($section['display_name']); ?>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi-info-circle me-2"></i>
                        <strong>Demo Mode:</strong> This is a placeholder for the Bullying Report section.
                        The actual package content will be loaded here once the package system is fully implemented.
                    </div>

                    <p class="lead"><?php echo e($section['description']); ?></p>

                    <hr>

                    <h5>Section Information</h5>
                    <dl class="row">
                        <dt class="col-sm-3">Section Name:</dt>
                        <dd class="col-sm-9"><?php echo e($section['name']); ?></dd>

                        <dt class="col-sm-3">Type:</dt>
                        <dd class="col-sm-9">
                            <?php echo $section['is_dynamic'] ? '<span class="badge bg-success">Package-Based</span>' : '<span class="badge bg-secondary">File-Based</span>'; ?>
                        </dd>

                        <dt class="col-sm-3">Category:</dt>
                        <dd class="col-sm-9"><?php echo e($section['section_type']); ?></dd>

                        <dt class="col-sm-3">Status:</dt>
                        <dd class="col-sm-9">
                            <?php echo $section['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?>
                        </dd>
                    </dl>

                    <hr>

                    <h5>Quick Actions</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" onclick="alert('Submit Report feature coming soon!')">
                            <i class="bi-file-earmark-plus"></i> Submit New Report
                        </button>
                        <button class="btn btn-outline-secondary" onclick="alert('View Reports feature coming soon!')">
                            <i class="bi-list-ul"></i> View Reports
                        </button>
                        <a href="/" class="btn btn-outline-secondary">
                            <i class="bi-arrow-left"></i> Back to Hub
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Section-specific styles */
.card-header {
    background: var(--primary-color, #0d6efd) !important;
}
</style>
