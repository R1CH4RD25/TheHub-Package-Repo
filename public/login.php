<?php

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Auth;
use Hub\SiteSettings;

$auth = new Auth();

// Check which OAuth providers are enabled
$googleEnabled = filter_var($_ENV['ENABLE_GOOGLE_LOGIN'] ?? true, FILTER_VALIDATE_BOOLEAN);
$microsoftEnabled = filter_var($_ENV['ENABLE_MICROSOFT_LOGIN'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Get login URLs
$googleLoginUrl = $googleEnabled ? $auth->getAuthUrl() : null;
$microsoftLoginUrl = null; // TODO: Implement Microsoft OAuth

// Get dynamic branding
$orgName = SiteSettings::get('organization_name', 'Your Organization');
$siteName = SiteSettings::get('site_name', 'The Hub');
$logoPath = SiteSettings::get('logo_path', '/assets/images/branding/Branding_NoBG.png');

// Build login hint based on allowed domains
$allowedDomains = $_ENV['ALLOWED_DOMAINS'] ?? '';
$loginHint = '';
if (!empty($allowedDomains)) {
    $domains = array_map('trim', explode(',', $allowedDomains));
    if (count($domains) === 1) {
        $loginHint = "Use your @{$domains[0]} account";
    } else {
        $loginHint = "Use your authorized account";
    }
} else {
    $loginHint = "Sign in to continue";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo e($siteName); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e(SiteSettings::get('favicon_path', '/assets/images/Cowboy_SM_favicon.png')); ?>">
    <?php 
    use Hub\Layout;
    Layout::renderHead('Login - ' . $siteName, 'login'); 
    ?>
    <link rel="stylesheet" href="/assets/css/login-modern.css?v=<?php echo time(); ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <img src="<?php echo e($logoPath); ?>" alt="<?php echo e($orgName); ?>" style="max-width: 200px; margin-bottom: 1rem;">
                <h1><?php echo e($orgName); ?></h1>
                <h2><?php echo e($siteName); ?></h2>
            </div>
            
            <div class="login-content">
                <?php if ($googleEnabled && $googleLoginUrl): ?>
                <a href="<?php echo e($googleLoginUrl); ?>" class="google-login-btn">
                    <span class="google-icon">
                        <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                            <path fill="none" d="M0 0h48v48H0z"/>
                        </svg>
                    </span>
                    Sign in with Google
                </a>
                <?php endif; ?>
                
                <?php if ($microsoftEnabled && $microsoftLoginUrl): ?>
                <a href="<?php echo e($microsoftLoginUrl); ?>" class="microsoft-login-btn" style="margin-top: 1rem;">
                    <span class="microsoft-icon">
                        <svg width="20" height="20" viewBox="0 0 23 23" xmlns="http://www.w3.org/2000/svg">
                            <path fill="#f25022" d="M0 0h11v11H0z"/>
                            <path fill="#00a4ef" d="M12 0h11v11H12z"/>
                            <path fill="#7fba00" d="M0 12h11v11H0z"/>
                            <path fill="#ffb900" d="M12 12h11v11H12z"/>
                        </svg>
                    </span>
                    Sign in with Microsoft
                </a>
                <?php endif; ?>
                
                <?php if (!$googleEnabled && !$microsoftEnabled): ?>
                <p class="error-message">No authentication providers are enabled. Please contact your administrator.</p>
                <?php endif; ?>
                
                <p class="login-note"><?php echo e($loginHint); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Initialize particles background effect (subtle but visible)
        if (typeof particlesJS !== 'undefined') {
            const particlesDiv = document.createElement('div');
            particlesDiv.id = 'particles-js';
            particlesDiv.style.position = 'absolute';
            particlesDiv.style.width = '100%';
            particlesDiv.style.height = '100%';
            particlesDiv.style.top = '0';
            particlesDiv.style.left = '0';
            particlesDiv.style.zIndex = '0';
            document.querySelector('.login-page').prepend(particlesDiv);
            
            particlesJS('particles-js', {
                particles: {
                    number: { value: 40, density: { enable: true, value_area: 800 } },
                    color: { value: '#C99700' },
                    shape: { type: 'circle' },
                    opacity: { 
                        value: 0.3, 
                        random: false,
                        anim: { enable: false }
                    },
                    size: { 
                        value: 3, 
                        random: true,
                        anim: { enable: false }
                    },
                    line_linked: { 
                        enable: true, 
                        distance: 150, 
                        color: '#C99700', 
                        opacity: 0.2, 
                        width: 1 
                    },
                    move: { 
                        enable: true, 
                        speed: 0.5,
                        direction: 'none', 
                        random: false, 
                        straight: false,
                        out_mode: 'out',
                        bounce: false
                    }
                },
                interactivity: {
                    detect_on: 'canvas',
                    events: {
                        onhover: { enable: true, mode: 'grab' },
                        onclick: { enable: false }
                    },
                    modes: {
                        grab: { 
                            distance: 140, 
                            line_linked: { opacity: 0.4 } 
                        }
                    }
                },
                retina_detect: true
            });
        }

        // Typewriter effect for "THE HUB" subtitle with delay
        if (typeof Typed !== 'undefined') {
            const h2 = document.querySelector('.logo h2');
            if (h2) {
                const originalText = h2.textContent;
                h2.textContent = '';
                h2.style.minHeight = '1.5em'; // Prevent layout shift
                
                // Start typing after card float animation completes (1.5s)
                setTimeout(() => {
                    new Typed('.logo h2', {
                        strings: [originalText],
                        typeSpeed: 60,
                        startDelay: 0,
                        showCursor: false
                    });
                }, 1500);
            }
        }

        // Add click animation to login buttons
        document.querySelectorAll('.google-login-btn, .microsoft-login-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const card = document.querySelector('.login-card');
                card.classList.add('loading');
                
                // Show loading message
                if (typeof TheHub !== 'undefined' && TheHub.showLoading) {
                    TheHub.showLoading('Redirecting to login...');
                }
                
                // Add ripple effect
                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.width = ripple.style.height = '10px';
                ripple.style.background = 'rgba(201, 151, 0, 0.3)';
                ripple.style.borderRadius = '50%';
                ripple.style.left = e.offsetX + 'px';
                ripple.style.top = e.offsetY + 'px';
                ripple.style.transform = 'translate(-50%, -50%)';
                ripple.style.animation = 'ripple 0.6s ease-out';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Add custom ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    width: 500px;
                    height: 500px;
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Keyboard shortcut: Press Enter to trigger first login button
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const loginBtn = document.querySelector('.google-login-btn, .microsoft-login-btn');
                if (loginBtn) loginBtn.click();
            }
        });
    </script>

    <?php Layout::renderModernInit(); ?>
</body>
</html>
