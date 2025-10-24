<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Auth;
use Hub\Module;

Auth::requireLogin();

$user = Auth::getCurrentUser();
$moduleModel = new Module();
$userModules = $moduleModel->getUserModules($user['id']);

// If user only has access to one module, redirect directly
if (count($userModules) === 1) {
    header('Location: ' . $userModules[0]['base_url']);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Woodson ISD - Select Module</title>
    <link rel="icon" type="image/png" href="/assets/images/Cowboy_SM_favicon.png">
    <?php 
    use Hub\Layout;
    Layout::renderHead('Select Module - Woodson ISD', 'modules'); 
    ?>
    <link rel="stylesheet" href="/assets/css/modules-modern.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/assets/css/media.css?v=<?php echo time(); ?>">
</head>
<body class="modules-page">    <link rel="stylesheet" href="/assets/css/media.css?v=<?php echo time(); ?>">
</head>
<body class="modules-page">
    <nav class="navbar">
        <div class="nav-content">
            <a href="/modules.php" class="nav-brand" style="text-decoration: none; color: var(--primary-color);">Woodson ISD</a>
            <button class="nav-toggle" id="navToggle">☰</button>
            <div class="nav-links" id="navLinks">
                <span class="nav-user">Hello, <?php echo e($user['name']); ?></span>
                <a href="/logout.php" class="nav-link btn-nav btn-logout">🚪 Logout</a>
            </div>
        </div>
    </nav>

    <div class="modules-container">
        <div class="modules-header">
            <h1>Welcome to Woodson ISD</h1>
            <p>Select a module to continue</p>
        </div>

        <?php if (empty($userModules)): ?>
            <div class="modules-empty-state">
                <p style="font-size: 1.2rem; color: #666;">You don't have access to any modules yet.</p>
                <p style="margin-top: 1rem;">Please contact your administrator to request access.</p>
            </div>
        <?php else: ?>
            <div class="modules-grid">
                <?php foreach ($userModules as $module): ?>
                    <a href="<?php echo e($module['base_url']); ?>" class="module-card">
                        <div class="module-icon"><?php echo e($module['icon']); ?></div>
                        <div class="module-title"><?php echo e($module['display_name']); ?></div>
                        <div class="module-description"><?php echo e($module['description'] ?? ''); ?></div>
                        <div>
                            <span class="module-role"><?php echo e($module['user_role']); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Initialize AOS (Animate On Scroll)
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 800,
                easing: 'ease-out',
                once: true
            });
        }

        // Add click animation to module cards
        document.querySelectorAll('.module-card').forEach(card => {
            card.addEventListener('click', function(e) {
                this.classList.add('clicked');
                
                // Show loading indicator
                if (typeof TheHub !== 'undefined' && TheHub.showLoading) {
                    TheHub.showLoading('Loading module...');
                }
            });

            // Add AOS attributes
            card.setAttribute('data-aos', 'fade-up');
            card.setAttribute('data-aos-delay', '100');
        });

        // Initialize Vanilla Tilt for 3D effect
        if (typeof VanillaTilt !== 'undefined') {
            VanillaTilt.init(document.querySelectorAll('.module-card'), {
                max: 10,
                speed: 400,
                glare: true,
                'max-glare': 0.4,
                scale: 1.03
            });
        }

        // Add Typed.js to the subtitle (typewriter effect)
        if (typeof Typed !== 'undefined') {
            const subtitleEl = document.querySelector('.modules-header p');
            const originalText = subtitleEl.textContent;
            subtitleEl.textContent = '';
            
            new Typed('.modules-header p', {
                strings: [originalText],
                typeSpeed: 50,
                showCursor: false,
                onComplete: () => {
                    // Welcome notification
                    if (typeof TheHub !== 'undefined' && TheHub.notify) {
                        TheHub.notify('Welcome back! Select a module to continue.', 'info');
                    }
                }
            });
        }

        // Add particles effect to background
        if (typeof particlesJS !== 'undefined') {
            particlesJS('particles-js', {
                particles: {
                    number: { value: 50, density: { enable: true, value_area: 800 } },
                    color: { value: '#ffffff' },
                    opacity: { value: 0.3, random: true },
                    size: { value: 3, random: true },
                    move: { enable: true, speed: 1, direction: 'none', random: true, out_mode: 'out' }
                }
            });
        }

        // Pace.js for page loading
        if (typeof Pace !== 'undefined') {
            Pace.on('done', () => {
                console.log('Page fully loaded!');
            });
        }
    </script>

    <?php Layout::renderModernInit(); ?>
</body>
</html>
