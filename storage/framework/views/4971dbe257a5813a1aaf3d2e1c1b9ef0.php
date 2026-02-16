<?php $__env->startSection('title', ($mgmtDisplayName ?? 'Management') . ' Console'); ?>

<?php $__env->startSection('content'); ?>
<div class="admin-main-content">

    
    <div class="mgmt-modules-grid admin-responsive">
        <?php $__currentLoopData = $packageCards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="mgmt-module-card mgmt-rich-card">
            <div class="module-header">
                <div class="module-icon <?php echo e($card['color']); ?>">
                    <i class="<?php echo e($card['icon']); ?>"></i>
                </div>
                <div class="module-header-text">
                    <h3><?php echo e($card['name']); ?></h3>
                    <p class="module-subtitle"><?php echo e($card['subtitle']); ?></p>
                </div>
                <a href="<?php echo e($card['url']); ?>" class="module-manage-link">Manage</a>
            </div>
            <?php if(!empty($card['quick_actions'])): ?>
            <div class="module-quick-actions">
                <?php $__currentLoopData = $card['quick_actions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e($action['url']); ?>" class="module-action-link">
                    <?php if($action['icon']): ?><i class="<?php echo e($action['icon']); ?>"></i> <?php endif; ?><?php echo e($action['title']); ?>

                </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        
        <?php $__currentLoopData = $legacySections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="mgmt-module-card mgmt-rich-card">
            <div class="module-header">
                <div class="module-icon gray">
                    <i class="<?php echo e($section['icon'] ?? 'bi-folder'); ?>"></i>
                </div>
                <div class="module-header-text">
                    <h3><?php echo e($section['name']); ?></h3>
                    <p class="module-subtitle">
                        <?php echo e($section['submission_count'] ?? 0); ?> submission<?php echo e(($section['submission_count'] ?? 0) !== 1 ? 's' : ''); ?>

                        <?php if(($section['pending_count'] ?? 0) > 0): ?>
                        &middot; <strong><?php echo e($section['pending_count']); ?> pending</strong>
                        <?php endif; ?>
                    </p>
                </div>
                <a href="/management/section.php?slug=<?php echo e(urlencode($section['slug'])); ?>" class="module-manage-link">Manage</a>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    
    <?php if(!empty($attentionItems)): ?>
    <div class="attention-section">
        <h2><i class="bi bi-bell attention-icon-bell"></i> Needs Your Attention</h2>
        <div class="attention-list">
            <?php $__currentLoopData = $attentionItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="attention-item type-<?php echo e($item['type']); ?>">
                <div class="attention-icon <?php echo e($item['type']); ?>">
                    <i class="<?php echo e($item['icon']); ?>"></i>
                </div>
                <div class="attention-body">
                    <p class="attention-title"><?php echo e($item['title']); ?></p>
                    <p class="attention-subtitle"><?php echo e($item['subtitle']); ?></p>
                </div>
                <a href="<?php echo e($item['url']); ?>" class="attention-action">
                    <?php echo e($item['action']); ?> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.management', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/woodson/thehub/resources/views/management/dashboard.blade.php ENDPATH**/ ?>