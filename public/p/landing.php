<?php

/**
 * Package Discovery Landing Page
 *
 * Shows all installed/available packages as cards.
 * Entry point: /p/ (no specific package selected)
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §5 (Cards → Mini Website Model)
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Layout;
use Hub\SiteSettings;
use Hub\Package\PackageLoader;

Auth::requireLogin();

$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$siteName = SiteSettings::get('site_name', 'The Hub');

$loader = new PackageLoader();
$packages = $loader->getInstalled();

$pageTitle = 'Packages';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php Layout::renderHead($pageTitle . ' - ' . $siteName, 'hub'); ?>
    <link rel="stylesheet" href="/assets/css/package-components.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/package-components.css') ?: time(); ?>">
    <style>
        .pkg-landing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .pkg-landing-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.2s, border-color 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .pkg-landing-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            border-color: #3b82f6;
        }

        .pkg-landing-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .pkg-landing-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #3b82f6;
            flex-shrink: 0;
        }

        .pkg-landing-card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
        }

        .pkg-landing-card-version {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.125rem;
        }

        .pkg-landing-card-desc {
            color: #6b7280;
            font-size: 0.875rem;
            line-height: 1.5;
            flex: 1;
            margin-bottom: 1rem;
        }

        .pkg-landing-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 1rem;
            border-top: 1px solid #f3f4f6;
        }

        .pkg-landing-card-author {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .pkg-landing-card-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #3b82f6;
        }

        .pkg-landing-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: #9ca3af;
        }

        .pkg-landing-empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .pkg-landing-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .pkg-landing-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
        }

        .pkg-landing-count {
            font-size: 0.875rem;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <?php include __DIR__ . '/../partials/header.php'; ?>

        <div class="main-content">
            <div class="container">

                <div class="pkg-landing-header">
                    <h1><i class="bi bi-box-seam"></i> Packages</h1>
                    <span class="pkg-landing-count"><?php echo count($packages); ?> available</span>
                </div>

                <?php if (empty($packages)): ?>
                    <div class="pkg-landing-empty">
                        <div class="pkg-landing-empty-icon"><i class="bi bi-box-seam"></i></div>
                        <h3>No Packages Installed</h3>
                        <p>Packages extend The Hub with new features and tools.<br>Contact your administrator to install packages.</p>
                    </div>
                <?php else: ?>
                    <div class="pkg-landing-grid">
                        <?php foreach ($packages as $pkg):
                            $pkgData = is_array($pkg) ? $pkg : [];
                            $pkgMeta = $pkgData['package'] ?? $pkgData;
                            $pkgId = $pkgMeta['id'] ?? $pkgMeta['package_id'] ?? '';
                            $displayName = $pkgMeta['display_name'] ?? $pkgData['_display_name'] ?? $pkgId;
                            $icon = $pkgMeta['icon'] ?? $pkgData['_icon'] ?? 'bi-box';
                            $desc = $pkgMeta['description'] ?? '';
                            $version = $pkgMeta['version'] ?? '1.0.0';
                            $author = $pkgMeta['author'] ?? '';
                            $baseUrl = '/p/' . $pkgId;
                        ?>
                            <a href="<?php echo e($baseUrl); ?>" class="pkg-landing-card">
                                <div class="pkg-landing-card-header">
                                    <div class="pkg-landing-card-icon">
                                        <i class="<?php echo e($icon); ?>"></i>
                                    </div>
                                    <div>
                                        <div class="pkg-landing-card-title"><?php echo e($displayName); ?></div>
                                        <div class="pkg-landing-card-version">v<?php echo e($version); ?></div>
                                    </div>
                                </div>
                                <div class="pkg-landing-card-desc"><?php echo e($desc); ?></div>
                                <div class="pkg-landing-card-footer">
                                    <span class="pkg-landing-card-author"><?php echo e($author); ?></span>
                                    <span class="pkg-landing-card-btn">Open <i class="bi bi-arrow-right"></i></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <?php include __DIR__ . '/../partials/footer.php'; ?>
    </div>
</body>

</html>
