<?php
/**
 * Package Module Viewer
 * 
 * Generic page for displaying package modules
 * URL: package-view.php?package=employee-evaluation&module=submit-evaluation
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/module-router.php';

use Hub\Auth;

// Require login
Auth::requireLogin();

$packageSlug = $_GET['package'] ?? '';
$moduleId = $_GET['module'] ?? '';

if (!$packageSlug) {
    header('Location: /modules.php');
    exit;
}

// Get package info
$manifest = getPackageManifest($packageSlug);
if (!$manifest) {
    header('Location: /modules.php?error=package_not_found');
    exit;
}

$packageName = $manifest['name'] ?? ucfirst(str_replace('-', ' ', $packageSlug));
$packageVersion = $manifest['version'] ?? '1.0.0';

// Get available modules
$modules = getPackageModules($packageSlug);

// If no module specified, show first available
if (!$moduleId && !empty($modules)) {
    $moduleId = $modules[0]['id'];
}

// Current module info
$currentModule = null;
foreach ($modules as $mod) {
    if ($mod['id'] === $moduleId) {
        $currentModule = $mod;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($packageName); ?> - The Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/main.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar with module navigation -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo htmlspecialchars($packageName); ?></h5>
                        <small class="text-muted">v<?php echo htmlspecialchars($packageVersion); ?></small>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($modules as $module): ?>
                            <a href="?package=<?php echo urlencode($packageSlug); ?>&module=<?php echo urlencode($module['id']); ?>" 
                               class="list-group-item list-group-item-action <?php echo $module['id'] === $moduleId ? 'active' : ''; ?>">
                                <i class="<?php echo htmlspecialchars($module['icon']); ?> me-2"></i>
                                <?php echo htmlspecialchars($module['title']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="/modules.php" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-arrow-left"></i> Back to Modules
                    </a>
                </div>
            </div>
            
            <!-- Main content -->
            <div class="col-md-9 col-lg-10">
                <?php echo renderFlashMessage(); ?>
                
                <?php if ($currentModule): ?>
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="<?php echo htmlspecialchars($currentModule['icon']); ?> me-2"></i>
                                <?php echo htmlspecialchars($currentModule['title']); ?>
                            </h4>
                            <?php if ($currentModule['description']): ?>
                                <p class="text-muted mb-0 mt-2"><?php echo htmlspecialchars($currentModule['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php echo renderModule($packageSlug, $moduleId); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <h4>Welcome to <?php echo htmlspecialchars($packageName); ?></h4>
                        <p>Please select a module from the sidebar to get started.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
