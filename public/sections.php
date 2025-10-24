<?php
/**
 * DEPRECATED: Redirect to new unified hub.php
 * This file kept for backward compatibility with bookmarks
 */

// Redirect to hub
header('Location: /');
exit;

/**
 * Old Section Selector - Landing page after login
 * Displays all sections/modules user has access to
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Auth;
use Hub\SectionAccess;

// Prevent caching of this page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Require login
Auth::requireLogin();

$user = Auth::getCurrentUser();
$sectionAccess = new SectionAccess();

error_log("=== SECTIONS.PHP: User accessing hub: {$user['email']} (ID: {$user['id']}) ===");

// Get sections user has access to
$sections = $sectionAccess->getUserSections($user['id']);

error_log("=== SECTIONS.PHP: User has " . count($sections) . " section(s) ===");
foreach ($sections as $section) {
    error_log("===   Section: {$section['section_display_name']} ({$section['section_slug']}) - URL: {$section['section_base_url']} ===");
}

// If user has no access, show error
if (count($sections) === 0) {
    $errorMessage = "You don't have access to any sections yet. Please contact an administrator.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose a Section - Woodson ISD</title>
    <link rel="icon" type="image/png" href="/assets/images/Cowboy_SM_favicon.png">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .sections-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .sections-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .sections-header h1 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .sections-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .section-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem 1rem;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            min-height: 180px;
        }

        .section-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(201, 151, 0, 0.2);
            transform: translateY(-4px);
        }

        .section-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .section-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.4rem;
            line-height: 1.3;
        }

        .section-description {
            color: #666;
            font-size: 0.825rem;
            line-height: 1.3;
            margin-bottom: 0.6rem;
            flex-grow: 1;
        }

        .section-role {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 0.2rem 0.65rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: auto;
        }

        .no-access {
            text-align: center;
            padding: 4rem 2rem;
        }

        .no-access-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }

        .no-access h2 {
            color: var(--text-color);
            margin-bottom: 1rem;
        }

        .no-access p {
            color: #666;
            margin-bottom: 2rem;
        }

        .logout-link {
            display: inline-block;
            background: var(--primary-color);
            color: black;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }

        .logout-link:hover {
            background: var(--primary-dark);
        }

        /* Tablet and smaller desktop - 2 columns */
        @media (max-width: 992px) {
            .sections-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.25rem;
            }
        }

        /* Mobile - single column */
        @media (max-width: 600px) {
            .sections-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .sections-container {
                padding: 1rem;
                margin: 1rem auto;
            }

            .sections-header h1 {
                font-size: 1.75rem;
            }

            .sections-header p {
                font-size: 1rem;
            }

            .sections-header {
                margin-bottom: 1.5rem;
            }

            .section-card {
                padding: 1.25rem;
            }

            .section-icon {
                font-size: 2.75rem;
            }

            .section-title {
                font-size: 1.1rem;
            }

            .section-title {
                font-size: 1.25rem;
            }

            .nav-brand img {
                height: 32px !important;
            }
        }
    </style>
    <link rel="stylesheet" href="/assets/css/media.css?v=<?php echo time(); ?>">
</head>
<body>
    <nav class="navbar">
        <div class="nav-content">
            <a href="/sections.php" class="nav-brand" style="text-decoration: none; color: var(--primary-color);">
                <?php if (file_exists(__DIR__ . '/assets/images/branding/Branding_NoBG.png')): ?>
                    <img src="/assets/images/branding/Branding_NoBG.png" alt="Woodson ISD" style="height: 80px; vertical-align: middle;">
                <?php else: ?>
                    🏫 Woodson ISD
                <?php endif; ?>
            </a>
            <button class="nav-toggle" id="navToggle">☰</button>
            <div class="nav-links" id="navLinks">
                <span class="nav-user">Hello, <?php echo e($user['name']); ?></span>
                <?php
                // Show Admin link for users with admin capabilities
                $canAccessAdmin = in_array($user['role'], ['super_admin', 'admin', 'manager']);
                if ($canAccessAdmin): ?>
                    <a href="/admin/" class="nav-link btn-nav" style="background: var(--primary-color); color: black;">Admin</a>
                <?php endif; ?>
                <a href="/logout.php" class="nav-link btn-nav btn-logout">🚪 Logout</a>
            </div>
        </div>
    </nav>

    <div class="sections-container">
        <?php if (isset($errorMessage)): ?>
            <div class="no-access">
                <div class="no-access-icon">🔒</div>
                <h2>No Access</h2>
                <p><?php echo e($errorMessage); ?></p>
                <a href="/logout.php" class="logout-link">Logout</a>
            </div>
        <?php else: ?>
            <div class="sections-header">
                <h1>🏠 Staff Resources Hub</h1>
                <p>Select which area you'd like to access</p>
            </div>

            <div class="sections-grid">
                <?php foreach ($sections as $section): ?>
                    <a href="<?php echo e($section['section_base_url']); ?>" class="section-card">
                        <div class="section-icon"><?php echo $section['section_icon']; ?></div>
                        <div class="section-title"><?php echo e($section['section_display_name']); ?></div>
                        <div class="section-description"><?php echo e($section['description'] ?? ''); ?></div>
                        <div class="section-role"><?php echo e(ucwords(str_replace('_', ' ', $section['role']))); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="/assets/js/nav.js?v=<?php echo time(); ?>"></script>
</body>
</html>
