<?php
/**
 * Staff Resources Hub / Student Hub
 * Dynamic landing page that changes based on user role
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\SiteSettings;
use Hub\Layout;

// Require login
Auth::requireLogin();

$user = Auth::getCurrentUser();
$currentUser = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$userGroup = $currentUser['user_group'] ?? 'staff';

// Determine if user should see Student Hub or Staff Hub
// Priority: user_group setting > role-based fallback
$isStudent = ($userGroup === 'student') || ($userRole === 'student');

// Dynamic title and subtitle from site settings
$siteName = SiteSettings::get('site_name', 'The Hub');
$welcomeMessage = SiteSettings::get('welcome_message', 'Central hub for student services and resources');

// Landing page header settings
$landingPageTitle = SiteSettings::get('landing_page_title', 'The Hub');
$showLandingPageIcon = SiteSettings::get('landing_page_show_icon', '1') === '1';

// Use hub tile icon for landing page header
$useCustomIcon = SiteSettings::get('hub_tile_icon_custom_enabled', '0') === '1';
$customIconPath = SiteSettings::get('hub_tile_icon_path', '/assets/images/branding/Branding_NoBG.png');

if ($isStudent) {
    $hubTitle = '📚 Student ' . $landingPageTitle;
    $hubSubtitle = $welcomeMessage;
} else {
    $hubTitle = $landingPageTitle;
    $hubSubtitle = $welcomeMessage;
}

// Get sections user has access to
$db = Database::getInstance();

// If user is super_admin or admin, they see everything
if (in_array($userRole, ['super_admin', 'admin'])) {
    $sections = $db->fetchAll("
        SELECT * FROM sections
        WHERE is_active = TRUE
        AND section_type != 'hub'
        ORDER BY sort_order
    ");
} else {
    // Get sections based on user's role
    $sections = $db->fetchAll("
        SELECT DISTINCT s.*
        FROM sections s
        INNER JOIN section_role_access sra ON s.id = sra.section_id
        WHERE s.is_active = TRUE
        AND s.section_type != 'hub'
        AND sra.role = ?
        ORDER BY s.sort_order
    ", [$userRole]);
}

?>
<!DOCTYPE html>
<html lang="en" class="animations-played">
<head>
    <?php Layout::renderHead($siteName . ' - ' . SiteSettings::get('organization_name', 'Your Organization'), 'hub'); ?>
</head>
<body>
    <div class="page-wrapper hub-page">
        <?php include __DIR__ . '/partials/header.php'; ?>

        <!-- Main Content -->
    <div class="main-content hub-page">
        <div class="hub-container">
            <div class="hub-header">
                <?php if ($showLandingPageIcon && $useCustomIcon): ?>
                    <div class="hub-header-icon">
                        <img src="<?php echo e($customIconPath); ?>" alt="Hub Icon" style="max-width: 120px; max-height: 120px; object-fit: contain;">
                    </div>
                <?php endif; ?>
                <h1><?php echo $hubTitle; ?></h1>
                <p><?php echo $hubSubtitle; ?></p>
            </div>

            <?php if (empty($sections)): ?>
                <div class="no-sections">
                    <h3>No sections available</h3>
                    <p>You don't have access to any sections yet. Please contact an administrator.</p>
                </div>
            <?php else: ?>
                <div class="sections-grid">
                    <?php
                    $useCustomIcon = SiteSettings::get('hub_tile_icon_custom_enabled', '0') === '1';
                    $customIconPath = SiteSettings::get('hub_tile_icon_path', '/assets/images/branding/Branding_NoBG.png');

                    foreach ($sections as $section):
                        // Determine icon to display
                        if ($useCustomIcon) {
                            $iconHtml = '<img src="' . e($customIconPath) . '" alt="Section Icon" style="width: 100%; height: 100%; object-fit: contain;">';
                        } else {
                            // Check if icon is Bootstrap Icons class or emoji
                            if (strpos($section['icon'], 'bi-') === 0) {
                                $iconHtml = '<i class="bi ' . e($section['icon']) . '"></i>';
                            } else {
                                $iconHtml = e($section['icon'] ?: '📦');
                            }
                        }
                        // Truncate description to 100 characters for hub cards
                        $description = $section['description'] ?? '';
                        $truncatedDesc = strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                        $needsTooltip = strlen($description) > 100;
                    ?>
                        <a href="<?php echo e($section['base_url']); ?>" class="section-card">
                            <span class="section-icon"><?php echo $iconHtml; ?></span>
                            <div class="section-title">
                                <?php echo e($section['display_name']); ?>
                                <?php if ($needsTooltip): ?>
                                <i class="bi bi-info-circle section-title-tooltip" data-full-text="<?php echo htmlspecialchars($description, ENT_QUOTES); ?>"></i>
                                <?php endif; ?>
                            </div>
                            <?php if ($truncatedDesc): ?>
                            <div class="section-description">
                                <?php echo e($truncatedDesc); ?>
                            </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <?php include __DIR__ . '/partials/footer.php'; ?>
    </div><!-- end page-wrapper -->

    <script>
        // Initialize AOS (Animate On Scroll) if available
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 800,
                easing: 'ease-out',
                once: true,
                offset: 100
            });
        }

        // Add click animation to section cards
        document.querySelectorAll('.section-card').forEach(card => {
            card.addEventListener('click', function(e) {
                this.classList.add('clicked');
                setTimeout(() => this.classList.remove('clicked'), 300);
            });

            // Add data-aos attributes for scroll animations
            card.setAttribute('data-aos', 'fade-up');
        });

        // Initialize Vanilla Tilt for 3D card effect (if available)
        if (typeof VanillaTilt !== 'undefined') {
            VanillaTilt.init(document.querySelectorAll('.section-card'), {
                max: 8,
                speed: 400,
                glare: true,
                'max-glare': 0.3,
                scale: 1.05
            });
        }

        // Add CountUp animation to any numbers in the header
        if (typeof CountUp !== 'undefined') {
            document.querySelectorAll('[data-countup]').forEach(el => {
                const target = parseInt(el.getAttribute('data-countup'));
                const countUp = new CountUp(el, target, {
                    duration: 2,
                    useEasing: true
                });
                if (!countUp.error) {
                    countUp.start();
                }
            });
        }

        // Page load progress with Pace.js (automatically initialized)
        if (typeof Pace !== 'undefined') {
            Pace.options = {
                ajax: true,
                document: true,
                eventLag: false
            };
        }

        // Add smooth hover sound effect (optional - can be enabled)
        document.querySelectorAll('.section-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                // Optional: Add subtle haptic feedback on mobile
                if (navigator.vibrate) {
                    navigator.vibrate(10);
                }
            });
        });

        // Welcome notification using TheHub
        if (typeof TheHub !== 'undefined' && TheHub.notify) {
            setTimeout(() => {
                TheHub.notify('Welcome to <?php echo e($siteName); ?>! 👋', 'info');
            }, 500);
        }
    </script>

    <!-- SPA Navigation DISABLED - Sections use full page loads -->
    <!-- <script src="/assets/js/spa-navigation.js?v=<?php echo time(); ?>"></script> -->
</body>
</html>
