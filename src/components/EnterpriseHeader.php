<?php
/**
 * Enterprise Header Component
 * 
 * Shared header for Management Console and Admin Dashboard
 * Google Admin Console / Microsoft 365 inspired design
 * 
 * Usage:
 *   \Hub\Components\EnterpriseHeader::render($user, $userRole, [
 *       'context' => 'management' | 'admin',
 *       'breadcrumbs' => [['label' => 'Home', 'url' => '/hub.php'], ['label' => 'Current']],
 *       'actions' => [] // Optional action buttons
 *   ]);
 */

namespace Hub\Components;

use Hub\SiteSettings;

class EnterpriseHeader
{
    /**
     * Render the enterprise header
     * 
     * @param array $user User data
     * @param string $userRole User's role
     * @param array $options Configuration options
     *   - 'context' => 'management' | 'admin' (determines styling/links)
     *   - 'breadcrumbs' => array of breadcrumb items
     *   - 'actions' => array of action buttons/links
     *   - 'show_search' => bool (default: false)
     *   - 'show_notifications' => bool (default: false)
     */
    public static function render($user, $userRole, $options = [])
    {
        $defaults = [
            'context' => 'management',
            'breadcrumbs' => [],
            'actions' => [],
            'show_search' => false,
            'show_notifications' => false
        ];
        $opts = array_merge($defaults, $options);
        
        $contextClass = $opts['context'] === 'admin' ? 'admin-header' : 'mgmt-header';
        $isSuperAdmin = ($userRole === 'super_admin');
        
        ?>
        <!-- Enterprise Header Component -->
        <header class="<?= $contextClass ?>">
            <div class="breadcrumb">
                <?php if (!empty($opts['breadcrumbs'])): ?>
                    <?php foreach ($opts['breadcrumbs'] as $i => $crumb): ?>
                        <?php if ($i > 0): ?><span>/</span><?php endif; ?>
                        <?php if (!empty($crumb['url'])): ?>
                            <a href="<?= htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php else: ?>
                            <span><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="/hub.php">Home</a>
                    <span>/</span>
                    <span><?= $opts['context'] === 'admin' ? 'Admin Dashboard' : 'Management Console' ?></span>
                <?php endif; ?>
            </div>
            
            <div class="header-actions">
                <?php if ($opts['show_search']): ?>
                <div class="header-search">
                    <input type="search" 
                           class="search-input" 
                           placeholder="Search..." 
                           aria-label="Search">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($opts['actions'])): ?>
                    <?php foreach ($opts['actions'] as $action): ?>
                        <a href="<?= htmlspecialchars($action['url'], ENT_QUOTES, 'UTF-8') ?>" 
                           class="btn <?= htmlspecialchars($action['class'] ?? 'btn-secondary', ENT_QUOTES, 'UTF-8') ?>"
                           style="text-decoration: none;">
                            <?php if (!empty($action['icon'])): ?>
                                <i class="<?= htmlspecialchars($action['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php 
                // Context-specific cross-navigation
                if ($opts['context'] === 'management' && $isSuperAdmin): 
                ?>
                    <a href="/admin/" class="btn btn-secondary" style="text-decoration: none;">
                        <i class="fas fa-shield-alt"></i> Admin Dashboard
                    </a>
                <?php endif; ?>
                
                <?php if ($opts['show_notifications']): ?>
                <button class="btn-icon" title="Notifications" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" style="display: none;">0</span>
                </button>
                <?php endif; ?>
                
                <?php
                // User Profile Dropdown
                \Hub\Components\UserProfileDropdown::render($user, $userRole, [
                    'show_preferences' => true,
                    'show_contact' => true
                ]);
                ?>
            </div>
        </header>
        <?php
    }
}
