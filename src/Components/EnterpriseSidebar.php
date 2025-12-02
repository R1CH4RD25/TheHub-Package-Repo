<?php

/**
 * Enterprise Sidebar Component
 *
 * Shared sidebar navigation for Management Console and Admin Dashboard
 * Google Admin Console / Microsoft 365 inspired design
 *
 * Usage:
 *   \Hub\Components\EnterpriseSidebar::render($user, $userRole, [
 *       'context' => 'management' | 'admin',
 *       'nav_items' => [array of nav items],
 *       'title' => 'Management',
 *       'icon' => 'bi-kanban',
 *       'logo_url' => '/management/'
 *   ]);
 */

namespace Hub\Components;

use Hub\SiteSettings;

class EnterpriseSidebar
{
    /**
     * Render the enterprise sidebar
     *
     * @param array $user User data
     * @param string $userRole User's role
     * @param array $options Configuration options
     *   - 'context' => 'management' | 'admin'
     *   - 'title' => Sidebar title (e.g., "Management", "Admin")
     *   - 'icon' => Icon class for logo
     *   - 'logo_url' => URL when clicking logo/title
     *   - 'nav_items' => Array of navigation items
     *   - 'active_item' => Current active nav item ID
     */
    public static function render($user, $userRole, $options = [])
    {
        $defaults = [
            'context' => 'management',
            'title' => 'Management',
            'icon' => 'bi-kanban',
            'logo_url' => '/management/',
            'nav_items' => [],
            'active_item' => null
        ];
        $opts = array_merge($defaults, $options);

        $contextClass = $opts['context'] === 'admin' ? 'admin-sidebar' : 'mgmt-sidebar';
        $navClass = $opts['context'] === 'admin' ? 'admin-nav' : 'mgmt-nav';
        $navLinkClass = $opts['context'] === 'admin' ? 'admin-nav-link' : 'mgmt-nav-link';

?>
        <!-- Enterprise Sidebar Component -->
        <aside class="<?= $contextClass ?>" data-sidebar="enterprise">
            <div class="sidebar-header">
                <button class="sidebar-toggle" aria-label="Toggle sidebar" title="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <a href="<?= htmlspecialchars($opts['logo_url'], ENT_QUOTES, 'UTF-8') ?>"
                    style="display: flex; align-items: center; gap: var(--space-3); text-decoration: none;">
                    <div class="sidebar-logo">
                        <i class="<?= htmlspecialchars($opts['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    </div>
                    <span class="sidebar-title"><?= htmlspecialchars($opts['title'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            </div>

            <nav class="<?= $navClass ?>">
                <?php if (!empty($opts['nav_items'])): ?>
                    <?php foreach ($opts['nav_items'] as $item): ?>
                        <?php
                        // Check if item should be shown based on permissions
                        $showItem = true;
                        if (isset($item['permission'])) {
                            if (is_callable($item['permission'])) {
                                $showItem = $item['permission']($user, $userRole);
                            } elseif (is_array($item['permission'])) {
                                $showItem = in_array($userRole, $item['permission']);
                            }
                        }

                        if (!$showItem) continue;

                        // Determine if this item is active
                        $isActive = false;
                        if ($opts['active_item']) {
                            $isActive = ($item['id'] ?? null) === $opts['active_item'];
                        } elseif (!empty($item['data_tab'])) {
                            // For JS-based tab switching (admin dashboard)
                            $isActive = ($item['active'] ?? false);
                        }

                        $activeClass = $isActive ? ' active' : '';
                        ?>

                        <?php if ($item['type'] === 'divider'): ?>
                            <div style="height: 1px; background: rgba(255,255,255,0.1); margin: var(--space-2) 0;"></div>

                        <?php elseif ($item['type'] === 'link'): ?>
                            <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= $navLinkClass . $activeClass ?>"
                                title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>"
                                <?php if (!empty($item['data_tab'])): ?>
                                data-tab="<?= htmlspecialchars($item['data_tab'], ENT_QUOTES, 'UTF-8') ?>"
                                <?php endif; ?>>
                                <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if (!empty($item['badge'])): ?>
                                    <span class="sidebar-badge"
                                        style="background: <?= htmlspecialchars($item['badge']['color'] ?? 'var(--error)', ENT_QUOTES, 'UTF-8') ?>; margin-left: auto;">
                                        <?= htmlspecialchars($item['badge']['count'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </nav>

            <?php if ($opts['context'] === 'admin'): ?>
            <!-- Sidebar Footer - Dashboard Info -->
            <div class="sidebar-footer">
                <div class="sidebar-footer-content">
                    <div class="footer-info">
                        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars(\Hub\SiteSettings::get('organization_name', 'Your Organization'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php
                    $version = \Hub\SiteSettings::get('site_version', '1.0');
                    ?>
                    <div class="footer-version">
                        Admin Dashboard v<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Sidebar Toggle Script -->
        <script nonce="<?php echo CSP_NONCE; ?>">
            (function() {
                const sidebar = document.querySelector('[data-sidebar="enterprise"]');
                const toggle = sidebar?.querySelector('.sidebar-toggle');
                const STORAGE_KEY = 'enterprise-sidebar-collapsed';

                // Restore saved state
                const isCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
                if (isCollapsed && sidebar) {
                    sidebar.classList.add('collapsed');
                }

                // Toggle functionality
                toggle?.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    const collapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem(STORAGE_KEY, collapsed);
                });
            })();
        </script>
<?php
    }

    /**
     * Helper: Build nav items array for Management Console
     *
     * @param array $sections Array of sections/modules user has access to
     * @param string $currentSlug Currently active section slug
     * @return array Nav items
     */
    public static function buildManagementNavItems($sections = [], $currentSlug = null)
    {
        $items = [];

        if (!empty($sections)) {
            foreach ($sections as $section) {
                $badge = null;
                if (($section['urgent_count'] ?? 0) > 0) {
                    $badge = ['count' => $section['urgent_count'], 'color' => 'var(--error)'];
                } elseif (($section['pending_count'] ?? 0) > 0) {
                    $badge = ['count' => $section['pending_count'], 'color' => 'var(--warning)'];
                }

                $items[] = [
                    'id' => $section['slug'],
                    'type' => 'link',
                    'url' => '/management/section.php?slug=' . urlencode($section['slug']),
                    'icon' => $section['icon'] ?? 'bi-folder',
                    'label' => $section['name'],
                    'badge' => $badge,
                    'active' => ($section['slug'] === $currentSlug)
                ];
            }
        }

        return $items;
    }

    /**
     * Helper: Build nav items array for Admin Dashboard
     *
     * @param string $userRole User's role for permission checking
     * @param string $activeTab Currently active tab
     * @return array Nav items
     */
    public static function buildAdminNavItems($userRole, $activeTab = 'users')
    {
        $isSuperAdmin = ($userRole === 'super_admin');
        $isAdmin = in_array($userRole, ['super_admin', 'admin']);

        $items = [];

        // Management Console link
        if ($isAdmin) {
            $mgmtName = SiteSettings::get('mgmt_display_name', 'Management');
            $mgmtIcon = SiteSettings::get('mgmt_icon', 'bi-kanban');

            $items[] = [
                'id' => 'management',
                'type' => 'link',
                'url' => '/management/',
                'icon' => $mgmtIcon,
                'label' => $mgmtName
            ];
        }

        // User Management
        if ($isAdmin) {
            $items[] = [
                'id' => 'users',
                'type' => 'link',
                'url' => '#',
                'icon' => 'fas fa-users',
                'label' => 'User Management',
                'data_tab' => 'users',
                'active' => ($activeTab === 'users'),
                'permission' => ['super_admin', 'admin']
            ];
        }

        // Package Management
        if ($isAdmin) {
            $items[] = [
                'id' => 'packages',
                'type' => 'link',
                'url' => '#',
                'icon' => 'fas fa-box',
                'label' => 'Package Management',
                'data_tab' => 'packages',
                'active' => ($activeTab === 'packages'),
                'permission' => ['super_admin', 'admin']
            ];
        }

        // Super Admin only items
        if ($isSuperAdmin) {
            $items[] = [
                'id' => 'site-settings',
                'type' => 'link',
                'url' => '#',
                'icon' => 'fas fa-cog',
                'label' => 'Site Settings',
                'data_tab' => 'site-settings',
                'active' => ($activeTab === 'site-settings'),
                'permission' => ['super_admin']
            ];

            $items[] = [
                'id' => 'logs',
                'type' => 'link',
                'url' => '#',
                'icon' => 'fas fa-chart-line',
                'label' => 'Activity Logs',
                'data_tab' => 'logs',
                'active' => ($activeTab === 'logs'),
                'permission' => ['super_admin']
            ];
        }

        // Export Data
        if ($isAdmin) {
            $items[] = [
                'id' => 'export',
                'type' => 'link',
                'url' => '#',
                'icon' => 'fas fa-download',
                'label' => 'Export Data',
                'data_tab' => 'export',
                'active' => ($activeTab === 'export'),
                'permission' => ['super_admin', 'admin']
            ];
        }

        return $items;
    }
}
