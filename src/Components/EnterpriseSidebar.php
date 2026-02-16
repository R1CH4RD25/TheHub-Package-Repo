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

        // Both admin and management use the same sidebar styling
        $contextClass = 'admin-sidebar';
        $navClass = 'admin-nav';
        $navLinkClass = 'admin-nav-link';

?>
        <!-- Enterprise Sidebar Component -->
        <aside class="<?= $contextClass ?>" data-sidebar="enterprise">
            <div class="sidebar-header">
                <button class="sidebar-toggle" aria-label="Toggle sidebar" title="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <a href="<?= htmlspecialchars($opts['logo_url'], ENT_QUOTES, 'UTF-8') ?>"
                    class="sidebar-brand-link">
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
                            <div class="sidebar-divider"></div>

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

                        <?php elseif ($item['type'] === 'expandable'): ?>
                            <?php
                            // Check if any submenu item is active OR if explicitly expanded
                            $hasActiveChild = false;
                            $activeChildId = null;
                            if (!empty($item['submenu'])) {
                                foreach ($item['submenu'] as $subitem) {
                                    if (($subitem['active'] ?? false) === true) {
                                        $hasActiveChild = true;
                                        $activeChildId = $subitem['id'] ?? null;
                                        break;
                                    }
                                    // Also check nested expandable children for active leaves
                                    if (($subitem['type'] ?? 'link') === 'expandable' && !empty($subitem['submenu'])) {
                                        foreach ($subitem['submenu'] as $leaf) {
                                            if (($leaf['active'] ?? false) === true) {
                                                $hasActiveChild = true;
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            }
                            $isExpanded = $hasActiveChild || ($item['expanded'] ?? false);
                            $parentId = $item['id'];
                            ?>
                            <div class="nav-expandable <?= $isExpanded ? 'expanded has-active-child' : '' ?>"
                                data-nav-parent="<?= htmlspecialchars($parentId, ENT_QUOTES, 'UTF-8') ?>">
                                <!-- Google-style expandable: chevron on left -->
                                <button class="<?= $navLinkClass ?> nav-expandable-trigger"
                                    title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>"
                                    aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>"
                                    data-parent-id="<?= htmlspecialchars($parentId, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fas fa-caret-right nav-expand-icon"></i>
                                    <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                </button>
                                <?php if (!empty($item['submenu']) && count($item['submenu']) > 0): ?>
                                    <div class="nav-submenu"
                                        data-nav-submenu="<?= htmlspecialchars($parentId, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $isExpanded ? '' : 'style="display: none;"' ?>>
                                        <?php foreach ($item['submenu'] as $subitem): ?>
                                            <?php
                                            $isSubitemActive = ($subitem['active'] ?? false) === true;
                                            ?>
                                            <?php if (($subitem['type'] ?? 'link') === 'expandable' && !empty($subitem['submenu'])): ?>
                                                <?php
                                                // Nested expandable (3-tier: e.g. package under category)
                                                $nestedHasActive = false;
                                                foreach ($subitem['submenu'] as $leaf) {
                                                    if (($leaf['active'] ?? false) === true) {
                                                        $nestedHasActive = true;
                                                        break;
                                                    }
                                                }
                                                $nestedExpanded = $nestedHasActive || ($subitem['expanded'] ?? false);
                                                $nestedId = $subitem['id'] ?? 'nested';
                                                ?>
                                                <div class="nav-expandable nav-nested-expandable <?= $nestedExpanded ? 'expanded has-active-child' : '' ?>"
                                                    data-nav-parent="<?= htmlspecialchars($nestedId, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="<?= $navLinkClass ?> nav-sublink nav-expandable-trigger"
                                                        title="<?= htmlspecialchars($subitem['label'], ENT_QUOTES, 'UTF-8') ?>"
                                                        aria-expanded="<?= $nestedExpanded ? 'true' : 'false' ?>"
                                                        data-parent-id="<?= htmlspecialchars($nestedId, ENT_QUOTES, 'UTF-8') ?>">
                                                        <i class="fas fa-caret-right nav-expand-icon"></i>
                                                        <span><?= htmlspecialchars($subitem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    </button>
                                                    <div class="nav-submenu nav-submenu-nested"
                                                        data-nav-submenu="<?= htmlspecialchars($nestedId, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $nestedExpanded ? '' : 'style="display: none;"' ?>>
                                                        <?php foreach ($subitem['submenu'] as $leaf): ?>
                                                            <?php $isLeafActive = ($leaf['active'] ?? false) === true; ?>
                                                            <a href="<?= htmlspecialchars($leaf['url'], ENT_QUOTES, 'UTF-8') ?>"
                                                                class="<?= $navLinkClass ?> nav-sublink nav-leaf-link"
                                                                data-nav-child
                                                                data-parent="<?= htmlspecialchars($nestedId, ENT_QUOTES, 'UTF-8') ?>"
                                                                data-active="<?= $isLeafActive ? 'true' : 'false' ?>">
                                                                <span><?= htmlspecialchars($leaf['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <a href="<?= htmlspecialchars($subitem['url'], ENT_QUOTES, 'UTF-8') ?>"
                                                    class="<?= $navLinkClass ?> nav-sublink"
                                                    data-nav-child
                                                    data-parent="<?= htmlspecialchars($parentId, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-active="<?= $isSubitemActive ? 'true' : 'false' ?>"
                                                    <?php if (!empty($subitem['data_tab'])): ?>
                                                    data-tab="<?= htmlspecialchars($subitem['data_tab'], ENT_QUOTES, 'UTF-8') ?>"
                                                    <?php endif; ?>>
                                                    <span><?= htmlspecialchars($subitem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </a>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div> <!-- Close nav-expandable -->

                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </nav>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="sidebar-footer-content">
                    <div class="footer-info">
                        <span>&copy; <?= date('Y') ?> <?= htmlspecialchars(\Hub\SiteSettings::get('organization_name', 'Your Organization'), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php
                    $version = \Hub\SiteSettings::get('site_version', '1.0');
                    $dashLabel = $opts['context'] === 'admin' ? 'Admin' : htmlspecialchars($opts['title'], ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="footer-version">
                        <?= $dashLabel ?> Dashboard v<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Sidebar Toggle Script -->
        <script nonce="<?php echo CSP_NONCE; ?>">
            (function() {
                const DEBUG = true; // Set to false to disable logging
                const log = (...args) => DEBUG && console.log('🔧 SIDEBAR:', ...args);

                const sidebar = document.querySelector('[data-sidebar="enterprise"]');
                const toggle = sidebar?.querySelector('.sidebar-toggle');
                const shell = document.querySelector('.admin-shell');
                const STORAGE_KEY = 'enterprise-sidebar-collapsed';

                log('Initialized', {
                    sidebar: !!sidebar,
                    toggle: !!toggle,
                    shell: !!shell
                });

                // Restore saved state
                const isCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
                if (isCollapsed && sidebar && shell) {
                    sidebar.classList.add('collapsed');
                    shell.classList.add('has-collapsed-sidebar');
                    log('Restored collapsed state');
                }

                // Toggle functionality
                toggle?.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    shell.classList.toggle('has-collapsed-sidebar');
                    const collapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem(STORAGE_KEY, collapsed);
                    log('Toggle clicked', {
                        collapsed
                    });
                });

                // Expandable menu functionality (Google Admin style)
                const expandableTriggers = sidebar?.querySelectorAll('.nav-expandable-trigger');
                log('Found expandable triggers:', expandableTriggers?.length);

                // Google Admin Pattern: Sync active highlights based on expanded state
                function syncActiveHighlights() {
                    const isNavCollapsed = sidebar?.classList.contains('collapsed');

                    sidebar?.querySelectorAll('[data-nav-parent]').forEach(expandableSection => {
                        const parentId = expandableSection.dataset.navParent;
                        const parentBtn = expandableSection.querySelector('.nav-expandable-trigger');
                        const submenu = expandableSection.querySelector('.nav-submenu');
                        const isSectionExpanded = expandableSection.classList.contains('expanded');

                        // Find active child
                        const activeChild = expandableSection.querySelector('[data-nav-child][data-active="true"]');
                        const hasActiveChild = !!activeChild;

                        // Clear existing highlights
                        parentBtn?.classList.remove('active', 'has-active-descendant');
                        expandableSection.querySelectorAll('[data-nav-child]').forEach(child => {
                            child.classList.remove('active');
                        });

                        if (hasActiveChild) {
                            // KEY RULE (Google Admin style):
                            // Only highlight child if section is expanded AND nav isn't collapsed
                            const highlightChild = !isNavCollapsed && isSectionExpanded;

                            log(`  ${parentId}:`, {
                                hasActiveChild,
                                isSectionExpanded,
                                isNavCollapsed,
                                highlightChild,
                                willHighlight: highlightChild ? 'CHILD' : 'PARENT'
                            });

                            if (highlightChild) {
                                // Section open: highlight the active submenu item
                                activeChild.classList.add('active');
                                parentBtn?.classList.add('has-active-descendant'); // subtle state
                            } else {
                                // Section closed or nav collapsed: highlight parent only
                                parentBtn?.classList.add('active');
                                log(`  ✓ Added 'active' class to ${parentId} parent button`);
                            }
                        }
                    });

                    log('Active highlights synced', {
                        isNavCollapsed
                    });
                }

                // Initial sync on page load
                syncActiveHighlights();

                expandableTriggers?.forEach((trigger, index) => {
                    const parent = trigger.closest('.nav-expandable');
                    const itemLabel = trigger.querySelector('span')?.textContent?.trim();
                    log(`Setup trigger ${index}:`, itemLabel);

                    trigger.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        const parent = trigger.closest('.nav-expandable');
                        const submenu = parent.querySelector(':scope > .nav-submenu');
                        const isExpanded = parent.classList.contains('expanded');

                        log('Click on:', itemLabel, {
                            wasExpanded: isExpanded
                        });

                        // Close sibling expandables at the same level (not ancestors or descendants)
                        const parentContainer = parent.parentElement;
                        const siblings = parentContainer.querySelectorAll(':scope > .nav-expandable');
                        siblings.forEach(item => {
                            if (item !== parent) {
                                item.classList.remove('expanded');
                                // Close all nested expandables inside the sibling too
                                item.querySelectorAll('.nav-expandable').forEach(nested => {
                                    nested.classList.remove('expanded');
                                    nested.querySelector('.nav-submenu')?.style.setProperty('display', 'none');
                                });
                                item.querySelector(':scope > .nav-submenu')?.style.setProperty('display', 'none');
                            }
                        });

                        // Toggle this one (CSS handles caret rotation via .expanded class)
                        parent.classList.toggle('expanded');
                        if (submenu) {
                            submenu.style.display = isExpanded ? 'none' : 'block';
                        }

                        // Re-sync highlights after toggle
                        syncActiveHighlights();

                        log('Toggled to:', parent.classList.contains('expanded'));
                    });
                });

                // Re-sync highlights when sidebar is collapsed/expanded
                toggle?.addEventListener('click', () => {
                    setTimeout(syncActiveHighlights, 50); // Small delay for transition
                });
            })();
        </script>
<?php
    }

    /**
     * Helper: Build nav items for Management Console
     *
     * Google Admin Console pattern — the sidebar ALWAYS shows the full
     * navigation tree. The active package is auto-expanded and the
     * active page highlighted. No mode switching.
     *
     *   🏠 Home
     *   🏫 District              ← category (expandable)
     *     ▸ Student Directory    ← package (expandable, auto-expanded when active)
     *       ├─ Student Directory ← page (leaf, highlighted when active)
     *       ├─ Add Student
     *       ├─ Import Students
     *       └─ Export Students
     *     ▸ Staff Directory
     *   🔧 Operations
     *     ▸ Maintenance Requests
     *   ─────────────────
     *   📋 Legacy Section        ← legacy sections (if any)
     *
     * @param array $sections Legacy sections
     * @param string|null $currentSlug Active section slug
     * @param array $packages All packages the user can access
     * @param string|null $activePackageId Active package ID (null = dashboard)
     * @param string|null $activePageId Active page within a package
     * @param array $accessiblePages Accessible pages info
     * @param array $categoryMap Category metadata (icon, color, label)
     * @return array Nav items
     */
    public static function buildManagementNavItems($sections = [], $currentSlug = null, $packages = [], $activePackageId = null, $activePageId = null, $accessiblePages = [], $categoryMap = [])
    {
        $items = [];

        // Default category definitions
        if (empty($categoryMap)) {
            $categoryMap = [
                'district'     => ['icon' => 'fas fa-school',          'label' => 'District'],
                'operations'   => ['icon' => 'fas fa-tools',           'label' => 'Operations'],
                'finance'      => ['icon' => 'fas fa-dollar-sign',     'label' => 'Finance'],
                'reporting'    => ['icon' => 'fas fa-chart-bar',       'label' => 'Reporting'],
                'analytics'    => ['icon' => 'fas fa-chart-line',      'label' => 'Analytics'],
                'forms'        => ['icon' => 'fas fa-file-alt',        'label' => 'Forms'],
                'integrations' => ['icon' => 'fas fa-plug',            'label' => 'Integrations'],
                'workflows'    => ['icon' => 'fas fa-project-diagram', 'label' => 'Workflows'],
                'student'      => ['icon' => 'fas fa-user-graduate',   'label' => 'Student'],
            ];
        }

        // ── Home link (always present) ───────────────────────────────
        $items[] = [
            'id' => 'home',
            'type' => 'link',
            'url' => '/management/',
            'icon' => 'fas fa-home',
            'label' => 'Home',
            'active' => ($activePackageId === null && $currentSlug === null),
        ];

        // ── Group packages by category ───────────────────────────────
        $categoriesWithPackages = [];
        foreach ($packages as $pkg) {
            $pkgId = $pkg['package_id'] ?? '';
            $category = explode('.', $pkgId)[0] ?? 'other';
            if (!isset($categoriesWithPackages[$category])) {
                $categoriesWithPackages[$category] = [];
            }
            $categoriesWithPackages[$category][] = $pkg;
        }

        // ── Build category → package → pages tree ────────────────────
        foreach ($categoriesWithPackages as $catKey => $catPackages) {
            $catInfo = $categoryMap[$catKey] ?? ['icon' => 'fas fa-box', 'label' => ucfirst($catKey)];
            $catHasActiveChild = false;

            $subItems = [];
            foreach ($catPackages as $pkg) {
                $isActivePackage = ($pkg['package_id'] === $activePackageId);
                if ($isActivePackage) {
                    $catHasActiveChild = true;
                }

                // Build page links for this package
                $pageItems = [];
                foreach ($pkg['pages'] ?? [] as $page) {
                    if (preg_match('/\{[^}]+\}/', $page['route'] ?? '')) {
                        continue;
                    }

                    $pageId = $page['id'] ?? 'index';

                    if (!empty($accessiblePages)) {
                        if (isset($accessiblePages[$pageId]) && !$accessiblePages[$pageId]['accessible']) {
                            continue;
                        }
                    }

                    $pageRoute = trim($page['route'] ?? '/', '/');
                    $pageUrl = '/management/package.php?id=' . urlencode($pkg['package_id']);
                    if ($pageRoute && $pageRoute !== '/') {
                        $pageUrl .= '&page=' . urlencode($page['id']);
                    }

                    $isActivePage = $isActivePackage && (
                        $page['id'] === $activePageId ||
                        (!$activePageId && ($page['id'] === 'index' || $page['route'] === '/'))
                    );

                    $pageItems[] = [
                        'id' => $pkg['package_id'] . ':' . $pageId,
                        'label' => $page['title'] ?? ucfirst($pageId),
                        'url' => $pageUrl,
                        'active' => $isActivePage,
                    ];
                }

                $subItems[] = [
                    'id' => 'nav:' . $pkg['package_id'],
                    'type' => 'expandable',
                    'label' => $pkg['name'],
                    'icon' => $pkg['icon'] ?? 'fas fa-box',
                    'expanded' => $isActivePackage,
                    'submenu' => $pageItems,
                ];
            }

            $items[] = [
                'id' => 'cat:' . $catKey,
                'type' => 'expandable',
                'icon' => $catInfo['icon'],
                'label' => $catInfo['label'],
                'expanded' => $catHasActiveChild,
                'submenu' => $subItems,
            ];
        }

        // ── Legacy section nav items ─────────────────────────────────
        if (!empty($sections)) {
            $items[] = ['type' => 'divider'];

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
                    'icon' => $section['icon'] ?? 'fas fa-folder',
                    'label' => $section['name'],
                    'badge' => $badge,
                    'active' => ($section['slug'] === $currentSlug),
                ];
            }
        }

        return $items;
    }

    /**
     * Guess an appropriate icon for a package page based on its ID/title
     */
    public static function guessPageIcon(string $pageId, string $title = ''): string
    {
        $id = strtolower($pageId);
        $t = strtolower($title);

        if (in_array($id, ['index', 'home', 'dashboard', 'list'])) return 'fas fa-th-large';
        if (str_contains($id, 'add') || str_contains($t, 'add')) return 'fas fa-plus-circle';
        if (str_contains($id, 'import') || str_contains($t, 'import')) return 'fas fa-upload';
        if (str_contains($id, 'export') || str_contains($t, 'export')) return 'fas fa-download';
        if (str_contains($id, 'view') || str_contains($id, 'detail')) return 'fas fa-eye';
        if (str_contains($id, 'edit')) return 'fas fa-pencil-alt';
        if (str_contains($id, 'setting')) return 'fas fa-cog';
        if (str_contains($id, 'report')) return 'fas fa-chart-bar';
        if (str_contains($id, 'search') || str_contains($t, 'search')) return 'fas fa-search';

        return 'fas fa-file-alt';
    }

    /**
     * Helper: Build nav items array for Admin Dashboard
     * Google Admin-style: expandable groups with real route sub-pages (no tabs!)
     *
     * @param string $userRole User's role for permission checking
     * @param string $currentPath Current URL path for active state detection
     * @return array Nav items
     */
    public static function buildAdminNavItems($userRole, $currentPath = '/admin/')
    {
        $isSuperAdmin = ($userRole === 'super_admin');
        $isAdmin = in_array($userRole, ['super_admin', 'admin']);
        $currentPath = strtok($currentPath, '?'); // strip query string

        $items = [];

        // Home
        $items[] = [
            'id' => 'home',
            'type' => 'link',
            'url' => '/admin/',
            'icon' => 'fas fa-home',
            'label' => 'Home',
        ];

        // Users (expandable)
        if ($isAdmin) {
            $usersSubmenu = [
                [
                    'id' => 'users-active',
                    'label' => 'Active Users',
                    'url' => '/admin/users',
                    'active' => ($currentPath === '/admin/users' || $currentPath === '/admin/users/')
                ],
                [
                    'id' => 'users-pending',
                    'label' => 'Pending Approvals',
                    'url' => '/admin/users/pending',
                    'active' => (strpos($currentPath, '/admin/users/pending') === 0)
                ],
                [
                    'id' => 'users-invitations',
                    'label' => 'Invitations',
                    'url' => '/admin/users/invitations',
                    'active' => (strpos($currentPath, '/admin/users/invitations') === 0)
                ],
            ];
            if ($isSuperAdmin) {
                $usersSubmenu[] = [
                    'id' => 'users-roles',
                    'label' => 'Organization Roles',
                    'url' => '/admin/roles',
                    'active' => (strpos($currentPath, '/admin/roles') === 0)
                ];
            }

            $items[] = [
                'id' => 'users',
                'type' => 'expandable',
                'url' => '/admin/users',
                'icon' => 'fas fa-users',
                'label' => 'Users',
                'submenu' => $usersSubmenu,
                'permission' => ['super_admin', 'admin'],
            ];
        }

        // Package Management (expandable)
        if ($isAdmin) {
            $packagesSubmenu = [
                [
                    'id' => 'packages-available',
                    'label' => 'Available',
                    'url' => '/admin/packages/available',
                    'active' => (strpos($currentPath, '/admin/packages/available') === 0 || $currentPath === '/admin/packages' || $currentPath === '/admin/packages/')
                ],
                [
                    'id' => 'packages-installed',
                    'label' => 'Installed',
                    'url' => '/admin/packages/installed',
                    'active' => (strpos($currentPath, '/admin/packages/installed') === 0)
                ],
                [
                    'id' => 'packages-updates',
                    'label' => 'Updates',
                    'url' => '/admin/packages/updates',
                    'active' => (strpos($currentPath, '/admin/packages/updates') === 0)
                ],
                [
                    'id' => 'packages-configure',
                    'label' => 'Configure',
                    'url' => '/admin/packages/configure',
                    'active' => (strpos($currentPath, '/admin/packages/configure') === 0)
                ],
            ];

            $items[] = [
                'id' => 'packages',
                'type' => 'expandable',
                'url' => '/admin/packages',
                'icon' => 'fas fa-box',
                'label' => 'Package Management',
                'submenu' => $packagesSubmenu,
                'permission' => ['super_admin', 'admin'],
            ];
        }

        // Settings (expandable, super admin only)
        if ($isSuperAdmin) {
            $items[] = [
                'id' => 'settings',
                'type' => 'expandable',
                'url' => '/admin/settings',
                'icon' => 'fas fa-cog',
                'label' => 'Settings',
                'submenu' => [
                    [
                        'id' => 'settings-general',
                        'label' => 'General',
                        'url' => '/admin/settings/general',
                        'active' => (strpos($currentPath, '/admin/settings/general') === 0 || $currentPath === '/admin/settings' || $currentPath === '/admin/settings/')
                    ],
                    [
                        'id' => 'settings-auth',
                        'label' => 'Authentication',
                        'url' => '/admin/settings/auth',
                        'active' => (strpos($currentPath, '/admin/settings/auth') === 0)
                    ],
                    [
                        'id' => 'settings-modules',
                        'label' => 'Modules',
                        'url' => '/admin/settings/modules',
                        'active' => (strpos($currentPath, '/admin/settings/modules') === 0)
                    ],
                    [
                        'id' => 'settings-theme',
                        'label' => 'Theme',
                        'url' => '/admin/settings/theme',
                        'active' => (strpos($currentPath, '/admin/settings/theme') === 0)
                    ],
                    [
                        'id' => 'settings-layout',
                        'label' => 'Layout',
                        'url' => '/admin/settings/layout',
                        'active' => (strpos($currentPath, '/admin/settings/layout') === 0)
                    ],
                ],
                'permission' => ['super_admin'],
            ];
        }

        // Activity Logs (super admin only)
        if ($isSuperAdmin) {
            $items[] = [
                'id' => 'logs',
                'type' => 'link',
                'url' => '/admin/logs',
                'icon' => 'fas fa-list-alt',
                'label' => 'Activity Logs',
                'permission' => ['super_admin'],
            ];
        }

        // Export Data
        if ($isAdmin) {
            $items[] = [
                'id' => 'export',
                'type' => 'link',
                'url' => '/admin/export',
                'icon' => 'fas fa-download',
                'label' => 'Export Data',
                'permission' => ['super_admin', 'admin'],
            ];
        }

        return $items;
    }
}
