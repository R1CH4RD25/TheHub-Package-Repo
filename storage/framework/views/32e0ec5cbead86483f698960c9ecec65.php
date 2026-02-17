<?php $__env->startSection('title', 'Package Creator'); ?>

<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="/assets/css/package-wizard.css">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="admin-main-content">
    <div class="content-header">
        <div>
            <h1><i class="bi bi-magic"></i> Package Creator</h1>
            <p class="text-muted">Build a new package visually — no code required</p>
        </div>
        <a href="<?php echo e(route('admin.packages.available')); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Packages
        </a>
    </div>

    <div class="content-body">
        <div id="package-wizard" class="package-wizard">
            <div class="text-muted text-center" style="padding: 3rem;">
                <i class="bi bi-hourglass-split"></i> Loading wizard...
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="/assets/js/package-wizard.js"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/woodson/thehub/resources/views/admin/packages-create.blade.php ENDPATH**/ ?>