<?php

/**
 * Dynamic Package Renderer
 * Handles all package-based sections (/pkg/*)
 * Renders sections dynamically from database definition
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\Layout;
use Hub\SiteSettings;
use Hub\DynamicFormRenderer;

// Require login
Auth::requireLogin();

$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();

// Parse URL to get section slug
// URL format: /pkg/vm/ or /pkg/vm/view/123
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = array_filter(explode('/', $path));

// First part is 'pkg', second part is section identifier (vm, fuel, etc.)
$pathArray = array_values($pathParts);
if (count($pathArray) < 2 || $pathArray[0] !== 'pkg') {
    http_response_code(404);
    die('Invalid package URL');
}

$sectionIdentifier = $pathArray[1]; // e.g., 'vm'

// Get section from database
$db = Database::getInstance();
$section = $db->fetchOne("
    SELECT s.*, si.package_id, sp.package_data
    FROM sections s
    LEFT JOIN section_installations si ON s.id = si.section_id
    LEFT JOIN section_packages sp ON si.package_id = sp.package_id
    WHERE s.base_url LIKE ? AND s.is_active = 1
", ["/pkg/{$sectionIdentifier}/%"]);

if (!$section) {
    http_response_code(404);
    die('Section not found');
}

// Check access
$hasAccess = false;
if (in_array($userRole, ['super_admin', 'admin'])) {
    $hasAccess = true;
} else {
    $accessCheck = $db->fetchOne("
        SELECT 1 FROM section_role_access
        WHERE section_id = ? AND role = ?
    ", [$section['id'], $userRole]);
    $hasAccess = (bool)$accessCheck;
}

if (!$hasAccess) {
    http_response_code(403);
    die('Access denied');
}

// Parse package data
$packageData = json_decode($section['package_data'] ?? '{}', true);
$package = $packageData['package'] ?? [];
$entities = $packageData['entities'] ?? [];
$workflowStates = $packageData['workflow_states'] ?? [];

// Check if section has field definitions
$hasFields = (bool)$db->fetchOne("
    SELECT COUNT(*) as count FROM section_field_definitions WHERE section_id = ?
", [$section['id']])['count'];

// Determine view mode
$action = $pathArray[2] ?? 'list'; // list, view, edit, create

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php Layout::renderHead($section['display_name'] . ' - ' . SiteSettings::get('site_name', 'The Hub'), 'package'); ?>
</head>

<body>
    <div class="page-wrapper">
        <?php include __DIR__ . '/../partials/header.php'; ?>

        <div class="main-content">
            <div class="container">
                <div class="page-header">
                    <h1><?php echo htmlspecialchars($section['display_name']); ?></h1>
                    <?php if (!empty($section['description'])): ?>
                        <p class="page-description"><?php echo htmlspecialchars($section['description']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="package-content">
                    <?php if (!$hasFields): ?>
                        <!-- Configuration Required -->
                        <div class="alert alert-warning">
                            <h4><i class="bi bi-exclamation-triangle"></i> Package Configuration Required</h4>
                            <p style="margin: 1rem 0;">
                                This package uses Layer 2 format and needs field definitions to be configured.
                                The database tables have been created, but the form fields need to be set up.
                            </p>
                            <?php if (in_array($userRole, ['super_admin', 'admin'])): ?>
                                <p style="margin-top: 1rem;">
                                    <strong>Admin Actions:</strong>
                                </p>
                                <ul style="margin-left: 1.5rem;">
                                    <li>Go to <a href="/admin">Admin Dashboard</a> → Package Management → Configure</li>
                                    <li>Or create field definitions manually in the section configuration</li>
                                </ul>
                            <?php else: ?>
                                <p style="margin-top: 1rem;">
                                    Please contact an administrator to configure this package.
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Show Package Info -->
                        <div class="card" style="margin-top: 2rem;">
                            <div class="card-header">
                                <h5>Package Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <th style="width: 200px;">Package ID:</th>
                                        <td><?php echo e($section['package_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Version:</th>
                                        <td><?php echo e($package['version'] ?? 'Unknown'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Entities:</th>
                                        <td>
                                            <?php foreach ($entities as $entity): ?>
                                                <span class="badge badge-info"><?php echo e($entity['label'] ?? $entity['id']); ?></span>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($package['author'])): ?>
                                        <tr>
                                            <th>Author:</th>
                                            <td><?php echo e($package['author']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($action === 'create'): ?>
                        <!-- Create View - Use DynamicFormRenderer -->
                        <div class="form-container">
                            <?php
                            $formRenderer = new DynamicFormRenderer($section, [], []);
                            echo $formRenderer->render();
                            ?>
                        </div>

                    <?php elseif ($action === 'list'): ?>
                        <!-- List View (will be implemented with data fetching) -->
                        <div class="toolbar">
                            <button class="btn btn-primary" onclick="window.location.href='<?php echo e($section['base_url']); ?>create'">
                                <i class="bi bi-plus-circle"></i> Create New
                            </button>
                        </div>

                        <div class="alert alert-info">
                            <p>Data listing functionality coming soon. Fields are configured, now we need to implement data CRUD operations.</p>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-info">
                            <p>This view is under development. Available views: list, create</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/../partials/footer.php'; ?>
    </div>
</body>

</html>
