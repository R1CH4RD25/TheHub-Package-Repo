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
                
                <?php if ($pageType === 'dashboard'): ?>
                    <button class="nav-toggle" id="navToggle">☰</button>
                <?php endif; ?>
                
                <div class="nav-links" id="navLinks">
                    <?php if ($pageType === 'hub' && $showDashboardLink): ?>
                        <a href="/admin/">Dashboard</a>
                    <?php elseif ($pageType === 'dashboard'): ?>
                        <a href="/">Back to The Hub</a>
                    <?php endif; ?>
                    
                    <a href="/logout.php">Logout</a>
                    
                    <div class="nav-user-profile">
                        <?php if (!empty($user['picture'])): ?>
                            <img src="<?php echo e($user['picture']); ?>" alt="<?php echo e($user['name']); ?>" class="nav-user-avatar">
                        <?php else: ?>
                            <img src="/assets/images/default-avatar.svg" alt="Default Avatar" class="nav-user-avatar">
                        <?php endif; ?>
                        <div class="nav-user-info">
                            <div class="nav-user-name"><?php echo e($user['name']); ?></div>
                            <div class="nav-user-role"><?php echo ucfirst(str_replace('_', ' ', $userRole)); ?></div>
                        </div>
                    </div>
                    
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
        <a href="/admin/#settings-advanced" class="maintenance-banner" onclick="
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
                            document.getElementById('maintenanceMode')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }, 300);
                    }, 300);
                }, 100);
                return false;
            }
        ">
            🔧 <strong>MAINTENANCE MODE ACTIVE</strong> - The system is currently unavailable to regular users. Click here to disable.
        </a>
        <?php endif; ?>
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
        <footer class="admin-footer">
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
    public static function getModernLibraries($useLocalBundle = false)
    {
        $libraries = [];
        
        if ($useLocalBundle && file_exists(__DIR__ . '/../public/assets/dist/vendor.bundle.js')) {
            // Use local webpack bundle (faster, self-hosted)
            $libraries[] = "<script src=\"/assets/dist/vendor.bundle.js\"></script>";
            $libraries[] = "<script src=\"/assets/dist/app.bundle.js\"></script>";
        } else {
            // Use CDN fallback (always available)
            // Bootstrap 5.3.3
            $libraries[] = "<link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\" integrity=\"sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH\" crossorigin=\"anonymous\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js\" integrity=\"sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz\" crossorigin=\"anonymous\"></script>";
            
            // Bootstrap Icons 1.11.3
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css\">";
            
            // Alpine.js 3.14.1
            $libraries[] = "<script defer src=\"https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js\"></script>";
            
            // HTMX 1.9.12
            $libraries[] = "<script src=\"https://unpkg.com/htmx.org@1.9.12\"></script>";
            
            // SweetAlert2 11.10.8
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.all.min.js\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/sweetalert2@11.10.8/dist/sweetalert2.min.css\">";
            
            // AOS (Animate On Scroll) 2.3.4
            $libraries[] = "<link href=\"https://unpkg.com/aos@2.3.4/dist/aos.css\" rel=\"stylesheet\">";
            $libraries[] = "<script src=\"https://unpkg.com/aos@2.3.4/dist/aos.js\"></script>";
            
            // Chart.js 4.4.2
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js\"></script>";
            
            // ApexCharts 3.48.0
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/apexcharts@3.48.0/dist/apexcharts.min.js\"></script>";
            
            // Flatpickr 4.6.13
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js\"></script>";
            
            // Tom Select 2.3.1
            $libraries[] = "<link href=\"https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css\" rel=\"stylesheet\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js\"></script>";
            
            // Notyf 3.10.0
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/notyf@3.10.0/notyf.min.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/notyf@3.10.0/notyf.min.js\"></script>";
            
            // Axios 1.6.8
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/axios@1.6.8/dist/axios.min.js\"></script>";
            
            // Day.js 1.11.10
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/dayjs@1.11.10/dayjs.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/dayjs@1.11.10/plugin/relativeTime.js\"></script>";
            
            // Lodash 4.17.21
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js\"></script>";
            
            // Tippy.js 6.3.7
            $libraries[] = "<script src=\"https://unpkg.com/@popperjs/core@2\"></script>";
            $libraries[] = "<script src=\"https://unpkg.com/tippy.js@6\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://unpkg.com/tippy.js@6/dist/tippy.css\">";
            
            // ===== BONUS MODERN LIBRARIES =====
            
            // Sortable.js 1.15.2 - Drag & drop reordering
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js\"></script>";
            
            // Cleave.js 1.6.0 - Input formatting (phone, credit card, dates)
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/cleave.js@1.6.0/dist/cleave.min.js\"></script>";
            
            // Animate.css 4.1.1 - CSS animation library
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css\">";
            
            // Pace.js 1.2.4 - Automatic page loading progress bar
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/pace-js@1.2.4/pace.min.js\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/pace-js@1.2.4/themes/blue/pace-theme-minimal.css\">";
            
            // NProgress 0.2.0 - Slim progress bar (alternative to Pace)
            $libraries[] = "<script src=\"https://unpkg.com/nprogress@0.2.0/nprogress.js\"></script>";
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://unpkg.com/nprogress@0.2.0/nprogress.css\">";
            
            // Particles.js 2.0.0 - Animated particle backgrounds
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js\"></script>";
            
            // Typed.js 2.1.0 - Typing animation
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/typed.js@2.1.0/dist/typed.umd.js\"></script>";
            
            // CountUp.js 2.8.0 - Number animations
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/countup.js@2.8.0/dist/countUp.umd.min.js\"></script>";
            
            // Vanilla Tilt 1.8.1 - 3D tilt effect on hover
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/vanilla-tilt@1.8.1/dist/vanilla-tilt.min.js\"></script>";
            
            // Prism.js 1.29.0 - Syntax highlighting for code blocks
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css\">";
            $libraries[] = "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js\"></script>";
            
            // Dropzone.js 6.0.0 - Drag & drop file uploads
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone.css\">";
            $libraries[] = "<script src=\"https://unpkg.com/dropzone@6.0.0-beta.2/dist/dropzone-min.js\"></script>";
            
            // Masonry 4.2.2 - Pinterest-style grid layouts
            $libraries[] = "<script src=\"https://unpkg.com/masonry-layout@4.2.2/dist/masonry.pkgd.min.js\"></script>";
            
            // ImagesLoaded 5.0.0 - Detect when images are loaded
            $libraries[] = "<script src=\"https://unpkg.com/imagesloaded@5.0.0/imagesloaded.pkgd.min.js\"></script>";
            
            // Micromodal 0.4.10 - Accessible modal dialogs
            $libraries[] = "<script src=\"https://unpkg.com/micromodal@0.4.10/dist/micromodal.min.js\"></script>";
            
            // A11y Dialog 8.0.3 - Accessible dialog component
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/a11y-dialog@8.0.3/dist/a11y-dialog.min.js\"></script>";
            
            // Choices.js 10.2.0 - Select boxes & multi-select (lighter alternative to Tom Select)
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/styles/choices.min.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/scripts/choices.min.js\"></script>";
            
            // Driver.js 1.3.1 - Feature tours & highlights
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.js.iife.js\"></script>";
            
            // Shepherd.js 11.2.0 - User onboarding tours
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/css/shepherd.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/shepherd.js@11.2.0/dist/js/shepherd.min.js\"></script>";
            
            // ===== MOBILE-SPECIFIC LIBRARIES =====
            
            // Hammer.js 2.0.8 - Touch gestures (swipe, pinch, tap, etc.)
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/hammerjs@2.0.8/hammer.min.js\"></script>";
            
            // Swiper 11.0.5 - Mobile touch slider/carousel
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js\"></script>";
            
            // PulltoRefresh.js 0.1.22 - Pull to refresh for mobile
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/pulltorefreshjs@0.1.22/dist/index.umd.js\"></script>";
            
            // FastClick 1.0.6 - Eliminate 300ms tap delay on mobile
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/fastclick@1.0.6/lib/fastclick.min.js\"></script>";
            
            // iNoBounce 0.2.0 - Disable iOS rubber band scrolling
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/inobounce@0.2.0/inobounce.min.js\"></script>";
            
            // Mobile Detect (via CDN) - Detect mobile devices
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/mobile-detect@1.4.5/mobile-detect.min.js\"></script>";
            
            // PhotoSwipe 5.4.3 - Mobile-friendly image gallery
            $libraries[] = "<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/photoswipe.css\">";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe.umd.min.js\"></script>";
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/photoswipe@5.4.3/dist/umd/photoswipe-lightbox.umd.min.js\"></script>";
            
            // Lottie 5.12.2 - Render animations (mobile-optimized)
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js\"></script>";
            
            // QRCode.js 1.0.0 - Generate QR codes
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js\"></script>";
            
            // Vibrant.js 1.0.0 - Extract colors from images
            $libraries[] = "<script src=\"https://cdn.jsdelivr.net/npm/node-vibrant@3.2.1-alpha.1/dist/vibrant.min.js\"></script>";
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
        
        if ($useProduction) {
            // Use single combined production.css file (minified if available)
            $versionFile = __DIR__ . '/../public/assets/css/version.txt';
            $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : time();
            
            // Use minified version if it exists, otherwise fall back to unminified
            $minifiedPath = __DIR__ . '/../public/assets/css/production.min.css';
            $cssFile = file_exists($minifiedPath) ? 'production.min.css' : 'production.css';
            
            $stylesheets = [];
            $stylesheets[] = "<link rel=\"stylesheet\" href=\"/api/theme-css.php?v={$version}\">";
            $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/{$cssFile}?v={$version}\">";
            
            return implode("\n    ", $stylesheets);
        }
        
        // Development mode - individual files for easier debugging
        $timestamp = time();
        $stylesheets = [];
        
        // Common stylesheets for all pages
        $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/style.css?v={$timestamp}\">";
        $stylesheets[] = "<link rel=\"stylesheet\" href=\"/api/theme-css.php?v={$timestamp}\">";
        $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/header.css?v={$timestamp}\">";
        $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/footer.css?v={$timestamp}\">";
        
        // Page-specific stylesheets
        if ($pageType === 'dashboard') {
            $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/admin.css?v={$timestamp}\">";
            $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/admin-theme.css?v={$timestamp}\">";
            $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/admin-colors.css?v={$timestamp}\">";
        } elseif ($pageType === 'hub') {
            $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/hub.css?v={$timestamp}\">";
        }
        
        // Media queries last
        $stylesheets[] = "<link rel=\"stylesheet\" href=\"/assets/css/media.css?v={$timestamp}\">";
        
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
        ob_start();
        ?>
        <style>
            <?php echo SiteSettings::getCSSVariables(); ?>
            
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
        <title><?php echo e($pageTitle); ?></title>
        <link rel="icon" type="image/png" href="<?php echo e(SiteSettings::get('favicon_path', '/assets/images/Cowboy_SM_favicon.png')); ?>">
        <?php echo SiteSettings::getGoogleFontsLink(); ?>
        <?php if ($useModernLibraries): ?>
        <?php echo self::getModernLibraries(); ?>
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
        <script>
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
                            position: { x: 'right', y: 'top' },
                            types: [
                                {
                                    type: 'success',
                                    background: '#28a745',
                                    icon: { className: 'bi bi-check-circle-fill', tagName: 'i' }
                                },
                                {
                                    type: 'error',
                                    background: '#dc3545',
                                    icon: { className: 'bi bi-exclamation-triangle-fill', tagName: 'i' }
                                },
                                {
                                    type: 'warning',
                                    background: '#ffc107',
                                    icon: { className: 'bi bi-exclamation-circle-fill', tagName: 'i' }
                                },
                                {
                                    type: 'info',
                                    background: '#17a2b8',
                                    icon: { className: 'bi bi-info-circle-fill', tagName: 'i' }
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
                            easing: 'ease-in-out',
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
                        this.notyf.open({ type, message });
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: type, text: message, toast: true, position: 'top-end', timer: 3000, showConfirmButton: false });
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
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title,
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => { Swal.showLoading(); }
                        });
                    }
                },
                
                closeLoading() {
                    if (typeof Swal !== 'undefined') {
                        Swal.close();
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
}
