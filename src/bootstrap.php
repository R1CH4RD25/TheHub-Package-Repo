<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configure session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // HTTPS only
ini_set('session.cookie_samesite', 'Lax');

session_save_path(__DIR__ . '/../sessions');
session_name('THEHUB_SESSION');
session_start();

// Set timezone
date_default_timezone_set('America/Chicago');

// Error reporting based on environment and debug mode
$debugMode = ($_ENV['DEBUG_MODE'] ?? 'false') === 'true';
$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

if ($isProduction && !$debugMode) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
} else {
    // Development mode or debug enabled - show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
}

// CSS Production Mode - Use minified production.css instead of individual files
// Enable in production for performance, disable in dev for easier debugging
define('CSS_PRODUCTION_MODE', $isProduction && !$debugMode);

// Generate CSP nonce for inline scripts/styles
if (!defined('CSP_NONCE')) {
    define('CSP_NONCE', base64_encode(random_bytes(16)));
}
if (!defined('Hub\CSP_NONCE')) {
    define('Hub\CSP_NONCE', CSP_NONCE);
}
if (!defined('Hub\Components\CSP_NONCE')) {
    define('Hub\Components\CSP_NONCE', CSP_NONCE);
}

// Maintenance mode check (before Auth is loaded)
$maintenanceMode = ($_ENV['MAINTENANCE_MODE'] ?? 'false') === 'true';
if ($maintenanceMode) {
    // Allow access to login, logout, and API endpoints for admins
    $allowedPaths = ['/login.php', '/logout.php', '/google_login.php', '/auth/', '/api/'];
    $currentPath = $_SERVER['PHP_SELF'] ?? '';
    $isAllowedPath = false;

    foreach ($allowedPaths as $path) {
        if (strpos($currentPath, $path) !== false) {
            $isAllowedPath = true;
            break;
        }
    }

    // If not an allowed path and not logged in as admin, show maintenance page
    if (!$isAllowedPath) {
        // Check if user is admin (after session is started)
        // Check multiple possible session keys for admin status
        $isAdmin = (
            (isset($_SESSION['user']) && in_array($_SESSION['user']['role'] ?? '', ['super_admin', 'admin'])) ||
            (isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'admin'])) ||
            (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['super_admin', 'admin']))
        );

        if (!$isAdmin) {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Maintenance Mode - The Hub</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #F59E0B; }
        p { color: #666; line-height: 1.6; }
        .icon { font-size: 64px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>System Maintenance</h1>
        <p>The Hub is currently undergoing maintenance.</p>
        <p>We apologize for the inconvenience. Please check back shortly.</p>
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
        <p style="font-size: 14px; color: #999;">If you are an administrator, please <a href="/login.php">log in</a> to access the system.</p>
    </div>
</body>
</html>';
            exit;
        }
    }
}

// CSRF Token Helper
function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// JSON Response Helper
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Sanitize output
if (!function_exists('e')) {
    function e($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Initialize request context (correlation IDs for audit tracing)
Hub\RequestContext::init();

// Bootstrap Laravel application if not already loaded
if (!isset($GLOBALS['laravelApp'])) {
    $laravelBootstrap = __DIR__ . '/../bootstrap/app.php';
    if (file_exists($laravelBootstrap)) {
        $GLOBALS['laravelApp'] = require_once $laravelBootstrap;
    }
}
