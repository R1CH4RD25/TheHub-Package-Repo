<?php

namespace Hub;

/**
 * Centralized Layout Renderer
 * Provides consistent header and footer across Hub and Admin Dashboard
 */
class Layout
{
    /**
     * Render the page header/navbar
     *
     * @param array $user Current user data
     * @param string $userRole User's effective role
     * @param string $pageType 'hub' or 'dashboard'
     * @param array $options Additional options (e.g., mobile menu items for dashboard)
     */
    public static function renderHeader($user, $userRole, $pageType = 'hub', $options = [])
    {
        $showDashboardLink = in_array($userRole, ['super_admin', 'admin']);

        // Determine subtitle based on page type
        if ($pageType === 'dashboard') {
            $subtitle = 'Admin Dashboard';
        } else {
            $subtitle = SiteSettings::get('navbar_subtitle', SiteSettings::get('site_name', 'The Hub'));
        }

?>
        <!-- Header -->
        <nav class="navbar">
            <div class="nav-content">
                <a href="/" class="nav-brand">
                    <img src="<?php echo e(SiteSettings::get('logo_path', '/assets/images/branding/Branding_NoBG.png')); ?>"
                        alt="<?php echo e(SiteSettings::get('organization_name', 'Your Organization')); ?>">
                    <div class="nav-brand-text">
                        <div class="nav-brand-title"><?php echo e(SiteSettings::get('organization_name', 'Your Organization')); ?></div>
                        <?php if (SiteSettings::get('header_show_subtitle', '1') === '1' || SiteSettings::get('header_show_subtitle', '1') === true): ?>
                            <div class="nav-brand-subtitle"><?php echo e($subtitle); ?></div>
                        <?php endif; ?>
                    </div>
                </a>

                <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="nav-links" id="navLinks">
                    <?php
                    // Always show The Hub link (static menu - always visible)
                    $siteName = SiteSettings::get('site_name', 'The Hub');
                    $isOnHub = ($pageType === 'hub');
                    echo '<a href="/"' . ($isOnHub ? ' class="active"' : '') . '>' . e($siteName) . '</a>';

                    // Check if user has Management System access
                    $showManagement = false;
                    if (isset($user['id'])) {
                        try {
                            // Check if Module class exists and user has command access
                            if (class_exists('\Hub\Module')) {
                                $moduleClass = new \Hub\Module();
                                $showManagement = $moduleClass->hasAccess($user['id'], 'command') ||
                                    in_array($userRole, ['super_admin', 'admin']);
                            } else {
                                // Fallback: Show for admin/super_admin only
                                $showManagement = in_array($userRole, ['super_admin', 'admin']);
                            }
                        } catch (Exception $e) {
                            // Silently fail and show for admin/super_admin only
                            $showManagement = in_array($userRole, ['super_admin', 'admin']);
                        }
                    }

                    // Show Management link if user has access (static menu - always visible)
                    if ($showManagement) {
                        $mgmtName = SiteSettings::get('cc_display_name', 'Management');
                        $mgmtIcon = SiteSettings::get('cc_icon', 'bi-kanban');
                        $isOnCommand = ($pageType === 'command');
                        echo '<a href="/management/"' . ($isOnCommand ? ' class="active"' : '') . '><i class="' . \Hub\Helpers::safeIconClass($mgmtIcon) . '"></i> ' . e($mgmtName) . '</a>';
                    }

                    // Show Admin Dashboard link if user is admin or super_admin (static menu - always visible)
                    if ($showDashboardLink) {
                        $isOnDashboard = ($pageType === 'dashboard');
                        echo '<a href="/admin/"' . ($isOnDashboard ? ' class="active"' : '') . '><i class="bi bi-speedometer2"></i> Admin Dashboard</a>';
                    }
                    ?>

                    <?php self::renderUserProfile($user, $userRole); ?>

                    <?php if ($pageType === 'dashboard' && !empty($options['mobileMenuItems'])): ?>
                        <!-- Mobile Admin Menu (only visible on mobile) -->
                        <div class="mobile-admin-menu">
                            <?php foreach ($options['mobileMenuItems'] as $item): ?>
                                <a href="#" data-tab="<?php echo e($item['tab']); ?>"
                                    class="mobile-tab-link <?php echo $item['active'] ? 'active' : ''; ?>">
                                    <?php echo e($item['label']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <?php
        // Show maintenance mode banner for admins
        if (($_ENV['MAINTENANCE_MODE'] ?? 'false') === 'true'):
        ?>
            <a href="/admin/#settings-advanced"
                class="maintenance-banner"
                id="maintenanceBanner">
                🔧 <strong>MAINTENANCE MODE ACTIVE</strong> - The system is currently unavailable to regular users. Click here to disable.
            </a>
        <?php endif; ?>

        <script nonce="<?php echo CSP_NONCE; ?>">
            // User dropdown menu toggle with ARIA support
            const userDropdownTrigger = document.getElementById('userDropdownTrigger');
            const userDropdownMenu = document.getElementById('userDropdownMenu');

            if (userDropdownTrigger && userDropdownMenu) {
                userDropdownTrigger.addEventListener('click', function(event) {
                    event.stopPropagation();
                    const isExpanded = userDropdownMenu.classList.toggle('show');
                    userDropdownTrigger.setAttribute('aria-expanded', isExpanded);
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const dropdown = document.querySelector('.nav-user-dropdown');
                if (dropdown && userDropdownMenu && !dropdown.contains(event.target)) {
                    userDropdownMenu.classList.remove('show');
                    if (userDropdownTrigger) {
                        userDropdownTrigger.setAttribute('aria-expanded', 'false');
                    }
                }
            });

            // Maintenance banner navigation (if present)
            const maintenanceBanner = document.getElementById('maintenanceBanner');
            if (maintenanceBanner) {
                maintenanceBanner.addEventListener('click', function(e) {
                    e.preventDefault();
                    localStorage.setItem('openAdvancedSection', 'app');
                    if (window.location.pathname === '/admin/' || window.location.pathname === '/admin/index.php') {
                        setTimeout(() => {
                            const advancedTab = document.querySelector('[data-tab=settings]');
                            if (advancedTab) advancedTab.click();
                            setTimeout(() => {
                                const advancedSubtab = document.querySelector('[data-subtab=advanced]');
                                if (advancedSubtab) advancedSubtab.click();
                                setTimeout(() => {
                                    const appSection = document.querySelector('[data-section=app]');
                                    if (appSection && appSection.classList.contains('collapsed')) {
                                        appSection.previousElementSibling.click();
                                    }
                                    document.getElementById('maintenanceMode')?.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'center'
                                    });
                                }, 300);
                            }, 300);
                        }, 100);
                        return false;
                    } else {
                        window.location.href = '/admin/#settings-advanced';
                    }
                });
            }

            // Mobile menu toggle with CSS-based body lock (safer than inline styles)
            const navToggle = document.getElementById('navToggle');
            const navLinks = document.getElementById('navLinks');

            if (navToggle && navLinks) {
                navToggle.addEventListener('click', function() {
                    navToggle.classList.toggle('active');
                    navLinks.classList.toggle('active');
                    document.body.classList.toggle('nav-open');
                });

                // Close mobile menu when clicking a link
                navLinks.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', function() {
                        navToggle.classList.remove('active');
                        navLinks.classList.remove('active');
                        document.body.classList.remove('nav-open');
                    });
                });
            }
        </script>
    <?php
    }

    /**
     * Render the page footer
     *
     * @param array $user Current user data
     * @param string $pageType 'hub' or 'dashboard'
     */
    public static function renderFooter($user, $pageType = 'hub')
    {
        $siteName = SiteSettings::get('site_name', 'The Hub');
        $pageLabel = ($pageType === 'dashboard') ? 'Admin Dashboard' : $siteName;

    ?>
        <!-- Footer -->
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-left">
                    <span>&copy; <?php echo date('Y'); ?> <span id="footerOrganizationName"><?php echo e(SiteSettings::get('organization_name', 'Your Organization')); ?></span></span>
                    <span class="footer-separator">•</span>
                    <span><?php echo e($pageLabel); ?></span>
                    <?php if (SiteSettings::get('footer_custom_text')): ?>
                        <span class="footer-separator" id="footerCustomSeparator">•</span>
                        <span id="footerCustomTextDisplay"><?php echo e(SiteSettings::get('footer_custom_text')); ?></span>
                    <?php endif; ?>
                </div>
                <div class="footer-right">
                    <?php if (SiteSettings::get('footer_show_version', true)): ?>
                        <span id="footerVersionDisplay">Version 1.0</span>
                        <?php if (SiteSettings::get('footer_show_user', true)): ?>
                            <span class="footer-separator" id="footerUserSeparator">•</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (SiteSettings::get('footer_show_user', true)): ?>
                        <span id="footerUserDisplay">Logged in as <strong><?php echo e($user['name']); ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
        </footer>

        <?php
        // Auto-initialize modern frontend libraries
        self::renderModernInit();
        ?>
    <?php
    }

    /**
     * Get modern frontend library includes
     *
     * @param bool $useLocalBundle Use local webpack bundle instead of CDN
     * @return string HTML link/script tags for modern libraries
     */
    /**
     * Get smart-loaded libraries based on page type
     * Only loads what's actually needed instead of 59 libraries on every page
     *
     * @param string $pageType 'hub', 'dashboard', 'section', or 'login'
     * @return string HTML for library includes
     */
    public static function getModernLibraries($pageType = 'hub')
    {
        $libraries = [];

        // ===== CORE LIBRARIES (Always Loaded) =====
        // Bootstrap 5.3.3 - Used everywhere for layout/modals/forms
        $libraries[] = "<link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\" integrity=\"sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH\" crossorigin=\"anonymous\">";
        $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\" integrity=\"sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz\" crossorigin=\"anonymous\"></script>";

        // Bootstrap Icons 1.11.3 - Icons throughout app
        $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css\">";

        // FontAwesome 6.5.1 - Additional icons
        $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css\" integrity=\"sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==\" crossorigin=\"anonymous\" referrerpolicy=\"no-referrer\">";

        // SweetAlert2 11.10.8 - Confirmations/alerts everywhere
        $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.all.min.js\"></script>";
        $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.min.css\">";

        // MicroModal.js 0.4.10 - Lightweight, accessible modals (1.9KB gzipped)
        $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/micromodal@0.4.10/dist/micromodal.min.js\"></script>";

        // Axios 1.6.8 - All AJAX requests
        $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/axios@1.6.8/dist/axios.min.js\"></script>";

        // AOS (Animate On Scroll) - DISABLED (causing animation conflicts)
        // $libraries[] = "<link href=\"https://unpkg.com/aos@2.3.4/dist/aos.css\" rel=\"stylesheet\">";
        // $libraries[] = "<script src=\"https://unpkg.com/aos@2.3.4/dist/aos.js\"></script>";

        // Alpine.js 3.14.1 - Reactive components (lightweight, 15KB)
        $libraries[] = "<script defer src=\"https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js\"></script>";

        // HTMX 1.9.12 - Dynamic HTML updates (lightweight, 14KB)
        $libraries[] = "<script src=\"https://unpkg.com/htmx.org@1.9.12\"></script>";

        // Typed.js 2.1.0 - Typewriter effect (login page)
        $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/typed.js@2.1.0/dist/typed.umd.js\"></script>";

        // ===== PAGE-SPECIFIC LIBRARIES =====
        if ($pageType === 'dashboard' || $pageType === 'command') {
            // Admin Dashboard & Command Center Libraries
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/notyf@3.10.0/notyf.min.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/notyf@3.10.0/notyf.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/dayjs@1.11.10/dayjs.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/dayjs@1.11.10/plugin/relativeTime.js\"></script>";

            // jQuery (required for DataTables)
            $libraries[] = "<script src=\"https://code.jquery.com/jquery-3.7.1.min.js\" integrity=\"sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=\" crossorigin=\"anonymous\"></script>";

            // DataTables (for Command Center)
            if ($pageType === 'command') {
                $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css\">";
                $libraries[] = "<script src=\"https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js\"></script>";
                $libraries[] = "<script src=\"https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js\"></script>";
            }

            // Charts (lazy-load via data-requires="chart")
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/apexcharts@3.48.0/dist/apexcharts.min.js\"></script>";

            // Date pickers (lazy-load via data-requires=\"datepicker\")
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js\"></script>";

            // File uploads (lazy-load via data-requires=\"upload\")
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css\">";
            $libraries[] = "<script src=\"https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone-min.js\"></script>";

            // Multi-select dropdowns (lazy-load via data-requires=\"multiselect\")
            $libraries[] = "<link href=\"https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css\" rel=\"stylesheet\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js\"></script>";

            // Sortable tables/lists (lazy-load via data-requires=\"sortable\")
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js\"></script>";

            // Input formatting (lazy-load via data-requires=\"format\")
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js\"></script>";

            // Code highlighting (lazy-load via data-requires=\"code\")
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css\">";
            $libraries[] = "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js\"></script>";

            // Tooltips (lazy-load via data-requires=\"tooltip\")
            $libraries[] = "<script src=\"https://unpkg.com/@popperjs/core@2\"></script>";
            $libraries[] = "<script src=\"https://unpkg.com/tippy.js@6\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://unpkg.com/tippy.js@6/dist/tippy.css\">";

            // Progress bars for async operations
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/pace-js@1.2.4/pace.min.js\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/pace-js@1.2.4/themes/blue/pace-theme-minimal.css\">";
            $libraries[] = "<script src=\"https://unpkg.com/nprogress@0.2.0/nprogress.js\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://unpkg.com/nprogress@0.2.0/nprogress.css\">";

            // Animation library for transitions
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css\">";
        } elseif ($pageType === 'hub') {
            // Hub Landing Page Libraries
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/vanilla-tilt@1.8.1/dist/vanilla-tilt.min.js\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css\">";
        } elseif ($pageType === 'login') {
            // Login Page Libraries (minimal)
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css\">";
        }

        // ===== MOBILE-OPTIMIZED LIBRARIES (Load only on mobile) =====
        // Detect mobile via user agent (simple check)
        $isMobile = isset($_SERVER['HTTP_USER_AGENT']) &&
            preg_match('/(android|iphone|ipad|mobile)/i', $_SERVER['HTTP_USER_AGENT']);

        if ($isMobile) {
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/hammerjs@2.0.8/hammer.min.js\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/fastclick@1.0.6/lib/fastclick.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/mobile-detect@1.4.5/mobile-detect.min.js\"></script>";
        }

        return implode("\n    ", $libraries);
    }

    /**
     * Get common CSS includes for both Hub and Dashboard
     *
     * @param string $pageType 'hub' or 'dashboard'
     * @return string HTML link tags for stylesheets
     */
    public static function getStylesheets($pageType = 'hub')
    {
        // Check if production mode is enabled
        $useProduction = defined('CSS_PRODUCTION_MODE') && CSS_PRODUCTION_MODE === true;
        $timestamp = time();
        $stylesheets = [];

        if ($useProduction) {
            // Production mode - use minified bundles with version from css-version.json
            $versionFile = __DIR__ . '/../public/assets/css/css-version.json';
            if (file_exists($versionFile)) {
                $versionData = json_decode(file_get_contents($versionFile), true);
                $version = $versionData['version'] ?? $timestamp;
            } else {
                $version = $timestamp;
            }

            // Admin and management use the same bundle
            if ($pageType === 'admin' || $pageType === 'dashboard' || $pageType === 'management' || $pageType === 'command') {
                $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/admin-bundle.min.css?v={$version}\">";
            } else {
                // Default to hub bundle
                $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/hub-bundle.min.css?v={$version}\">";
            }
        } else {
            // Development mode - use concatenated bundles (run ./build-css-bundles.sh to rebuild)
            if ($pageType === 'admin' || $pageType === 'dashboard' || $pageType === 'management' || $pageType === 'command') {
                $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/admin-bundle.css?v={$timestamp}\">";
            } else {
                // Default to hub bundle
                $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/hub-bundle.css?v={$timestamp}\">";
            }
        }

        // Theme CSS LAST to override all defaults
        $stylesheets[] = "<link rel=\"stylesheet\" href=\"/api/theme-css.php?v={$timestamp}\">";

        return implode("\n    ", $stylesheets);
    }

    /**
     * Get the page-specific inline styles
     *
     * @param string $pageType 'hub' or 'dashboard'
     * @return string CSS style block
     */
    public static function getInlineStyles($pageType = 'hub')
    {
        // CSS variables now loaded via /api/theme-css.php link tag
        // Only output dynamic styles that can't be in external CSS
        ob_start();
    ?>
        <style>
            /* Logo glow effects (dynamic, not in static CSS) */
            .nav-brand img {
                <?php echo SiteSettings::getLogoGlowCSS(); ?>
            }

            @media (max-width: 768px) {
                .nav-brand img {
                    <?php echo SiteSettings::getMobileLogoGlowCSS(); ?>
                }
            }
        </style>
    <?php
        return ob_get_clean();
    }

    /**
     * Render the complete HTML head section
     *
     * @param string $pageTitle Page title
     * @param string $pageType 'hub' or 'dashboard'
     * @param bool $useModernLibraries Include modern frontend libraries (default: true)
     */
    public static function renderHead($pageTitle, $pageType = 'hub', $useModernLibraries = true)
    {
    ?>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="<?php echo e($_SESSION['csrf_token'] ?? ''); ?>">
        <meta name="csp-nonce" content="<?php echo CSP_NONCE; ?>">
        <title><?php echo e($pageTitle); ?></title>
        <link rel="icon" type="image/png" href="<?php echo e(SiteSettings::get('favicon_path', '/assets/images/Cowboy_SM_favicon.png')); ?>">
        <?php echo SiteSettings::getGoogleFontsLink(); ?>
        <?php if ($useModernLibraries): ?>
            <?php echo self::getModernLibraries($pageType); ?>
        <?php endif; ?>
        <?php echo self::getStylesheets($pageType); ?>
        <?php echo self::getInlineStyles($pageType); ?>
    <?php
    }

    /**
     * Initialize modern frontend libraries with JavaScript
     * Call this just before closing </body> tag
     */
    public static function renderModernInit()
    {
    ?>
        <script nonce="<?php echo CSP_NONCE; ?>">
            // Initialize The Hub modern components
            if (typeof window.TheHub === 'undefined') {
                // CDN mode - initialize components manually
                window.TheHub = {
                    version: '2.0.0',

                    init() {
                        this.initNotifications();
                        this.initTooltips();
                        this.initAnimations();
                        this.initAxios();
                        console.log('🚀 The Hub initialized (CDN mode)');
                    },

                    initNotifications() {
                        if (typeof Notyf !== 'undefined') {
                            this.notyf = new Notyf({
                                duration: 4000,
                                position: {
                                    x: 'right',
                                    y: 'top'
                                },
                                types: [{
                                        type: 'success',
                                        background: '#28a745',
                                        icon: {
                                            className: 'bi bi-check-circle-fill',
                                            tagName: 'i'
                                        }
                                    },
                                    {
                                        type: 'error',
                                        background: '#dc3545',
                                        icon: {
                                            className: 'bi bi-exclamation-triangle-fill',
                                            tagName: 'i'
                                        }
                                    },
                                    {
                                        type: 'warning',
                                        background: '#ffc107',
                                        icon: {
                                            className: 'bi bi-exclamation-circle-fill',
                                            tagName: 'i'
                                        }
                                    },
                                    {
                                        type: 'info',
                                        background: '#17a2b8',
                                        icon: {
                                            className: 'bi bi-info-circle-fill',
                                            tagName: 'i'
                                        }
                                    }
                                ]
                            });
                        }
                    },

                    initTooltips() {
                        if (typeof bootstrap !== 'undefined') {
                            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                            [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
                        }
                    },

                    initAnimations() {
                        if (typeof AOS !== 'undefined') {
                            AOS.init({
                                duration: 800,
                                easing: 'cubic-bezier(0.4, 0.0, 0.2, 1)', // MD3 motion-standard
                                once: true,
                                offset: 100
                            });
                        }
                    },

                    initAxios() {
                        if (typeof axios !== 'undefined') {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                            if (csrfToken) {
                                axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
                            }
                            axios.defaults.headers.common['Content-Type'] = 'application/json';
                            axios.interceptors.response.use(
                                response => response,
                                error => {
                                    if (error.response?.status === 401) {
                                        window.location.href = '/login.php';
                                    }
                                    return Promise.reject(error);
                                }
                            );
                        }
                    },

                    notify(message, type = 'success') {
                        if (this.notyf) {
                            this.notyf.open({
                                type,
                                message
                            });
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: type,
                                text: message,
                                toast: true,
                                position: 'top-end',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        } else {
                            alert(message);
                        }
                    },

                    async confirm(title, text, confirmText = 'Confirm') {
                        if (typeof Swal !== 'undefined') {
                            const result = await Swal.fire({
                                title,
                                text,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: confirmText,
                                cancelButtonText: 'Cancel',
                                customClass: {
                                    confirmButton: 'btn btn-primary me-2',
                                    cancelButton: 'btn btn-secondary'
                                },
                                buttonsStyling: false
                            });
                            return result.isConfirmed;
                        }
                        return confirm(text);
                    },

                    showLoading(title = 'Processing...') {
                        console.log('🔄 TheHub.showLoading() called with title:', title);
                        console.log('   - Swal exists?', typeof Swal !== 'undefined');
                        if (typeof Swal !== 'undefined') {
                            console.log('✅ Calling Swal.fire() to show loading modal');
                            Swal.fire({
                                title,
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                showConfirmButton: false,
                                didOpen: () => {
                                    console.log('✅ Swal modal opened, calling Swal.showLoading()');
                                    Swal.showLoading();
                                }
                            });
                            console.log('✅ Swal.fire() completed');
                        } else {
                            console.log('❌ Swal not available, cannot show loading modal');
                        }
                    },

                    closeLoading() {
                        console.log('🚪 TheHub.closeLoading() called');
                        console.log('   - Swal exists?', typeof Swal !== 'undefined');
                        if (typeof Swal !== 'undefined') {
                            console.log('✅ Calling Swal.close()');
                            Swal.close();
                            console.log('✅ Swal.close() completed');
                        } else {
                            console.log('❌ Swal not available, cannot close loading modal');
                        }
                    }
                };

                // Initialize when ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => window.TheHub.init());
                } else {
                    window.TheHub.init();
                }
            }
        </script>
<?php
    }

    /**
     * Render User Profile Dropdown Component (Shared across Hub/Management/Admin)
     * Wrapper method that delegates to standalone component
     *
     * @param array $user Current user data with keys: id, name, picture, role
     * @param string $userRole User's effective role (handles "view as" mode)
     * @return void Outputs HTML directly
     */
    public static function renderUserProfile($user, $userRole)
    {
        // Component is autoloaded via PSR-4, no need for require_once
        \Hub\Components\UserProfileDropdown::render($user, $userRole);
    }
}
