<?php
/**
 * Permission Matrix Loader
 * 
 * Loads the permission matrix component for a specific package via AJAX.
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;

// Require admin login
Auth::requireLogin();
Auth::requireRole(['admin', 'super_admin']);

$packageSlug = $_GET['package'] ?? '';

if (empty($packageSlug)) {
    http_response_code(400);
    echo '<div class="alert alert-error">Package slug is required</div>';
    exit;
}

// Validate package exists
$db = \Hub\Database::getInstance()->getConnection();
$checkPackage = $db->prepare("SELECT name FROM sections WHERE slug = ? AND is_active = 1");
$checkPackage->execute([$packageSlug]);
$package = $checkPackage->fetch(PDO::FETCH_ASSOC);

if (!$package) {
    http_response_code(404);
    echo '<div class="alert alert-error">Package not found or inactive</div>';
    exit;
}

// Set variables for the included component
$readonly = false;

// Include the permission matrix component
include __DIR__ . '/partials/permission-matrix.php';
